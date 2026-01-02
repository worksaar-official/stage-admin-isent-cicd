<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\Helpers;

class ItemCreateController extends Controller
{
    /**
     * Create a new item
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'store_code' => 'required|exists:stores,store_code',
            'category_id' => 'nullable|exists:categories,id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:0,1',
            'veg' => 'nullable|in:0,1',
            'module_id' => 'required|exists:modules,id',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'unit_id' => 'nullable|exists:units,id',
            'stock' => 'nullable|integer|min:0',
            'maximum_cart_quantity' => 'nullable|integer|min:1',
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

            // Get store by store_code
            $store = Store::where('store_code', $request->store_code)->first();

            $item = new Item();
            $item->name = $request->name;
            $item->description = $request->description;
            $item->price = $request->price;
            $item->store_id = $store->id;

            // Set category_id to null if not provided (default behavior)
            $item->category_id = $request->category_id ?? null;

            $item->discount = $request->discount ?? 0;
            $item->tax = $request->tax ?? 0;
            $item->status = 1;
            $item->veg = $request->veg ?? 0;
            $item->module_id = $request->module_id;
            $item->image = $request->image;
            $item->images = $request->images ?? [];
            $item->unit_id = $request->unit_id;
            $item->stock = $request->stock ?? 0;
            $item->maximum_cart_quantity = $request->maximum_cart_quantity;
            $item->is_approved = 1;
            $item->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => $item
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Item creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create item. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
