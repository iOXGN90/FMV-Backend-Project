<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Http\Controllers\API\BaseController;

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
use Illuminate\Support\Facades\Log;


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
            'address.region' => 'required|string|max:255',  // Add this line for region validation
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

        // Validate product quantities - ensure the quantity does not exceed available stock
        foreach ($request->input('product_details') as $productDetail) {
            $product = Product::find($productDetail['product_id']);
            if (!$product) {
                return response()->json(['error' => "Product with ID {$productDetail['product_id']} not found."], 404);
            }

            // Check if requested quantity exceeds available stock
            if ($productDetail['quantity'] > $product->quantity) {
                return response()->json([
                    'error' => "Insufficient stock for product '{$product->product_name}' (ID: {$product->id}).
                    Requested: {$productDetail['quantity']}, Available: {$product->quantity}.",
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create the address with the region field
            $addressData = $request->input('address');
            $address = Address::create($addressData);  // The Address model should be able to accept 'region'

            // Create the purchase order
            $purchaseOrderData = $request->only(['user_id', 'customer_name', 'status', 'sale_type_id']);
            $purchaseOrderData['address_id'] = $address->id;
            $purchaseOrder = PurchaseOrder::create($purchaseOrderData);

            // Create product details (no stock deduction here)
            foreach ($request->input('product_details') as $productDetailData) {
                $productDetailData['purchase_order_id'] = $purchaseOrder->id;
                ProductDetail::create($productDetailData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Purchase order created successfully!',
                'purchase_order_id' => $purchaseOrder->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while creating the purchase order: ' . $e->getMessage()], 500);
        }
    }


    public function cancelPurchaseOrder($purchaseOrderId)
    {
        try {
            // Log start of cancellation process
            Log::info('Attempting to cancel purchase order.', ['purchaseOrderId' => $purchaseOrderId]);

            // Find the purchase order
            $purchaseOrder = PurchaseOrder::with('delivery.deliveryProducts')->find($purchaseOrderId);

            if (!$purchaseOrder) {
                Log::error('Purchase Order not found.', ['purchaseOrderId' => $purchaseOrderId]);
                return response()->json(['message' => 'Purchase Order not found'], 404);
            }

            // Ensure the purchase order is not completed successfully yet
            if ($purchaseOrder->status === 'S') {
                Log::error('Cannot cancel successfully completed purchase order.', ['purchaseOrderId' => $purchaseOrderId]);
                return response()->json(['message' => 'Cannot cancel a successfully completed purchase order'], 400);
            }

            // Iterate through each delivery associated with the purchase order
            foreach ($purchaseOrder->delivery as $delivery) {
                Log::info('Processing delivery.', ['deliveryId' => $delivery->id]);

                foreach ($delivery->deliveryProducts as $deliveryProduct) {
                    Log::info('Processing delivery product.', ['deliveryProductId' => $deliveryProduct->id]);

                    // Calculate the intact quantity to be returned to the product's stock
                    $intactQuantity = $deliveryProduct->quantity - $deliveryProduct->no_of_damages;

                    // Update the product's quantity
                    $product = Product::find($deliveryProduct->product_id);
                    if ($product) {
                        $product->quantity += $intactQuantity;
                        $product->save();
                        Log::info('Updating product quantity.', ['productId' => $product->id, 'quantity' => $product->quantity]);
                    }
                }
            }

            // Update the purchase order status to "Failed" or "Cancelled"
            $purchaseOrder->status = 'F'; // Assuming 'F' represents "Failed" or "Cancelled"
            $purchaseOrder->save();

            return response()->json(['message' => 'Purchase order canceled successfully. Products restocked.'], 200);

        } catch (\Exception $e) {
            Log::error('Error in cancelPurchaseOrder:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Something went wrong while canceling the purchase order.'], 500);
        }
    }


    public function updatePurchaseOrderDate(Request $request, $id)
    {
        Log::info('PUT request received for updating Purchase Order Date.', [
            'request_data' => $request->all(),
            'purchase_order_id' => $id
        ]);

        $validator = Validator::make($request->all(), [
            'created_at' => 'required|date_format:Y-m-d H:i:s'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for updating Purchase Order Date.', [
                'errors' => $validator->errors()
            ]);

            return response()->json($validator->errors(), 400);
        }

        $purchaseOrder = PurchaseOrder::find($id);

        if (!$purchaseOrder) {
            Log::error('Purchase Order not found.', ['purchase_order_id' => $id]);
            return response()->json(['message' => 'Purchase Order not found'], 404);
        }

        $purchaseOrder->created_at = $request->input('created_at');
        $purchaseOrder->save();

        Log::info('Purchase Order date updated successfully.', [
            'purchase_order_id' => $purchaseOrder->id,
            'new_created_at' => $purchaseOrder->created_at
        ]);

        return response()->json(['message' => 'Purchase Order date updated successfully.'], 200);
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
