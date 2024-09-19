<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\Address;
use App\Models\ProductDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


class PurchaseOrderController extends BaseController
{

    // Store a new purchase order with address and product details
    public function create_purchase_order_delivery(Request $request)
{
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|exists:users,id',
        'customer_name' => 'required|string|max:255',
        'status' => 'required|in:P,F,S',
        'sale_type_id' => 'required|exists:sale_types,id',
        'address.street' => 'required|string|max:255',
        'address.barangay' => 'required|string|max:255',
        'address.zip_code' => 'required|integer',
        'address.province' => 'required|string|max:255',
        'product_details' => 'required|array',
        'product_details.*.product_id' => 'required|exists:products,id',
        'product_details.*.price' => 'required|numeric',
        'product_details.*.quantity' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    // Check if the user has the correct user type
    $user = User::find($request->input('user_id'));
    if ($user->user_type_id != 1) {
        return response()->json(['error' => 'User does not have the correct permissions to create a delivery'], 403);
    }

    // Check if the sale type is delivery (1)
    if ($request->input('sale_type_id') != 1) {
        return response()->json(['error' => 'Invalid sale type. Deliveries can only be created for sale type 1 (delivery).'], 403);
    }

    DB::beginTransaction();
    try {
        // Create the address
        $address = Address::create($request->input('address'));

        // Create the purchase order
        $purchaseOrderData = $request->only(['user_id', 'customer_name', 'status', 'sale_type_id']);
        $purchaseOrderData['address_id'] = $address->id;
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

        // Format the response to match `index_purchase_order`
        $formattedOrder = [
            'id' => $purchaseOrder->id,
            'user_id' => $purchaseOrder->user_id,
            'address_id' => $purchaseOrder->address_id,
            'sale_type_id' => $purchaseOrder->sale_type_id,
            'customer_name' => $purchaseOrder->customer_name,
            'status' => $purchaseOrder->status,
            'created_at' => Carbon::parse($purchaseOrder->created_at)->format('l, M d, Y'), // Readable date format
            'address' => [
                'id' => $purchaseOrder->address->id,
                'street' => $purchaseOrder->address->street,
                'barangay' => $purchaseOrder->address->barangay,
                'zip_code' => $purchaseOrder->address->zip_code,
                'province' => $purchaseOrder->address->province,
                'created_at' => Carbon::parse($purchaseOrder->address->created_at)->format('l, M d, Y'), // Readable date format
            ],
            'product_details' => $purchaseOrder->productDetails->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product->product_name ?? 'N/A', // Include product name
                    'purchase_order_id' => $detail->purchase_order_id,
                    'price' => $detail->price,
                    'quantity' => $detail->quantity,
                ];
            }),
        ];

        return response()->json($formattedOrder, 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => 'Error occurred while creating the purchase order: ' . $e->getMessage()], 500);
    }
}

    // Update a specific purchase order with address and product details
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'status' => 'required|in:P,F,S',
            'sale_type_id' => 'required|exists:sale_types,id',
            'address.street' => 'sometimes|required|string|max:255',
            'address.barangay' => 'sometimes|required|string|max:255',
            'address.zip_code' => 'sometimes|required|integer',
            'address.province' => 'sometimes|required|string|max:255',
            'product_details' => 'sometimes|array',
            'product_details.*.product_id' => 'required_with:product_details|exists:products,id',
            'product_details.*.price' => 'required_with:product_details|numeric',
            'product_details.*.quantity' => 'required_with:product_details|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $purchaseOrder = PurchaseOrder::find($id);

        if (is_null($purchaseOrder)) {
            return response()->json(['message' => 'Purchase Order not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Restore the product quantities for the existing product details
            foreach ($purchaseOrder->productDetails as $existingProductDetail) {
                $product = Product::find($existingProductDetail->product_id);
                $product->quantity += $existingProductDetail->quantity;
                $product->save();
            }

            // Update the purchase order
            $purchaseOrder->update($request->only(['user_id', 'customer_name', 'status', 'sale_type_id']));

            // Update the address if provided
            if ($request->has('address')) {
                $purchaseOrder->address->update($request->input('address'));
            }

            // Update the product details if provided
            if ($request->has('product_details')) {
                // Delete existing product details
                $purchaseOrder->productDetails()->delete();

                // Create new product details and update the product quantities
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
            }

            DB::commit();
            return response()->json($purchaseOrder->load(['address', 'productDetails']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while updating the purchase order: ' . $e->getMessage()], 500);
        }
    }

    // Delete a specific purchase order by ID
    public function destroy($id)
    {
        $purchaseOrder = PurchaseOrder::find($id);

        if (is_null($purchaseOrder)) {
            return response()->json(['message' => 'Purchase Order not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Restore the product quantities for the existing product details
            foreach ($purchaseOrder->productDetails as $existingProductDetail) {
                $product = Product::find($existingProductDetail->product_id);
                $product->quantity += $existingProductDetail->quantity;
                $product->save();
            }

            // Delete related product details
            $purchaseOrder->productDetails()->delete();

            // Delete related address
            $purchaseOrder->address()->delete();

            // Delete the purchase order
            $purchaseOrder->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while deleting the purchase order: ' . $e->getMessage()], 500);
        }
    }

}
