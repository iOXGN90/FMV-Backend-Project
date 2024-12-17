<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\ProductDetail;
use App\Models\PurchaseOrder;



class PurchaseOrder_Edit extends BaseController
{
    public function getPurchaseOrderDetails($purchase_order_id)
    {
        // Validate the purchase_order_id
        if (!is_numeric($purchase_order_id)) {
            return response()->json(['error' => 'Invalid Purchase Order ID'], 400);
        }

        // Fetch purchase order details including customer name and address
        $purchaseOrder = DB::table('purchase_orders')
            ->join('addresses', 'purchase_orders.address_id', '=', 'addresses.id')
            ->where('purchase_orders.id', $purchase_order_id)
            ->select(
                'purchase_orders.customer_name',
                'addresses.street',
                'addresses.barangay',
                'addresses.city',
                'addresses.province',
                'addresses.zip_code',
                'addresses.region'
            )
            ->first();

        if (!$purchaseOrder) {
            return response()->json(['error' => 'Purchase order not found'], 404);
        }

        // Fetch product details including original price and category name
        $productDetails = DB::table('product_details')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('product_details.purchase_order_id', $purchase_order_id)
            ->select(
                'products.id as product_id',
                'products.product_name',
                'categories.category_name',
                'product_details.quantity as total_quantity',
                'product_details.price as custom_price',
                'products.original_price'
            )
            ->get();

        // Fetch delivery products for the given purchase order
        $deliveryProducts = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.purchase_order_id', $purchase_order_id)
            ->select(
                'delivery_products.product_id',
                DB::raw('SUM(delivery_products.quantity) as delivered_quantity')
            )
            ->groupBy('delivery_products.product_id')
            ->get();

        // Map delivery quantities to products
        $deliveryMap = $deliveryProducts->pluck('delivered_quantity', 'product_id');

        // Combine product details with delivery data and add isEditable field
        $result = $productDetails->map(function ($product) use ($deliveryMap) {
            $deliveredQuantity = $deliveryMap->get($product->product_id, 0); // Default to 0 if no delivery
            $dataComparison = $product->total_quantity - $deliveredQuantity; // Calculate remaining quantity

            $isEditable = $dataComparison == $product->total_quantity; // Editable if no delivery has been made yet

            return (object)[
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'category_name' => $product->category_name,
                'total_quantity' => $product->total_quantity,
                'delivered_quantity' => $deliveredQuantity,
                'custom_price' => $product->custom_price, // Custom price from product_details
                'original_price' => $product->original_price, // Original price from products
                'isEditable' => $isEditable
            ];
        });

        // Return the response
        return response()->json([
            'purchase_order_id' => $purchase_order_id,
            'customer_name' => $purchaseOrder->customer_name,
            'address' => [
                'street' => $purchaseOrder->street,
                'barangay' => $purchaseOrder->barangay,
                'city' => $purchaseOrder->city,
                'province' => $purchaseOrder->province,
                'zip_code' => $purchaseOrder->zip_code,
                'region' => $purchaseOrder->region, // Include region in the address data
            ],
            'products' => $result
        ]);
    }


    public function update_purchase_order(Request $request, $purchase_order_id)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'status' => 'required|in:P,F,S',
            'address.street' => 'required|string|max:255',
            'address.barangay' => 'required|string|max:255',
            'address.zip_code' => 'required|string',  // Ensure zip_code is a string
            'address.province' => 'required|string|max:255',
            'address.region' => 'required|string|max:255',
            'product_details' => 'required|array',
            'product_details.*.product_id' => 'required|exists:products,id',
            'product_details.*.price' => 'required|numeric',
            'product_details.*.quantity' => 'required|integer|min:1',
            'removed_products' => 'array',
            'removed_products.*' => 'exists:product_details,product_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        DB::beginTransaction();
        try {
            // Find the purchase order
            $purchaseOrder = PurchaseOrder::find($purchase_order_id);
            if (!$purchaseOrder) {
                return response()->json(['error' => 'Purchase order not found'], 404);
            }

            // Update the purchase order details
            $purchaseOrder->update([
                'customer_name' => $request->input('customer_name'),
                'status' => $request->input('status'),
            ]);

            // Update the address with the region field
            $addressData = $request->input('address');
            $purchaseOrder->address->update($addressData);

            // Update product details
            $productDetails = $request->input('product_details');
            $existingProductDetails = $purchaseOrder->productDetails;

            foreach ($productDetails as $detail) {
                $productDetail = $existingProductDetails->firstWhere('product_id', $detail['product_id']);
                if ($productDetail) {
                    $productDetail->update([
                        'price' => $detail['price'],
                        'quantity' => $detail['quantity'],
                    ]);
                } else {
                    ProductDetail::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $detail['product_id'],
                        'price' => $detail['price'],
                        'quantity' => $detail['quantity'],
                    ]);
                }
            }

            // Handle removed products
            $removedProducts = $request->input('removed_products', []);
            if (!empty($removedProducts)) {
                ProductDetail::where('purchase_order_id', $purchase_order_id)
                    ->whereIn('product_id', $removedProducts)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Purchase order updated successfully',
                'purchase_order' => $purchaseOrder->load(['address', 'productDetails.product']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while updating the purchase order: ' . $e->getMessage()], 500);
        }
    }



}
