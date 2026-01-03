<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Zone;
use App\Models\OrderDetail;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Coupon;
use App\Models\BusinessSetting;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Traits\PlaceNewOrder;
use App\Models\Guest;
use Carbon\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Http;

class OrderCreateController extends Controller
{
    use PlaceNewOrder;

    /**
     * Create a new order with store integration
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'store_id' => 'required_without:store_code|exists:stores,id',
            'store_code' => 'required_without:store_id|exists:stores,store_code',
            'user_id' => 'nullable|exists:users,id',
            'f_name' => 'required|string|max:100',
            'l_name' => 'required|string|max:100',
            'cart_items' => 'required|array|min:1',
            'cart_items.*.item_id' => 'nullable',
            'cart_items.*.item_name' => 'nullable',
            'cart_items.*.price' => 'nullable|numeric|min:0',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.variation' => 'nullable|array',
            'cart_items.*.add_ons.*.id' => 'nullable',
            'cart_items.*.add_ons.*.name' => 'nullable|string',
            'cart_items.*.add_ons.*.quantity' => 'nullable|integer|min:1',
            'cart_items.*.add_ons.*.price' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash_on_delivery,digital_payment,wallet,offline_payment',
            'order_type' => 'required|in:take_away,delivery',
            'delivery_address' => 'required_if:order_type,delivery',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_number' => 'required|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
            'latitude' => 'required_if:order_type,delivery|numeric',
            'longitude' => 'required_if:order_type,delivery|numeric',
            'delivery_fee' => 'nullable|numeric|min:0',
            'schedule_at' => 'nullable|date|after:now',
            'delivery_time' => 'nullable|string|max:255',
            'order_note' => 'nullable|string|max:500',
            'delivery_instruction' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'dm_tips' => 'nullable|numeric|min:0',
            'cutlery' => 'nullable|boolean',
            'guest_id' => 'nullable|string',
            'delivery_man_id' => 'nullable|exists:delivery_men,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Helpers::error_processor($validator)
            ], 422);
        }

        try {
            DB::beginTransaction();

            $storeQuery = Store::with(['zone', 'module']);
            if ($request->has('store_id')) {
                $store = $storeQuery->where('id', $request->store_id)->first();
            } else {
                $store = $storeQuery->where('store_code', $request->store_code)->first();
            }
            if (!$store || !$store->module_id) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        [
                            'code' => 'moduleId',
                            'message' => 'Not found'
                        ]
                    ]
                ], 404);
            }

            $moduleExists = $store->zone && $store->zone->modules()->where('modules.id', $store->module_id)->exists();
            if (!$moduleExists) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        [
                            'code' => 'moduleId',
                            'message' => 'Not found'
                        ]
                    ]
                ], 404);
            }


            if ($request->order_type == 'delivery') {
                $zone = Zone::where('status', 1)
                    ->where('id', $store->zone_id)
                    ->first();

                if (!$zone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Delivery location is outside the service area'
                    ], 400);
                }
            }

            $cart_validation = $this->validateCartItems($request->cart_items, $store, $request);

            if (!$cart_validation['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $cart_validation['message']
                ], 400);
            }

            $cart_data = $cart_validation['data'];
            $product_price = $cart_data['product_price'];
            $total_addon_price = $cart_data['total_addon_price'];
            $store_discount_amount = $cart_data['store_discount_amount'];


            if ($store->minimum_order > $product_price + $total_addon_price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order amount is ' . $store->minimum_order . ' ' . Helpers::currency_code()
                ], 400);
            }


            $delivery_charge = 0;
            if ($request->order_type == 'delivery') {
                if ($request->has('delivery_fee') && is_numeric($request->delivery_fee)) {
                    $delivery_charge = $request->delivery_fee;
                } else {
                    $distanceData = $this->calculateDistance($store, $request->latitude, $request->longitude);
                    $distanceMeter = $distanceData['distance_meters'];
                    $distanceKm = $distanceMeter / 1000;
                    $distance = round($distanceKm, 2);

                    $deliveryConfig = $this->getDeliveryConfiguration($store);

                    $extraCharge = $this->calculateExtraCharge($distance, $deliveryConfig['self_delivery_status']);

                    $deliveryAmount = $this->calculateTieredDeliveryFee($distance);

                    $deliveryAmount += $extraCharge;
                    $maximumShippingCharge = $deliveryConfig['maximum_shipping_charge'];
                    if ($maximumShippingCharge > 0 && $deliveryAmount > $maximumShippingCharge) {
                        $deliveryAmount = $maximumShippingCharge;
                    }

                    $delivery_charge = round($deliveryAmount, 2);                
                }
            }

            // Get local currency conversion rate
            $localCurrency = \App\Models\LocalCurrencyConversion::first();
            $localCurrencyRate = $localCurrency ? $localCurrency->local_rate : 1;
            
            // Calculate local currency delivery fees by applying local currency rate to the final delivery charge
            $localCurrencyDeliveryFees = $delivery_charge * $localCurrencyRate;
            $localCurrencyDeliveryFees = round($localCurrencyDeliveryFees, 2);

            $coupon_discount_amount = 0;
            $coupon = null;
            if ($request->coupon_code) {
                $coupon = Coupon::where('code', $request->coupon_code)
                    ->where('status', 1)
                    ->where('start_date', '<=', now())
                    ->where('expire_date', '>=', now())
                    ->first();

                if ($coupon) {
                    $coupon_discount_amount = CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount);
                }
            }

            $total_before_tax = $product_price + $total_addon_price - $store_discount_amount - $coupon_discount_amount;
            $tax_amount = ($total_before_tax * $store->tax) / 100;

            $order_amount = $total_before_tax + $tax_amount + $delivery_charge + ($request->dm_tips ?? 0);
            $user_id = null;
            if ($request->user) {
                $user_id = $request->user->id;
                $is_guest = 0;
            } else {
                if ($request->user_id && User::find($request->user_id)) {
                    $user_id = $request->user_id;
                    $is_guest = 0;
                }
                elseif ($request->f_name && $request->l_name) {
                    $existingUser = User::where('f_name', $request->f_name)
                        ->where('l_name', $request->l_name)
                        ->first();

                    if ($existingUser) {
                        $user_id = $existingUser->id;
                        $is_guest = 0;
                    } else {
                        $guestData = [
                            'ip_address' => $request->ip(),
                            'fcm_token' => null
                        ];

                        if ($request->guest_id) {
                            $guest = Guest::firstOrCreate(
                                ['id' => $request->guest_id],
                                $guestData
                            );
                        } else {
                            $guest = Guest::firstOrCreate(
                                ['ip_address' => $request->ip()],
                                $guestData
                            );
                        }

                        $user_id = $guest->id;
                        $is_guest = 1;
                    }
                }
                else {
                    if ($request->guest_id) {
                        $guest = Guest::firstOrCreate(
                            ['id' => $request->guest_id],
                            [
                                'ip_address' => $request->ip(),
                                'fcm_token' => null
                            ]
                        );
                        $user_id = $guest->id;
                    } else {
                        $guest = Guest::firstOrCreate(
                            ['ip_address' => $request->ip()],
                            ['fcm_token' => null]
                        );
                        $user_id = $guest->id;
                    }
                    $is_guest = 1;
                }
            }
            // Create order
            $order = new Order();
            $order->user_id = $user_id;
            $order->dm_vehicle_id = 1;
            $order->is_guest = $is_guest;
            $order->store_id = $store->id;
            $order->order_amount = round($order_amount, 2);
            $order->payment_method = $request->payment_method;
            $order->payment_status = $request->payment_method == 'cash_on_delivery' ? 'unpaid' : 'paid';
            $order->order_status = 'confirmed';
            $order->order_type = $request->order_type;
            $order->zone_id = $store->zone_id;
            $order->module_id = $store->module_id;
            $order->coupon_code = $request->coupon_code;
            $order->coupon_discount_amount = round($coupon_discount_amount, 2);
            $order->total_tax_amount = round($tax_amount, 2);
            $order->store_discount_amount = round($store_discount_amount, 2);
            $order->delivery_charge = round($delivery_charge, 2);
            $order->dm_tips = $request->dm_tips ?? 0;
            $order->cutlery = $request->cutlery ?? 0;
            $order->scheduled = $request->schedule_at ? 1 : 0;
            $order->schedule_at = $request->schedule_at ? Carbon::parse($request->schedule_at) : now();
            $order->order_note = $request->order_note;
            $order->delivery_instruction = $request->delivery_instruction;
            $order->otp = rand(1000, 9999);
            $order->confirmed = now();
            
            // Add local currency fields
            $order->local_currency_rate = $localCurrencyRate;
            $order->local_currency_delivery_fees = $localCurrencyDeliveryFees;

            if ($request->delivery_time) {
                $order->delivery_time = $request->delivery_time;
            }
            if ($request->order_type == 'delivery' && $request->has('delivery_man_id') && $request->delivery_man_id) {
                $deliveryMan = DeliveryMan::where('id', $request->delivery_man_id)
                    ->active()
                    ->first();

                if ($deliveryMan) {
                    $maxOrders = config('dm_maximum_orders', 1);
                    if ($deliveryMan->current_orders <= $maxOrders + 2) {
                        $order->delivery_man_id = $deliveryMan->id;
                        $order->accepted = now();
                        $deliveryMan->current_orders = $deliveryMan->current_orders + 1;
                        $deliveryMan->save();
                        $deliveryMan->increment('assigned_order_count');
                        Log::info('Assigned delivery man', ['delivery_man_id' => $deliveryMan->id]);
                    } else {
                        Log::warning('Delivery man has exceeded maximum orders limit', [
                            'delivery_man_id' => $deliveryMan->id,
                            'current_orders' => $deliveryMan->current_orders,
                            'max_orders' => $maxOrders
                        ]);
                    }
                } else {
                    Log::warning('Delivery man not found or not active', [
                        'delivery_man_id' => $request->delivery_man_id
                    ]);
                }
            }

            if ($request->order_type == 'delivery') {
                $delivery_address = [
                    'contact_person_name' => $request->contact_person_name,
                    'contact_person_number' => $request->contact_person_number,
                    'contact_person_email' => $request->contact_person_email,
                    'address' => $request->delivery_address,
                    'latitude' => (string)$request->latitude,
                    'longitude' => (string)$request->longitude,
                    'address_type' => 'Delivery'
                ];
                $order->delivery_address = json_encode($delivery_address);

                if (isset($distance)) {
                    $order->distance = $distance;
                }
            }

            $order->save();

            foreach ($cart_data['order_details'] as $detail) {
                $order_detail = new OrderDetail();
                $order_detail->order_id = $order->id;
                $order_detail->item_id = $detail['item_id'] ?? null;
                $order_detail->quantity = $detail['quantity'];
                $order_detail->price = $detail['price'];
                $order_detail->discount_on_item = $detail['discount_on_item'];
                $order_detail->total_add_on_price = $detail['total_add_on_price'];
                $order_detail->tax_amount = $detail['tax_amount'];
                $order_detail->variation = json_encode($detail['variation']);
                $order_detail->add_ons = json_encode($detail['add_ons']);
                $order_detail->item_details = json_encode($detail['item_details']);
                $order_detail->save();
            }

            // Send notifications
            try {
                Helpers::send_order_notification($order);
            } catch (\Exception $e) {
                Log::error('Order notification failed: ' . $e->getMessage());
            }

            DB::commit();
            $order->load([
                'store:id,name,phone,address',
                'details.item:id,name',
                'delivery_man:id,f_name,l_name,phone,email,latitude,longitude,image'
            ]);

            $deliveryMan = null;
            if ($order->delivery_man) {
                $deliveryMan = [
                    'id' => $order->delivery_man->id,
                    'name' => trim($order->delivery_man->f_name . ' ' . $order->delivery_man->l_name),
                    'phone' => $order->delivery_man->phone,
                    'email' => $order->delivery_man->email ?? null,
                    'image' => $order->delivery_man->image ?? null,
                    'latitude' => $order->delivery_man->latitude,
                    'longitude' => $order->delivery_man->longitude
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_amount' => $order->order_amount,
                    'order_status' => $order->order_status,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'store' => $order->store,
                    'estimated_delivery_time' => $store->delivery_time,
                    'otp' => $order->otp,
                    'order' => $order,
                    'delivery_man' => $deliveryMan,
                    'tracking_api_url' => url('/api/v1/order-create/order/' . $order->id . '/tracking'),
                    'tracking_web_url' => url('/track-order/' . $order->id)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Format date to ISO string
     *
     * @param mixed $date
     * @return string
     */
    private function formatDate($date)
    {
        if ($date instanceof \DateTime) {
            return $date->format('c'); // ISO 8601 format
        } elseif (is_string($date)) {
            try {
                return Carbon::parse($date)->toIso8601String();
            } catch (\Exception $e) {
                return now()->toIso8601String();
            }
        } else {
            return now()->toIso8601String();
        }
    }    /**
     * Validate cart items and calculate totals
     */
    private function validateCartItems($cart_items, $store, $request)
    {
        $product_price = 0;
        $total_addon_price = 0;
        $store_discount_amount = 0;
        $order_details = [];

        foreach ($cart_items as $cart_item) {
            $item = null;

            if (!empty($cart_item['item_id'])) {
                $item = Item::find($cart_item['item_id']);
            }
            elseif (!empty($cart_item['item_name'])) {
                $item = Item::where('name', $cart_item['item_name'])->first();
            }

            if ($item) {
                $itemName = $item->name;
                $item_price = $item->price;
                $item_id_for_order = $item->id;
            } else {
                $itemName = $cart_item['item_name'] ?? 'External Item';
                $item_price = $cart_item['price'] ?? 0;
                $item_id_for_order = null;
            }

            $variation = $cart_item['variation'] ?? [];
            if (!empty($variation) && isset($variation['price'])) {
                $item_price = $variation['price'];
            }

            // Add-ons
            $addon_price = 0;
            $add_ons_data = [];
            if (!empty($cart_item['add_ons']) && is_array($cart_item['add_ons'])) {
                foreach ($cart_item['add_ons'] as $addon) {
                    $add_ons_data[] = [
                        'id' => $addon['id'] ?? null,
                        'name' => $addon['name'] ?? 'Addon',
                        'price' => $addon['price'] ?? 0,
                        'quantity' => $addon['quantity'] ?? 1
                    ];
                    $addon_price += (($addon['price'] ?? 0) * ($addon['quantity'] ?? 1));
                }
            }

            // Quantity & Discount
            $quantity = $cart_item['quantity'] ?? 1;
            $item_discount = $cart_item['discount'] ?? 0;

            $item_total = ($item_price - $item_discount) * $quantity;
            $addon_total = $addon_price * $quantity;

            $product_price += $item_total;
            $total_addon_price += $addon_total;
            $store_discount_amount += ($item_discount * $quantity);

            // Tax
            $tax_amount = (($item_total + $addon_total) * $store->tax) / 100;

            // Item details - include complete item information
            $item_details = [
                'id' => $item_id_for_order,
                'name' => $itemName,
                'description' => $cart_item['description'] ?? ($item ? $item->description : ''),
                'image' => $cart_item['image'] ?? ($item ? $item->image : null),
                'price' => $item_price,
                'tax' => $store->tax,
                'tax_type' => 'percent',
                'discount' => $item_discount,
                'discount_type' => 'amount',
                'category_id' => $item ? $item->category_id : null,
                'store_id' => $store->id,
                'created_at' => now(),
                'updated_at' => now(),
                'status' => 1,
                'variations' => $variation,
                'add_ons' => $add_ons_data,
                'attributes' => [],
                'choice_options' => [],
                'category_ids' => $item && $item->category ? [['id' => $item->category->id, 'position' => 1, 'name' => $item->category->name]] : [],
                'images' => $item ? $item->images : [],
                'stock' => $item ? $item->stock : 0,
                'module_id' => $item ? $item->module_id : null,
                'slug' => $item ? $item->slug : null,
                'organic' => $item ? $item->organic : 0,
                'is_approved' => $item ? $item->is_approved : 0,
                'module_type' => $item && $item->module ? $item->module->module_type : null,
                'translations' => $item ? $item->translations->toArray() : [],
                'storage' => $item ? $item->storage->toArray() : [],
                'module' => $item && $item->module ? $item->module->toArray() : null,
                'image_full_url' => $item ? $item->image_full_url : null,
                'images_full_url' => $item ? $item->images_full_url : []
            ];

            $order_details[] = [
                'item_id' => $item_id_for_order,
                'quantity' => $quantity,
                'price' => $item_price,
                'discount_on_item' => $item_discount,
                'total_add_on_price' => $addon_total,
                'tax_amount' => $tax_amount,
                'variation' => $variation,
                'add_ons' => $add_ons_data,
                'item_details' => $item_details
            ];
        }

        return [
            'success' => true,
            'data' => [
                'product_price' => $product_price,
                'total_addon_price' => $total_addon_price,
                'store_discount_amount' => $store_discount_amount,
                'order_details' => $order_details
            ]
        ];
    }

