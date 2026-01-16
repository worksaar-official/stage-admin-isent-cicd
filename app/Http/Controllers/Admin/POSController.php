<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\LocalCurrencyConversion;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Zone;
use App\Traits\PlaceNewOrder;
use Brian2694\Toastr\Facades\Toastr;
use Http;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\CustomerLogic;
use App\CentralLogics\ProductLogic;
use App\Mail\OrderVerificationMail;
use App\Models\Store;
use App\Mail\PlaceOrder;
use App\Models\BusinessSetting;
use App\Models\DMVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Scopes\StoreScope;
use Illuminate\Support\Facades\Config;

class POSController extends Controller
{
    use PlaceNewOrder;
    public function index(Request $request)
    {
        $time = Carbon::now()->toTimeString();
        $category = $request->query('category_id', 0);
        $module_id = Config::get('module.current_module_id');
        $store_id = $request->query('store_id', null);
        $categories = Category::active()->module(Config::get('module.current_module_id'))->get();
        $store = Store::active()->with('store_sub')->find($store_id);
        if(!$store && $request->has('store_id')){
            Toastr::error(translate('messages.Store_is_not_available'));
            return back();
        }
        $keyword = $request->query('keyword', false);
        $key = explode(' ', $keyword);

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if(!isset($cart['store_id']) || $cart['store_id'] != $store_id) {
                session()->forget('cart');
                session()->forget('tax_amount');
                session()->forget('tax_included');
                session()->forget('cart');
                session()->forget('address');
                session()->forget('cart_product_ids');
            }
        }

        if (empty(session('cart')) || count(session('cart')) === 0) {
            session()->forget('tax_amount');
            session()->forget('tax_included');
        }

