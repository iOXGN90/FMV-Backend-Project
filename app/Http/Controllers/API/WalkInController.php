<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\Address;
use App\Models\ProductDetail;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalkInController extends BaseController
{
    // Start View WalkIn
    public function index_walk_in()
    {
        // Filter and get all walk-in orders where sale_type_id is 2
        $walkInOrders = PurchaseOrder::with(['address', 'productDetails.product'])
            ->where('sale_type_id', 2)
            ->get();

        return response()->json($walkInOrders);
    }
    // End View WalkIn

    // Store a new walk-in order
    public function create_walk_in(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'customer_name' => 'sometimes|string|max:255',
            'sale_type_id' => 'required|exists:sale_types,id',
            'product_details' => 'required|array',
            'product_details.*.product_id' => 'required|exists:products,id',
            'product_details.*.price' => 'required|numeric',
            'product_details.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check if the sale type is walk-in; value=2
        //? 1 = delivery | 2 = walk in
        if ($request->input('sale_type_id') != 2) {
            return response()->json(['error' => 'Invalid sale type. Walk-in sales can only be created for sale type 2 (walk-in).'], 403);
        }

        DB::beginTransaction();
        try {
            // Default address for the shop
            $addressData = $request->input('address', [
                'street' => 'Masterson',
                'barangay' => 'Lumbia',
                'zip_code' => 9000,
                'province' => 'Misamis Oriental',
            ]);

            $address = Address::create($addressData);

            // Create the walk-in purchase order
            $purchaseOrderData = $request->only(['user_id', 'sale_type_id']);
            $purchaseOrderData['customer_name'] = $request->input('customer_name', ''); // Default to empty string if not provided
            $purchaseOrderData['status'] = 'S'; // Automatically set status to success
            $purchaseOrderData['address_id'] = $address->id; // Link address to the purchase order
            $purchaseOrder = PurchaseOrder::create($purchaseOrderData);

            // Create the product details and update the product quantities
            foreach ($request->input('product_details') as $productDetailData) {
                $productDetailData['purchase_order_id'] = $purchaseOrder->id;
                ProductDetail::create($productDetailData);

                // Update the product quantity
                $product = Product::find($productDetailData['product_id']);
                if ($product->quantity < $productDetailData['quantity']) {
                    throw new \Exception('Not enough product available');
                }
                $product->quantity -= $productDetailData['quantity'];
                $product->save();
            }

            DB::commit();
            return response()->json($purchaseOrder->load(['address', 'productDetails']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while creating the walk-in order: ' . $e->getMessage()], 500);
        }
    }
}
