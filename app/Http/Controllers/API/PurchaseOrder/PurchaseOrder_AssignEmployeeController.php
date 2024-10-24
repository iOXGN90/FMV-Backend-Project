<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Product;
use App\Models\DeliveryProduct;
use App\Http\Controllers\API\BaseController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseOrder_AssignEmployeeController extends BaseController
{
    // Assign employee to a purchase order
    public function assign_employee(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'user_id' => 'required|exists:users,id',
            'product_details' => 'required|array',
            'product_details.*.product_id' => 'required|exists:products,id',
            'product_details.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $purchaseOrder = PurchaseOrder::find($request->input('purchase_order_id'));
        $employee = User::find($request->input('user_id'));

        if (!$purchaseOrder) {
            return response()->json(['error' => 'Purchase order not found'], 404);
        }

        // if (!$employee) {
        //     return response()->json(['error' => 'Employee not found'], 404);
        // }

        DB::beginTransaction();
        try {
            $deliveryDetails = []; // To hold the details for the response
            $exceedingProducts = []; // To hold products with exceeding quantities

            // Calculate the next delivery number for this purchase order
            $currentMaxDeliveryNo = Delivery::where('purchase_order_id', $purchaseOrder->id)->max('delivery_no');
            $nextDeliveryNo = $currentMaxDeliveryNo ? $currentMaxDeliveryNo + 1 : 1;

            // Create the delivery record (without product details)
            $delivery = Delivery::create([
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $employee->id,
                'delivery_no' => $nextDeliveryNo,
                'status' => 'OD',
                'notes' => $request->input('notes', '')
            ]);

            // Use foreach to loop through product details
            foreach ($request->input('product_details') as $productDetailData) {
                $productDetail = $purchaseOrder->productDetails()
                    ->where('product_id', $productDetailData['product_id'])
                    ->first();

                if (!$productDetail) {
                    throw new \Exception('Product not found in purchase order');
                }

                // Get the product details and check remaining quantity
                $product = Product::find($productDetailData['product_id']);

                // Calculate the delivered quantity
                $deliveredQuantity = DeliveryProduct::whereHas('delivery', function ($query) use ($purchaseOrder) {
                    $query->where('purchase_order_id', $purchaseOrder->id);
                })
                ->where('product_details_id', $productDetailData['product_id']) // match product_details_id
                ->sum('quantity');

                $remainingQuantity = $productDetail->quantity - $deliveredQuantity;

                // Use foreach to check if quantity exceeds remaining quantity
                if ($productDetailData['quantity'] > $remainingQuantity) {
                    $exceedingProducts[] = [
                        'product_name' => $product->product_name,
                        'inputted' => $productDetailData['quantity'],
                        'total_needed' => $remainingQuantity
                    ];
                    continue; // Skip to next product if it exceeds the quantity
                }

                // Check inventory stock availability
                if ($product->quantity < $productDetailData['quantity']) {
                    throw new \Exception('Not enough product available in the inventory for product: ' . $product->product_name);
                }

                // Deduct the product quantity from inventory
                $product->quantity -= $productDetailData['quantity'];
                $product->save();

                // Insert into delivery_products table
                DeliveryProduct::create([
                    'delivery_id' => $delivery->id,
                    'product_details_id' => $productDetailData['product_id'],
                    'quantity' => $productDetailData['quantity'],
                ]);

                // $actualRemainingQuantity = $remainingQuantity - ['']
                // Add delivery details for this product
                $deliveryDetails[] = [
                    'delivery_no' => $delivery->delivery_no,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'quantity_to_delivered' => $productDetailData['quantity'],
                    'quantity_left_to_deliver' => $remainingQuantity,
                    'current_quantity' => $product->quantity
                ];
            }

            // If there are exceeding products, return an error response
            if (count($exceedingProducts) > 0) {
                DB::rollBack(); // Roll back the transaction
                return response()->json([
                    'error' => 'Product(s) Exceeded the value: ',
                    'exceeding_products' => $exceedingProducts
                ], 400);
            }

            DB::commit();

            // Return the response with delivery details
            return response()->json([
                'message' => 'Employee assigned and product quantity updated successfully',
                'delivery_id' => $delivery->id,
                'delivery_man_name' => $employee->name,
                'purchase_order_id' => $purchaseOrder->id,
                'status' => 'OD',
                'delivery_details' => $deliveryDetails
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Assigning the employee: ' . $e->getMessage()], 500);
        }
    }



    // Remove assigned employee from a delivery
    public function remove_assigned_employee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_id' => 'required|exists:deliveries,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $admin = auth()->user();

        // Check if the user is an admin
        if ($admin->user_type_id != 1) {
            return response()->json(['error' => 'User does not have the correct permissions to remove an assignment'], 403);
        }

        $delivery = Delivery::find($request->input('delivery_id'));

        if (is_null($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        DB::beginTransaction();
        try {
            $delivery->delete();
            DB::commit();
            return response()->json(['message' => 'Employee unassigned successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while removing the assignment: ' . $e->getMessage()], 500);
        }
    }
}