        $products = Item::withoutGlobalScope(StoreScope::class)->active()
        ->when($category, function($query)use($category){
            $query->whereHas('category',function($q)use($category){
                return $q->whereId($category)->orWhere('parent_id', $category);
            });
        })
        ->when($keyword, function($query)use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
        })
        ->whereHas('store', function($query)use($store_id, $module_id){
            return $query->where(['id'=>$store_id, 'module_id'=>$module_id]);
        });
        if(Config::get('module.current_module_type') == 'food'){
            $products=  $products->available($time);
        }
        $products=  $products->latest()->paginate(10);
        return view('admin-views.pos.index', compact('categories', 'products','category', 'keyword', 'store', 'module_id'));
    }

    public function quick_view(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->findOrFail($request->product_id);

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-data', compact('product'))->render(),
        ]);
    }

    public function quick_view_card_item(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->findOrFail($request->product_id);
        $item_key = $request->item_key;
        $cart_item = session()->get('cart')[$item_key];

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key'))->render(),
        ]);
    }

    public function variant_price(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($request->id);
        if($product->module->module_type == 'food'){
            $price = $product->price;
            $addon_price = 0;
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                }
            }
            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {

                $price_total =  $price + Helpers::food_variation_price($product_variations, $request->variations);
                $price= $price_total - Helpers::product_discount_calculate($product, $price_total, $product->store)['discount_amount'];
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        }else{

            $str = '';
            $quantity = 0;
            $price = 0;
            $addon_price = 0;

            foreach (json_decode($product->choice_options) as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }

            if($request['addon_id'])
            {
                foreach($request['addon_id'] as $id)
                {
                    $addon_price+= $request['addon-price'.$id]*$request['addon-quantity'.$id];
                }
            }

            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price - Helpers::product_discount_calculate($product, json_decode($product->variations)[$i]->price,$product->store)['discount_amount'];
                    }
                }
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price,$product->store)['discount_amount'];
            }
        }

        return array('price' => Helpers::format_currency(($price * $request->quantity)+$addon_price));
    }

    public function addDeliveryInfo(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'contact_person_name' => 'required',
            'contact_person_number' => 'required',
            'longitude' => 'required',
            'latitude' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'delivery',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'distance' => $request->distance??0,
            'delivery_fee' => $request->delivery_fee?:0,
            'longitude' => (string)$request->longitude,
            'latitude' => (string)$request->latitude,
        ];

        $request->session()->put('address', $address);

        return response()->json([
            'data' => $address,
            'view' => view('admin-views.pos._address', compact('address'))->render(),
        ]);
    }

    public function item_stock_view(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->findOrFail($request->id);
        $selected_item = $request->all();
        $stock= $this->get_stocks($product,$selected_item);
            return response()->json([
                'view' => view('admin-views.pos._item-stock-view', compact('product','selected_item','stock' ))->render(),
            ]);
    }

    public function item_stock_view_update(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->findOrFail($request->id);
        $selected_item = $request->all();
        $item_key = $request->cart_item_key;
        $cart_item = session()->get('cart')[$item_key];
        $stock= $this->get_stocks($product,$selected_item);
        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key' ,'stock','selected_item'))->render(),
        ]);
    }


    private function get_stocks($product,$selected_item){
        try {
            if($product->module->module_type == 'food'){
                return null;
            }
            $choice_options=   json_decode($product?->choice_options, true);
            $variation=  json_decode($product?->variations, true);

            if(is_array($choice_options) && is_array($variation)  &&  count($choice_options) == 0 && count($variation) == 0 ){
                return $product->stock ?? null ;
            }

            $choiceNames = array_column($choice_options, 'name');
            $variations = array_map(function ($choiceName) use ($selected_item) {
                return str_replace(' ', '', $selected_item[$choiceName]);
            }, $choiceNames);
            $resultString = implode('-', $variations);
            $stockVariations = json_decode($product->variations, true);
            foreach ($stockVariations as $variation) {
                if ($variation['type'] == $resultString) {
                    $stock = $variation['stock'];
                    break;
                }
            }
        } catch (\Throwable $th) {
            info($th->getMessage());
        }

        return $stock ?? null ;
    }

    public function addToCart(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($request->id);
        $product_ids = $request->session()->has('cart_product_ids') ? $request->session()->get('cart_product_ids') : [];
        if($product->module->module_type == 'food'){
            $data = array();
            $data['id'] = $product->id;
            $str = '';
            $variations = [];
            $price = 0;
            $addon_price = 0;
            $variation_price=0;

            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {
                foreach($request->variations  as $key=> $value ){

                    if($value['required'] == 'on' &&  isset($value['values']) == false){
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select items from') . ' ' . $value['name'],
                        ]);
                    }
                    if(isset($value['values'])  && $value['min'] != 0 && $value['min'] > count($value['values']['label'])){
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select minimum ').$value['min'].translate(' For ').$value['name'].'.',
                        ]);
                    }
                    if(isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])){
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select maximum ').$value['max'].translate(' For ').$value['name'].'.',
                        ]);
                    }
                }
                $variation_data = Helpers::get_varient($product_variations, $request->variations);
                $variation_price = $variation_data['price'];
                $variations = $request->variations;
            }
            $data['variations'] = $variations;
            $data['variant'] = $str;

            $price = $product->price + $variation_price;
            $data['variation_price'] = $variation_price;
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['name'] = $product->name;
            $data['discount'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data['image'] = $product->image;
            $data['image_full_url'] = $product->image_full_url;
            $data['storage'] = $product->storage?->toArray();
            $data['add_ons'] = [];
            $data['add_on_qtys'] = [];
            $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;
            $data['stock_quantity'] = null;
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $data['add_on_qtys'][] = $request['addon-quantity' . $id];
                }
                $data['add_ons'] = $request['addon_id'];
            }

            $data['addon_price'] = $addon_price;

            if ($request->session()->has('cart')) {
                $cart = $request->session()->get('cart', collect([]));
                if (isset($request->cart_item_key)) {
                    $cart[$request->cart_item_key] = $data;
                    $data = 2;
                } else {
                    $cart->push($data);
                }
            } else {
                $cart = collect([$data,'store_id'=>$product->store_id]);
                $request->session()->put('cart', $cart);
            }
            $product_ids[$product->id] = $request['quantity'];
            $request->session()->put('cart_product_ids', $product_ids);
        }else{

            $data = array();
            $data['id'] = $product->id;
            $str = '';
            $variations = [];
            $price = 0;
            $addon_price = 0;

            $selected_item = $request->all();
            $stock= $this->get_stocks($product,$selected_item);
            if($product?->maximum_cart_quantity > 0){
                if(((isset($stock) && min($stock, $product?->maximum_cart_quantity) < $request->quantity )||  $product?->maximum_cart_quantity <  $request->quantity  ) ){
                    return response()->json([
                        'data' => 0
                    ]);
                }
            }

            //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
            foreach (json_decode($product->choice_options) as $key => $choice) {
                $data[$choice->name] = $request[$choice->name];
                $variations[$choice->title] = $request[$choice->name];
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }
            $data['variations'] = $variations;
            $data['variant'] = $str;
            if ($request->session()->has('cart') && !isset($request->cart_item_key)) {
                if (count($request->session()->get('cart')) > 0) {
                    foreach ($request->session()->get('cart') as $key => $cartItem) {
                        if (is_array($cartItem) && $cartItem['id'] == $request['id'] && $cartItem['variant'] == $str) {
                            return response()->json([
                                'data' => 1
                            ]);
                        }
                    }

                }
            }
            //Check the string and decreases quantity for the stock
            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price;
                        $data['variations'] = json_decode($product->variations, true)[$i];
                    }
                }
            } else {
                $price = $product->price;
            }

            $data['stock_quantity'] = $stock;
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['name'] = $product->name;
            $data['discount'] = Helpers::product_discount_calculate($product, $price,$product->store)['discount_amount'];
            $data['image'] = $product->image;
            $data['image_full_url'] = $product->image_full_url;
            $data['storage'] = $product->storage?->toArray();
            $data['add_ons'] = [];
            $data['add_on_qtys'] = [];
            $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;

            if($request['addon_id'])
            {
                foreach($request['addon_id'] as $id)
                {
                    $addon_price+= $request['addon-price'.$id]*$request['addon-quantity'.$id];
                    $data['add_on_qtys'][]=$request['addon-quantity'.$id];
                }
                $data['add_ons'] = $request['addon_id'];
            }

            $data['addon_price'] = $addon_price;

            if ($request->session()->has('cart')) {
                $cart = $request->session()->get('cart', collect([]));

                if(!isset($cart['store_id']) || $cart['store_id'] != $product->store_id) {
                    return response()->json([
                        'data' => -1
                    ]);
                }
                if(isset($request->cart_item_key))
                {
                    $cart[$request->cart_item_key] = $data;
                    $data = 2;
                }
                else
                {
                    $cart->push($data);
                }


            } else {
                $cart = collect([$data]);
                $cart->put('store_id', $product->store_id);
                $request->session()->put('cart', $cart);
            }
            $product_ids[$product->id] = $request['quantity'];
            $request->session()->put('cart_product_ids', $product_ids);
        }

        $this->setPosCalculatedTax($product->store);
        return response()->json([
            'data' => $data
        ]);
    }

    public function single_items(Request $request)
    {
        $time = Carbon::now()->toTimeString();
        $category = $request->category_id??0;
        $module_id = Config::get('module.current_module_id');
        $store_id = $request->store_id;
        $categories = Category::active()->module(Config::get('module.current_module_id'))->get();
        $store = Store::active()->find($store_id);
        $keyword = $request->keyword??false;
        $key = explode(' ', $keyword);
        $products = Item::withoutGlobalScope(StoreScope::class)->active()
            ->when($category, function($query)use($category){
                $query->whereHas('category',function($q)use($category){
                    return $q->whereId($category)->orWhere('parent_id', $category);
                });
            })
            ->when($keyword, function($query)use($key){
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store', function($query)use($store_id, $module_id){
                return $query->where(['id'=>$store_id, 'module_id'=>$module_id]);
            });

            if(Config::get('module.current_module_type') == 'food'){
                $products=  $products->available($time);
            }
            $products=  $products->latest()->paginate(10);

        return view('admin-views.pos._single_product_list', compact('products','store'));
    }

    public function cart_items(Request $request)
    {
        $store = Store::find($request->store_id);
        return view('admin-views.pos._cart', compact('store'));
    }


    public function removeFromCart(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if($request->session()->has('cart_product_ids')) {
                $item_id = $cart[$request->key]['id'];
                $product_ids = $request->session()->get('cart_product_ids');
                if (isset($product_ids[$item_id])) {
                    unset($product_ids[$item_id]);
                    $request->session()->put('cart_product_ids', $product_ids);
                }
            }

            $cart->forget($request->key);
            $request->session()->put('cart', $cart);

            $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($item_id);
            if ($product && $product->store) {
                $this->setPosCalculatedTax($product->store);
            }

        }

        return response()->json([],200);
    }

    public function updateQuantity(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart = $cart->map(function ($object, $key) use ($request) {
            if ($key == $request->key) {
                $object['quantity'] = $request->quantity;
            }
            return $object;
        });
        $request->session()->put('cart', $cart);
        if($request->session()->has('cart_product_ids')) {
            $item_id = $cart[$request->key]['id'];
            $product_ids = $request->session()->get('cart_product_ids');
            if (isset($product_ids[$item_id])) {
                $product_ids[$item_id] = $request['quantity'];
                $request->session()->put('cart_product_ids', $product_ids);
            }
        }

        try {
            $product_id = $cart[$request->key]['id'];
            $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($product_id);
            if ($product && $product->store) {
                $this->setPosCalculatedTax($product->store);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to recalculate tax after quantity update: ' . $e->getMessage());
        }

        return response()->json([],200);
    }

    public function emptyCart(Request $request)
    {
        session()->forget('cart');
        session()->forget('tax_amount');
        session()->forget('tax_included');
        session()->forget('address');
        session()->forget('cart_product_ids');
        return response()->json([], 200);
    }

    public function update_tax(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['tax'] = $request->tax;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_discount(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['discount'] = $request->discount;
        $cart['discount_type'] = $request->type;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_paid(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['paid'] = $request->paid;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function get_customers(Request $request){
        $key = explode(' ', $request['q']);
        $data = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })
            ->limit(8)
            ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')]);

        return response()->json($data);
    }

    public function place_order(Request $request)
    {
        if(!$request->user_id){
            Toastr::error(translate('messages.no_customer_selected'));
            return back();
        }
        $customer = User::find($request->user_id);

        if(!$request->type){
            Toastr::error(translate('No payment method selected'));
            return back();
        }
        if ($request->session()->has('cart')) {
            if (count($request->session()->get('cart')) < 2) {
                Toastr::error(translate('messages.cart_empty_warning'));
                return back();
            }
        } else {
            Toastr::error(translate('messages.cart_empty_warning'));
            return back();
        }
        if ($request->session()->has('address')) {
            $address = $request->session()->get('address');
        }else {
            if(!isset($address['delivery_fee'])){
                Toastr::error(translate('messages.please_select_a_valid_delivery_location_on_the_map'));
                return back();
            }
            Toastr::error(translate('messages.delivery_information_warning'));
            return back();
        }
        if($request->type == 'wallet' && Helpers::get_business_settings('wallet_status', false) != 1)
        {
            Toastr::error(translate('messages.customer_wallet_disable_warning'));
            return back()->withInput()->with('customer', $customer);
        }

        $distance_data = isset($address) ? $address['distance'] : 0;

        $store = Store::with('store_sub')->find($request->store_id);


        if(!$store){
            Toastr::error(translate('messages.Sorry_the_store_is_not_available'));
            return back()->withInput()->with('customer', $customer);
        }


        $self_delivery_status = $store->self_delivery_system;
        $store_sub=$store?->store_sub;
        if ($store->is_valid_subscription) {

            $self_delivery_status = $store_sub->self_delivery;

            if($store_sub->max_order != "unlimited" && $store_sub->max_order <= 0){
                Toastr::error(translate('messages.The_store_has_reached_the_maximum_number_of_orders'));
                return back()->withInput()->with('customer', $customer);
            }
        } elseif($store->store_business_model == 'unsubscribed'){
            Toastr::error(translate('messages.The_store_is_not_subscribed_or_subscription_has_expired'));
            return back()->withInput()->with('customer', $customer);
        }

        $extra_charges = 0;
        $vehicle_id = null;

        if($self_delivery_status != 1){

            $data =  DMVehicle::where(function ($query) use ($distance_data) {
                $query->where('starting_coverage_area', '<=', $distance_data)->where('maximum_coverage_area', '>=', $distance_data)
                ->orWhere(function ($query) use ($distance_data) {
                    $query->where('starting_coverage_area', '>=', $distance_data);
                });
            })
            ->active()
                ->orderBy('starting_coverage_area')->first();

            $extra_charges = (float) (isset($data) ? $data->extra_charges  : 0);
            $vehicle_id = (isset($data) ? $data->id  : null);
        }



        $cart = $request->session()->get('cart');

        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;

        $order_details = [];
        $product_data = [];
        $order = new Order();
        //worksaar start
        $order->order_source = 'isent_web'; // Orders from admin POS
        //worksaar end
        $order->id = 100000 + Order::count() + 1;
        if (Order::find($order->id)) {
            $order->id = Order::latest()->first()->id + 1;
        }
        $order->distance = isset($address) ? $address['distance'] : 0;
        $order->payment_status = $request->type == 'wallet'?'paid':'unpaid';
        $order->order_status = $request->type == 'wallet'?'confirmed':'pending';
        $order->order_type = 'delivery';
        $order->payment_method = $request->type;
        $order->store_id = $store->id;
        $order->module_id = $store->module_id;
        $order->user_id = $request->user_id;
        $order->dm_vehicle_id = $vehicle_id;
        $order->delivery_charge = isset($address)?$address['delivery_fee']+$extra_charges:0;
        $order->original_delivery_charge = isset($address)?$address['delivery_fee']+$extra_charges:0;
        $order->delivery_address = isset($address)?json_encode($address):null;
        $order->checked = 1;
        $order->zone_id = $store->zone_id;
        $order->schedule_at = now();
        $order->created_at = now();
        $order->updated_at = now();
        $order->otp = rand(1000, 9999);


        $additionalCharges = [];
        $settings = BusinessSetting::whereIn('key', [
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ])->pluck('value', 'key');

        $additional_charge_status  = $settings['additional_charge_status'] ?? null;
        $additional_charge         = $settings['additional_charge'] ?? null;

        // if ($additional_charge_status == 1) {
        //     $additionalCharges['tax_on_additional_charge'] = $additional_charge ?? 0;
        // }

        $order_details = $this->makePosOrderDetails($cart, null, $store);

        if (data_get($order_details, 'status_code') === 403) {
            DB::rollBack();
            return response()->json([
                'errors' => [
                    ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                ]
            ], data_get($order_details, 'status_code'));
        }

        $total_addon_price = $order_details['total_addon_price'];
        $product_price = $order_details['product_price'];
        $store_discount_amount = $order_details['store_discount_amount'];
        $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
        $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
        $product_data = $order_details['product_data'];
        $order_details = $order_details['order_details'];

        $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount;
        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount;

        $order->flash_admin_discount_amount = round($flash_sale_admin_discount_amount, config('round_up_to_digit'));
        $order->flash_store_discount_amount = round($flash_sale_vendor_discount_amount, config('round_up_to_digit'));
        $finalCalculatedTax =  Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);

        $tax_amount = $finalCalculatedTax['tax_amount'];
        $tax_status = $finalCalculatedTax['tax_status'];
        $taxMap = $finalCalculatedTax['taxMap'];
        $orderTaxIds = data_get($finalCalculatedTax ,'taxData.orderTaxIds',[] );
        $taxType=  data_get($finalCalculatedTax ,'taxType');
        $order->tax_type = $taxType;

        $order->tax_status = $tax_status;

        try {
            $order->store_discount_amount= $store_discount_amount;
            $order->tax_percentage = 0;
            $order->total_tax_amount = $tax_amount;
            $order->order_amount = $total_price + $tax_amount + $order->delivery_charge;
            $order->adjusment = $request->amount - ($total_price + $tax_amount + $order->delivery_charge);
            $order->payment_method = $request->type == 'wallet'?'wallet':'cash_on_delivery';

            $max_cod_order_amount = BusinessSetting::where('key', 'max_cod_order_amount')->first();
            $max_cod_order_amount_value=  $max_cod_order_amount ? $max_cod_order_amount->value : 0;
            if( $max_cod_order_amount_value > 0 && $order->payment_method == 'cash_on_delivery' && $order->order_amount > $max_cod_order_amount_value){
            Toastr::error(translate('messages.You can not Order more then ').$max_cod_order_amount_value .Helpers::currency_symbol().' '. translate('messages.on COD order.')  );
            return back()->withInput()->with('customer', $customer);
            }

            if($request->type == 'wallet'){
                if($request->user_id){

                    if($customer->wallet_balance < $order->order_amount){
                        Toastr::error(translate('messages.insufficient_wallet_balance'));
                        return back()->withInput()->with('customer', $customer);
                    }else{
                        CustomerLogic::create_wallet_transaction($order->user_id, $order->order_amount, 'order_place', $order->id);

                        if (Helpers::getNotificationStatusData('customer','customer_pos_order_wallet_notification','push_notification_status') && $customer?->cm_firebase_token && $customer?->cm_firebase_token != '@' )  {
                            $notification_data = [
                                'title' => Helpers::format_currency($order->order_amount).' '. translate('amount is debited'),
                                'description' =>  Helpers::format_currency($order->order_amount).' '. translate('has been debited from your wallet balance for POS order ID') .' '.$order->id,
                                'order_id' => $order->id,
                                'image' => '',
                                'type' => 'add_fund',
                            ];
                            Helpers::send_push_notif_to_device($customer->cm_firebase_token, $notification_data);
                            DB::table('user_notifications')->insert([
                                'data' => json_encode($notification_data),
                                'user_id' => $order->user_id,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }

                    }
                }else{
                    Toastr::error(translate('messages.no_customer_selected'));
                    return back()->withInput()->with('customer', $customer);
                }
            };
            $order->save();

            if ($request->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $order_details[$key]['order_id'] = $order->id;

                    if ($item['item_id']) {
                        $item_id = $item['item_id'];
                    } else {
                        $item_id = $item['item_campaign_id'];
                    }
                    $index = $taxMapCollection->search(function ($tax) use ($item_id) {
                        return $tax['product_id'] == $item_id;
                    });
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                OrderDetail::insert($order_details);
                if (count($orderTaxIds)) {
                    \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                        orderId: $order->id,
                        orderTaxIds: $orderTaxIds,
                    );
                }
                if (count($product_data) > 0) {
                    foreach ($product_data as $item) {
                        ProductLogic::update_stock($item['item'], $item['quantity'], $item['variant'])->save();
                        ProductLogic::update_flash_stock($item['item'], $item['quantity'])?->save();
                    }
                }
                $store->increment('total_order');
            }

            session()->forget('cart');
            session()->forget('tax_amount');
            session()->forget('tax_include');
            session()->forget('address');
            session()->forget('cart_product_ids');
            session(['last_order' => $order->id]);
            Helpers::send_order_notification($order);

            //PlaceOrderMail
            try{
                if($order->order_status == 'pending' && config('mail.status') && Helpers::get_mail_status('place_order_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_order_notification','mail_status'))
                {
                    Mail::to($order->customer->email)->send(new PlaceOrder($order->id));
                }
                if ($order->order_status == 'pending' && config('order_delivery_verification') == 1 && Helpers::get_mail_status('order_verification_mail_status_user') == '1' && Helpers::getNotificationStatusData('customer','customer_delivery_verification','mail_status')) {
                    Mail::to($order->customer->email)->send(new OrderVerificationMail($order->otp,$order->customer->f_name));
                }
            }catch (\Exception $ex) {
                info($ex->getMessage());
            }
            //PlaceOrderMail end
            Toastr::success(translate('messages.order_placed_successfully'));
            if ($store?->is_valid_subscription && $store_sub->max_order != "unlimited" && $store_sub->max_order > 0 ) {
                $store_sub->decrement('max_order' , 1);
            }
            return back();
        } catch (\Exception $e) {
            info(['Admin pos order error_____',$e]);
        }
        Toastr::warning(translate('messages.failed_to_place_order'));
        return back()->withInput()->with('customer', $customer);
    }


    public function generate_invoice($id)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos.invoice', compact('order'))->render(),
        ]);
    }

    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'unique:users',
        ]);
        User::create([
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => bcrypt('password'),
            'is_from_pos' => 1
        ]);

        try {
            if (config('mail.status') && $request->email && Helpers::get_mail_status('pos_registration_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_pos_registration','mail_status')) {
                Mail::to($request->email)->send(new \App\Mail\CustomerRegistrationPOS($request->f_name . ' ' . $request->l_name,$request['email'],'password'));
                Toastr::success(translate('mail_sent_to_the_user'));
            }
        } catch (\Exception $ex) {
            info($ex->getMessage());
        }
        Toastr::success(translate('customer_added_successfully'));
        return back();
    }

    public function extra_charge(Request $request)
    {
        $distance_data = $request->distancMileResult ?? 1;
        $self_delivery_status = $request->self_delivery_status;
        $extra_charges = 0;

        if($self_delivery_status != 1){

            $data=  DMVehicle::where(function($query)use($distance_data) {
                    $query->where('starting_coverage_area','<=' , $distance_data )->where('maximum_coverage_area','>=', $distance_data);
                })
                ->orWhere(function ($query) use ($distance_data) {
                    $query->where('starting_coverage_area', '>=', $distance_data);
                })
                ->active()
                ->orderBy('starting_coverage_area')->first();

                $extra_charges = (float) (isset($data) ? $data->extra_charges  : 0);
        }
            return response()->json($extra_charges,200);
    }

    public function getUserData(Request $request){
        if($request->customer_id){
            $user= User::where('id', $request->customer_id)->first();
            if ($user) {
                $user = [
                    'id' => $user->id,
                    'customer_name' => $user->f_name . ' ' . $user->l_name,
                    'customer_phone' => $user->phone,
                    'customer_wallet' => Helpers::format_currency($user->wallet_balance),
                    'customer_image' => $user->image_full_url,
                ];
                }
            return response()->json($user,200);
        }
        return response()->json([],200);
    }
    //worksaar start

	public function calculateDelivery(Request $request)
    {
        $request->validate([
            'store_code' => 'required|exists:stores,store_code',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        $store = Store::where('store_code', $request->store_code)->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        if (!$this->isLocationInZone($request->latitude, $request->longitude, $store->zone_id)) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.out_of_coverage')
            ], 400);
        }

        $address = $this->getAddressFromCoordinates($request->latitude, $request->longitude);

        $distanceData = $this->calculateDistance($store, $request->latitude, $request->longitude);
        $distanceMeter = $distanceData['distance_meters'];
        $distanceKm = round($distanceMeter / 1000, 2);

        $deliveryConfig = $this->getDeliveryConfiguration($store);

        $extraCharge = $this->calculateExtraCharge($distanceKm, $deliveryConfig['self_delivery_status']);

        $perKmShippingCharge = $deliveryConfig['per_km_shipping_charge'];
        $minimumShippingCharge = $deliveryConfig['minimum_shipping_charge'];
        $maximumShippingCharge = $deliveryConfig['maximum_shipping_charge'];

        $deliveryAmount = $this->calculateTieredDeliveryFee($distanceKm);

        $deliveryAmount += $extraCharge;
        if ($maximumShippingCharge > 0 && $deliveryAmount > $maximumShippingCharge) {
            $deliveryAmount = $maximumShippingCharge;
        }

        $deliveryCharge = round($deliveryAmount, 2);

        if ($deliveryCharge > 1000) {
            $deliveryCharge = $deliveryCharge / 1000;
        }
        $deliveryCharge = round($deliveryCharge, 2);

        $localCurrency = LocalCurrencyConversion::first();
        $localCurrencyRate = $localCurrency ? $localCurrency->local_rate : 1;

        $localCurrencyDeliveryFees = $deliveryCharge * $localCurrencyRate;
        $localCurrencyDeliveryFees = round($localCurrencyDeliveryFees, 2);

        $distanceKm = round($distanceKm, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => (float)$request->latitude,
                'longitude' => (float)$request->longitude,
                'address' => $address,
                'distance' => (float)$distanceKm,
                'delivery_fee' => (float)$deliveryCharge,
                'extra_charge' => (float)$extraCharge,
                'local_currency_rate' => (float)$localCurrencyRate,
                'local_currency_delivery_fees' => (float)$localCurrencyDeliveryFees,
                'currency_symbol' => \App\CentralLogics\Helpers::currency_symbol()
            ]
        ]);
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
     * Check if user location is inside store zone polygon
     */
    private function isLocationInZone($latitude, $longitude, $zoneId)
    {
        $zone = Zone::find($zoneId);
        if (!$zone || !$zone->coordinates) return false;

        $coordinates = $this->parsePolygonCoordinates($zone->coordinates);
        if (!is_array($coordinates) || empty($coordinates)) return false;

        return $this->pointInPolygon($latitude, $longitude, $coordinates);
    }

    /**
     * Parse POLYGON WKT to array
     */
    private function parsePolygonCoordinates($wkt)
    {
        $wkt = str_replace(['POLYGON((', '))'], '', $wkt);
        $points = explode(',', $wkt);
        $coordinates = [];

        foreach ($points as $point) {
            $coords = preg_split('/\s+/', trim($point));
            if (count($coords) == 2) {
                $coordinates[] = [
                    'lat' => (float)$coords[1],
                    'lng' => (float)$coords[0],
                ];
            }
        }
        return $coordinates;
    }

    /**
     * Point in polygon algorithm
     */
    private function pointInPolygon($latitude, $longitude, $polygon)
    {
        $vertices_x = array_column($polygon, 'lng');
        $vertices_y = array_column($polygon, 'lat');
        $points_polygon = count($vertices_x) - 1;

        $c = false;
        for ($i = 0, $j = $points_polygon; $i < $points_polygon; $j = $i++) {
            if ((($vertices_y[$i] > $latitude) != ($vertices_y[$j] > $latitude)) &&
                ($longitude < ($vertices_x[$j] - $vertices_x[$i]) * ($latitude - $vertices_y[$i]) / ($vertices_y[$j] - $vertices_y[$i]) + $vertices_x[$i])) {
                $c = !$c;
            }
        }

        return $c;
    }

    /**
     * Get address from coordinates using Google Geocoding API
     */
    private function getAddressFromCoordinates($latitude, $longitude)
    {
        $apiKey = env('GOOGLE_MAP_KEY');
        $url = "https://maps.googleapis.com/maps/api/geocode/json";

        $response = Http::get($url, [
            'latlng' => "$latitude,$longitude",
            'key' => $apiKey
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['results'][0]['formatted_address'])) {
                return $data['results'][0]['formatted_address'];
            }
        }
        return 'Address not found';
    }

    /**
     * Calculate distance using Google DistanceMatrix API or fallback Haversine
     */
    private function calculateDistance($store, $destinationLat, $destinationLng)
    {
        $apiKey = env('GOOGLE_MAP_KEY');
        $origin = $store->latitude . ',' . $store->longitude;
        $destination = "$destinationLat,$destinationLng";

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

        // Fallback Haversine: swap lat/lng if needed
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

        if ($store->sub_self_delivery) {
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
    //worksaar end
}
