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

            // Create the product details (no product quantity update)
            foreach ($request->input('product_details') as $productDetailData) {
                $productDetailData['purchase_order_id'] = $purchaseOrder->id;
                ProductDetail::create($productDetailData);
            }

            DB::commit();

            // Format the response to match `index_purchase_order`
            $formattedOrder = [
                'purchase_order_id' => $purchaseOrder->id,
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
