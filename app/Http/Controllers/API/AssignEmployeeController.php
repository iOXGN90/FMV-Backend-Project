<?php

namespace App\Http\Controllers\API;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AssignEmployeeController extends BaseController
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

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check if the user being assigned is an employee
        if ($employee->user_type_id != 2) {
            return response()->json(['error' => 'The user being assigned must be an employee'], 403);
        }

        DB::beginTransaction();
        try {
            foreach ($request->input('product_details') as $productDetailData) {
                $productDetail = $purchaseOrder->productDetails()
                    ->where('product_id', $productDetailData['product_id'])
                    ->first();

                if (!$productDetail) {
                    throw new \Exception('Product not found in purchase order');
                }

                // Calculate the remaining quantity for this product
                $deliveredQuantity = Delivery::where('purchase_order_id', $purchaseOrder->id)
                    ->where('product_id', $productDetailData['product_id'])
                    ->sum('quantity');

                $remainingQuantity = $productDetail->quantity - $deliveredQuantity;

                if ($productDetailData['quantity'] > $remainingQuantity) {
                    throw new \Exception('Delivery quantity exceeds remaining quantity for product ID: ' . $productDetailData['product_id']);
                }

                // Deduct the quantity from the product's available quantity in the inventory
                $product = Product::find($productDetailData['product_id']);
                if ($product->quantity < $productDetailData['quantity']) {
                    throw new \Exception('Not enough product available in the inventory');
                }
                $product->quantity -= $productDetailData['quantity'];
                $product->save();

                // Calculate the next delivery number for this purchase order
                $currentMaxDeliveryNo = Delivery::where('purchase_order_id', $purchaseOrder->id)->max('delivery_no');
                $nextDeliveryNo = $currentMaxDeliveryNo ? $currentMaxDeliveryNo + 1 : 1;

                // Create the delivery record
                $deliveryData = [
                    'purchase_order_id' => $purchaseOrder->id,
                    'user_id' => $employee->id,
                    'product_id' => $productDetailData['product_id'],
                    'quantity' => $productDetailData['quantity'],
                    'delivery_no' => $nextDeliveryNo, // Sequential delivery number
                    'status' => 'OD', //OD = On Delivery
                    'notes' => $request->input('notes', '')
                ];

                Delivery::create($deliveryData);
            }

            DB::commit();
            return response()->json(['message' => 'Employee assigned and product quantity updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while assigning the employee: ' . $e->getMessage()], 500);
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