    /**
     * Calculate delivery charge based on store settings and distance
     */
    private function calculateDeliveryCharge($store, $distance)
    {
        $delivery_charge = $store->minimum_shipping_charge ?? 0;

        if ($store->per_km_shipping_charge && $distance > 0) {
            $per_km_charge = $store->per_km_shipping_charge * $distance;
            $delivery_charge = max($delivery_charge, $per_km_charge);
        }

        if ($store->maximum_shipping_charge && $delivery_charge > $store->maximum_shipping_charge) {
            $delivery_charge = $store->maximum_shipping_charge;
        }

        return $delivery_charge;
    }

    /**
     * Calculate distance using Google DistanceMatrix API or fallback Haversine
     */
    private function calculateDistance($store, $destinationLat, $destinationLng)
    {
        $apiKey = env('GOOGLE_MAP_KEY');
        $origin = $store->latitude . ',' . $store->longitude;
        $destination = "$destinationLat,$destinationLng";

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $origin,
                'destinations' => $destination,
                'units' => 'metric',
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['rows'][0]['elements'][0]['status'] ?? null;

                if ($status === 'OK') {
                    $distanceMeters = $data['rows'][0]['elements'][0]['distance']['value'];
                    $distanceKm = round($distanceMeters / 1000, 2);

                    return [
                        'distance_text' => $data['rows'][0]['elements'][0]['distance']['text'],
                        'distance_meters' => $distanceMeters,
                        'distance_km' => $distanceKm,
                        'duration_text' => $data['rows'][0]['elements'][0]['duration']['text'],
                        'duration_value' => $data['rows'][0]['elements'][0]['duration']['value']
                    ];
                }
            }
        } catch (\Exception $e) {

            Log::error('Google Distance Matrix API error: ' . $e->getMessage());
        }


        $storeLat = $store->latitude;
        $storeLng = $store->longitude;

        $distanceKm = $this->calculateHaversineDistance($storeLat, $storeLng, $destinationLat, $destinationLng);

        return [
            'distance_text' => $distanceKm . ' km (approx)',
            'distance_meters' => $distanceKm * 1000,
            'distance_km' => $distanceKm,
            'duration_text' => 'N/A',
            'duration_value' => 0
        ];
    }

    /**
     * Haversine formula for fallback distance calculation
     */
    private function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2); // km
    }

    /**
     * Get delivery configuration for the store
     */
    private function getDeliveryConfiguration($store)
    {
        $moduleCharge = $store->zone->modules()->where('modules.id', $store->module_id)->first();

        // Check if store has store_sub relationship and is valid, otherwise use default
        $subSelfDelivery = false;
        if (method_exists($store, 'store_sub') && $store->store_sub) {
            $subSelfDelivery = $store->store_sub->self_delivery ?? false;
        } else {
            $subSelfDelivery = $store->self_delivery_system ?? false;
        }

        if ($subSelfDelivery) {
            $perKm = $store->per_km_shipping_charge ?? 0;
            $min = $store->minimum_shipping_charge ?? 0;
            $max = $store->maximum_shipping_charge ?? 0;
            $selfDelivery = 1;
        } else {
            $selfDelivery = 0;
            if ($moduleCharge) {
                $perKm = $moduleCharge->pivot->per_km_shipping_charge;
                $min = $moduleCharge->pivot->minimum_shipping_charge;
                $max = $moduleCharge->pivot->maximum_shipping_charge ?? 0;
            } else {
                $perKm = (float)BusinessSetting::where(['key' => 'per_km_shipping_charge'])->first()->value;
                $min = (float)BusinessSetting::where(['key' => 'minimum_shipping_charge'])->first()->value;
                $max = 0;
            }
        }

        return [
            'per_km_shipping_charge' => (float)$perKm,
            'minimum_shipping_charge' => (float)$min,
            'maximum_shipping_charge' => (float)$max,
            'self_delivery_status' => $selfDelivery
        ];
    }

    /**
     * Calculate extra charge (if any external API is needed)
     */
    private function calculateExtraCharge($distance, $selfDeliveryStatus)
    {
        try {
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * New tiered delivery fee calculation
     * 0 – 5 km       => 3 flat
     * 5.01 – 8 km    => 3 + 0.50 per km (only for kms between 5 and 8)
     * Above 8 km     => 3 + (0.50 per km for 5–8) + 0.70 per km thereafter
     */
    private function calculateTieredDeliveryFee(float $distanceKm): float
    {
        if ($distanceKm <= 5) {
            return 3.0;
        }

        if ($distanceKm <= 8) {
            $extraKm = $distanceKm - 5;
            return 3.0 + ($extraKm * 0.50);
        }

        // Above 8 km
        $fixed_cost  = 3 + 1.5;
        $tier3Km  = $distanceKm - 8;
        $feeTier3 = $tier3Km * 0.70;
        return  $fixed_cost + $feeTier3;
    }
    /**
     * Get order by ID with store details
     */
    public function getOrder(Request $request, $order_id)
    {
        $validator = Validator::make(['order_id' => $order_id], [
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order ID',
                'errors' => Helpers::error_processor($validator)
            ], 422);
        }

        $order = Order::with('details', 'offline_payments', 'parcel_category')
            ->with([
                'store:id,name,phone,email,address,logo,cover_photo,latitude,longitude',
                'details.item:id,name,image',
                'customer:id,f_name,l_name,phone,email',
                'delivery_man:id,f_name,l_name,phone,email,latitude,longitude,image'
            ])->find($order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $details = isset($order->details) ? $order->details : null;
        if ($details != null && $details->count() > 0) {
            $details = Helpers::order_details_data_formatting($details);
            $details[0]['is_guest'] = (int)$order->is_guest;
            return response()->json($details, 200);
        } else if ($order->order_type == 'parcel' || $order->prescription_order == 1) {
            $order->delivery_address = json_decode($order->delivery_address, true);
            if ($order->prescription_order && $order->order_attachment) {
                $order->order_attachment = json_decode($order->order_attachment, true);
            }
            return response()->json(($order), 200);
        }

        $delivery_address = json_decode($order->delivery_address, true);

        // Prepare delivery man payload so frontend can display the rider
        $deliveryManLocation = null;
        if ($order->delivery_man) {
            $deliveryManLocation = [
                'id' => $order->delivery_man->id,
                'name' => trim($order->delivery_man->f_name . ' ' . $order->delivery_man->l_name),
                'phone' => $order->delivery_man->phone,
                'email' => $order->delivery_man->email ?? null,
                'image' => $order->delivery_man->image ?? null,
                'latitude' => $order->delivery_man->latitude,
                'longitude' => $order->delivery_man->longitude
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'delivery_address' => $delivery_address,
                'store' => $order->store ? Helpers::store_data_formatting($order->store) : null,
                'delivery_man' => $deliveryManLocation
            ]
        ], 200);
    }

    /**
     * Get order tracking information with map data
     *
     * @param Request $request
     * @param int $order_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderTracking(Request $request, $order_id)
    {
        $validator = Validator::make(array_merge(['order_id' => $order_id], $request->all()), [
            'order_id' => 'required|integer|exists:orders,id',
            'user_id' => 'nullable|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order ID or user ID',
                'errors' => Helpers::error_processor($validator)
            ], 422);
        }

        $user_id = $request->user_id ?? ($request->user ? $request->user->id : null);

        $order = Order::with([
            'store:id,name,latitude,longitude,address,phone',
            'delivery_man:id,f_name,l_name,phone,image,latitude,longitude',
            'delivery_history'
        ])
        ->where('id', $order_id)
        ->when($user_id, function ($query) use ($user_id) {
            return $query->where('user_id', $user_id);
        }, function ($query) {
            return $query;
        })
        ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or access denied'
            ], 404);
        }

        $deliveryAddress = json_decode($order->delivery_address, true);

        $trackingData = [
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'created_at' => $order->created_at ? (is_object($order->created_at) ? $order->created_at->format('Y-m-d H:i:s') : $order->created_at) : null,
            'schedule_at' => $order->schedule_at ? (is_object($order->schedule_at) ? $order->schedule_at->format('Y-m-d H:i:s') : $order->schedule_at) : null,
            'delivery_man' => $order->delivery_man ? [
                'id' => $order->delivery_man->id,
                'name' => $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name,
                'phone' => $order->delivery_man->phone,
                'image' => $order->delivery_man->image,
                'current_location' => [
                    'latitude' => $order->delivery_man->latitude,
                    'longitude' => $order->delivery_man->longitude
                ]
            ] : null,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->name,
                'phone' => $order->store->phone,
                'location' => [
                    'latitude' => $order->store->latitude,
                    'longitude' => $order->store->longitude,
                    'address' => $order->store->address
                ]
            ] : null,
            'customer' => [
                'location' => $deliveryAddress ? [
                    'latitude' => $deliveryAddress['latitude'] ?? null,
                    'longitude' => $deliveryAddress['longitude'] ?? null,
                    'address' => $deliveryAddress['address'] ?? null
                ] : null
            ],
            'delivery_path' => [],
            'status_timeline' => [
                'pending' => $order->pending ? (is_object($order->pending) ? $order->pending->format('Y-m-d H:i:s') : (is_string($order->pending) ? $order->pending : null)) : null,
                'confirmed' => $order->confirmed ? (is_object($order->confirmed) ? $order->confirmed->format('Y-m-d H:i:s') : (is_string($order->confirmed) ? $order->confirmed : null)) : null,
                'processing' => $order->processing ? (is_object($order->processing) ? $order->processing->format('Y-m-d H:i:s') : (is_string($order->processing) ? $order->processing : null)) : null,
                'handover' => $order->handover ? (is_object($order->handover) ? $order->handover->format('Y-m-d H:i:s') : (is_string($order->handover) ? $order->handover : null)) : null,
                'picked_up' => $order->picked_up ? (is_object($order->picked_up) ? $order->picked_up->format('Y-m-d H:i:s') : (is_string($order->picked_up) ? $order->picked_up : null)) : null,
                'delivered' => $order->delivered ? (is_object($order->delivered) ? $order->delivered->format('Y-m-d H:i:s') : (is_string($order->delivered) ? $order->delivered : null)) : null,
            ],
            'estimated_delivery_time' => $order->store ? $order->store->delivery_time : null,
            'map_data' => [
                'store_location' => $order->store ? [
                    'latitude' => $order->store->latitude,
                    'longitude' => $order->store->longitude,
                    'name' => $order->store->name,
                    'address' => $order->store->address
                ] : null,
                'customer_location' => $deliveryAddress ? [
                    'latitude' => $deliveryAddress['latitude'] ?? null,
                    'longitude' => $deliveryAddress['longitude'] ?? null,
                    'address' => $deliveryAddress['address'] ?? null
                ] : null,
                'delivery_man_location' => $order->delivery_man ? [
                    'latitude' => $order->delivery_man->latitude,
                    'longitude' => $order->delivery_man->longitude,
                    'name' => $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name
                ] : null
            ]
        ];

        if ($order->delivery_history) {
            foreach ($order->delivery_history as $history) {
                $trackingData['delivery_path'][] = [
                    'latitude' => $history->latitude,
                    'longitude' => $history->longitude,
                    'location' => $history->location,
                    'timestamp' => $history->created_at ? (is_object($history->created_at) ? $history->created_at->format('Y-m-d H:i:s') : (is_string($history->created_at) ? $history->created_at : null)) : null
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $trackingData
        ], 200);
    }

    /**
     * Get store details for order creation
     */
    public function getStoreDetails(Request $request, $identifier = null)
    {
        $storeId = $identifier ?? $request->store_id;
        $storeCode = $request->store_code;
        $validatorData = [];
        if ($storeId) {
            $validatorData['store_id'] = $storeId;
            $validatorRules = ['store_id' => 'required|integer|exists:stores,id'];
        } else if ($storeCode) {
            $validatorData['store_code'] = $storeCode;
            $validatorRules = ['store_code' => 'required|string|exists:stores,store_code'];
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either store_id or store_code is required'
            ], 422);
        }

        $validator = Validator::make($validatorData, $validatorRules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid store identifier',
                'errors' => Helpers::error_processor($validator)
            ], 422);
        }

        $storeQuery = Store::with(['zone', 'module', 'schedules'])->where('status', 1);
        if ($storeId) {
            $store = $storeQuery->where('id', $storeId)->first();
        } else {
            $store = $storeQuery->where('store_code', $storeCode)->first();
        }

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or not active'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'store' => Helpers::store_data_formatting($store),
                'minimum_order_amount' => $store->minimum_order,
                'delivery_time' => $store->delivery_time,
                'delivery_charge_info' => [
                    'minimum_charge' => $store->minimum_shipping_charge,
                    'per_km_charge' => $store->per_km_shipping_charge,
                    'maximum_charge' => $store->maximum_shipping_charge
                ],
                'tax_percentage' => $store->tax,
                'free_delivery' => $store->free_delivery,
                'schedule_order' => $store->schedule_order
            ]
        ], 200);
    }

    /**
     * Update order status and send notification
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_order_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'order_status' => 'required|in:pending,confirmed,processing,handover,picked_up,delivered,canceled,refund_requested,refund_request_canceled,refunded,failed'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $order = Order::findOrFail($request->order_id);
            $previousStatus = $order->order_status;
            $order->order_status = $request->order_status;
            $statusTimestampField = $request->order_status;
            if (in_array($request->order_status, ['pending', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded', 'failed'])) {
                $order->$statusTimestampField = now();
            }

            $order->save();

            // Send notification
            Helpers::send_order_notification($order);

            return response()->json([
                'message' => translate('messages.order_status_updated_successfully'),
                'order_id' => $order->id,
                'order_status' => $order->order_status
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [
                    ['code' => 'order-status-update', 'message' => translate('messages.failed_to_update_order_status')]
                ]
            ], 500);
        }
    }
}
