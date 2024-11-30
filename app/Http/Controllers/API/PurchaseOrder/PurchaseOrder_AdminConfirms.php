<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PurchaseOrder_AdminConfirms extends BaseController
{
    public function update_to_success(Request $request, $delivery_id)
    {
        DB::beginTransaction();
        try {
            // Step 1: Find the delivery by its ID
            $delivery = Delivery::with('deliveryProducts.returns')->find($delivery_id);
            if (is_null($delivery)) {
                return response()->json(['message' => 'Delivery not found.'], 404);
            }

            // Set the delivery status to Success directly
            $delivery->status = 'S';
            $delivery->save();

            // Log the update
            if ($delivery->wasChanged('status')) {
                Log::info("Delivery ID {$delivery->id} status updated to 'S'");
            } else {
                Log::warning("Delivery ID {$delivery->id} status failed to update");
            }

            // Step 2: Get the associated purchase order
            $purchaseOrder = PurchaseOrder::find($delivery->purchase_order_id);
            if (is_null($purchaseOrder)) {
                return response()->json(['message' => 'Associated Purchase Order not found.'], 404);
            }

            // Step 3: Calculate the total ordered quantity from product details
            $totalOrderedQuantity = ProductDetail::where('purchase_order_id', $purchaseOrder->id)->sum('quantity');

            // Step 4: Calculate the total delivered quantity, considering all related deliveries
            $deliveries = Delivery::with('deliveryProducts.returns')->where('purchase_order_id', $purchaseOrder->id)->get();
            $totalDeliveredQuantity = 0;

            foreach ($deliveries as $currentDelivery) {
                foreach ($currentDelivery->deliveryProducts as $deliveryProduct) {
                    $hasDamages = $deliveryProduct->no_of_damages > 0;
                    $returns = $deliveryProduct->returns;

                    // Step 4b: Conditions for adding to totalDeliveredQuantity
                    if ($deliveryProduct->no_of_damages == 0 && $currentDelivery->status == 'S') {
                        // If no damages and the delivery status is Success
                        $totalDeliveredQuantity += $deliveryProduct->quantity;
                    } elseif ($hasDamages) {
                        if ($returns->where('status', 'S')->count() > 0) {
                            // If there are damages but returns have been marked as Success
                            $totalDeliveredQuantity += $deliveryProduct->quantity;
                        } else {
                            // If there are damages and the returns are still pending, throw an error
                            DB::rollBack();
                            return response()->json([
                                'message' => "Delivery with ID {$currentDelivery->id} has damaged products that are not yet refunded. Please complete the refund process before finalizing.",
                            ], 400);
                        }
                    }
                }
            }

            // Step 5: Compare total ordered quantity and total delivered quantity
            $remainingQuantity = $totalOrderedQuantity - $totalDeliveredQuantity;

            if ($remainingQuantity == 0) {
                // If all products are delivered, set Purchase Order status to Success
                $purchaseOrder->status = 'S';
                $purchaseOrder->save();
            } else {
                // If not all products are delivered, set the Purchase Order status to Pending
                $purchaseOrder->status = 'P';
                $purchaseOrder->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Delivery and Purchase Order status updated successfully.',
                'delivery' => $delivery,
                'purchase_order' => $purchaseOrder,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating delivery and purchase order: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the delivery and purchase order.'], 500);
        }
    }
}
