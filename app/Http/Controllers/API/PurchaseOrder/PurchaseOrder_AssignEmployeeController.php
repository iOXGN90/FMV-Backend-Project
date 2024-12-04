<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\Delivery;
use App\Models\ProductDetail;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Product;
use App\Models\DeliveryProduct;
use App\Http\Controllers\API\BaseController;
use Carbon\Carbon;

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
        'product_details.*.quantity' => 'integer|min:0', // Allow zero quantity
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $filteredProductDetails = array_filter($request->input('product_details'), function ($detail) {
        return $detail['quantity'] > 0; // Only keep products with quantity > 0
    });

    if (empty($filteredProductDetails)) {
        return response()->json(['error' => 'No valid product quantities provided'], 400);
    }

    $purchaseOrder = PurchaseOrder::find($request->input('purchase_order_id'));
    $employee = User::find($request->input('user_id'));

    if (!$purchaseOrder) {
        return response()->json(['error' => 'Purchase order not found'], 404);
    }

    DB::beginTransaction();
    try {
        $deliveryDetails = [];
        $exceedingProducts = [];

        // Calculate the next delivery number for this purchase order
        $currentMaxDeliveryNo = Delivery::where('purchase_order_id', $purchaseOrder->id)->max('delivery_no');
        $nextDeliveryNo = $currentMaxDeliveryNo ? $currentMaxDeliveryNo + 1 : 1;

        // Create the delivery record (without product details)
        $delivery = Delivery::create([
            'purchase_order_id' => $purchaseOrder->id,
            'user_id' => $employee->id,
            'delivery_no' => $nextDeliveryNo,
            'status' => 'OD',
            'created_at' => now(), // Use the helper now() for the current timestamp
            'notes' => $request->input('notes', '')
        ]);

        foreach ($filteredProductDetails as $productDetailData) {
            // Calculate the delivered quantity for the current product
            $deliveredQuantity = DeliveryProduct::where('delivery_id', $delivery->id)
                                                ->where('product_id', $productDetailData['product_id'])
                                                ->sum('quantity');

            $productDetail = ProductDetail::where('product_id', $productDetailData['product_id'])
                                         ->where('purchase_order_id', $purchaseOrder->id)
                                         ->first();

            if (!$productDetail) {
                throw new \Exception('Product not found in purchase order');
            }

            $remainingQuantity = $productDetail->quantity - $deliveredQuantity;

            if ($productDetailData['quantity'] > $remainingQuantity) {
                $exceedingProducts[] = [
                    'product_name' => $productDetail->product->product_name,
                    'inputted' => $productDetailData['quantity'],
                    'total_needed' => $remainingQuantity
                ];
                continue;
            }

            // Create a record in delivery_products
            DeliveryProduct::create([
                'delivery_id' => $delivery->id,
                'product_id' => $productDetail->product_id,
                'quantity' => $productDetailData['quantity'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Deduct the product quantity after delivery
            $product = Product::find($productDetail->product_id);
            if ($product) {
                $product->quantity -= $productDetailData['quantity'];
                $product->save();
            }

            $deliveryDetails[] = [
                'delivery_no' => $delivery->delivery_no,
                'product_id' => $productDetail->product_id,
                'product_name' => $productDetail->product->product_name,
                'quantity_delivered' => $productDetailData['quantity'],
                'quantity_left' => $remainingQuantity - $productDetailData['quantity'],
            ];
        }

        if (!empty($exceedingProducts)) {
            DB::rollBack();
            return response()->json([
                'error' => 'Product quantities exceed available stock',
                'exceeding_products' => $exceedingProducts
            ], 400);
        }

        DB::commit();
        return response()->json([
            'message' => 'Delivery successfully created with employee assigned',
            'delivery_id' => $delivery->id,
            'delivery_details' => $deliveryDetails
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => 'Assigning the employee failed: ' . $e->getMessage()], 500);
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
