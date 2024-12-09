<?php

namespace App\Http\Controllers\API\Deliveries;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\Delivery;
use Carbon\Carbon;


class Deliveries_View extends BaseController
{

    public function index(Request $request)
    {
        $query = Delivery::query();

        // Apply status filter only if it's not empty
        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        // Eager load purchase order details, delivery products, and returns
        $deliveries = $query->with(['purchaseOrder', 'deliveryProducts.returns'])->paginate(20);

        // Format the deliveries using collect and map
        $formattedDeliveries = collect($deliveries->items())->map(function ($delivery) {
            // Get the statuses of returns from all delivery products if available
            $returnStatuses = $delivery->deliveryProducts->flatMap(function ($deliveryProduct) {
                return $deliveryProduct->returns->pluck('status');
            });

            // Determine the overall return status based on the priority of statuses
            if ($returnStatuses->contains('P')) {
                $returnStatus = 'P'; // If at least one Pending
            } elseif ($returnStatuses->contains('S') && $returnStatuses->every(fn($status) => $status === 'S')) {
                $returnStatus = 'S'; // If all are Success
            } else {
                $returnStatus = 'NR'; // Default to No Return
            }

            return [
                'delivery_id' => $delivery->id,
                'delivery_no' => $delivery->delivery_no,
                'notes' => $delivery->notes,
                'status' => $delivery->status,
                'return_status' => $returnStatus,
                'formatted_date' => Carbon::parse($delivery->created_at)
                    ->timezone(config('app.timezone')) // Apply the timezone from config
                    ->format('m/d/Y H:i'),
                'purchase_order' => $delivery->purchaseOrder ? [
                    'purchase_order_id' => $delivery->purchaseOrder->id,
                    'customer_name' => $delivery->purchaseOrder->customer_name,
                    'status' => $delivery->purchaseOrder->status,
                    'date' => Carbon::parse($delivery->purchaseOrder->date)
                        ->timezone(config('app.timezone')) // Apply the timezone from config
                        ->format('m/d/Y H:i'),
                ] : null,
                'delivery_man' => $delivery->user ? [
                    'user_id' => $delivery->user->id,
                    'name' => $delivery->user->name,
                    'number' => $delivery->user->number,
                    'username' => $delivery->user->username,
                ] : null,
            ];
        });


        // Return the formatted data with pagination metadata
        return response()->json([
            'deliveries' => $formattedDeliveries,
            'pagination' => [
                'total' => $deliveries->total(),
                'perPage' => $deliveries->perPage(),
                'currentPage' => $deliveries->currentPage(),
                'lastPage' => $deliveries->lastPage(),
            ],
        ]);
    }

    public function getDeliveryProducts($deliveryId)
    {
        // Fetch the delivery with its associated products and purchase order
        $delivery = Delivery::with(['deliveryProducts.product', 'purchaseOrder.productDetails'])->find($deliveryId);

        // Check if the delivery exists
        if (!$delivery) {
            return response()->json([
                'error' => true,
                'message' => 'Delivery not found.',
            ], 404);
        }

        // Format the products for the response
        $formattedProducts = $delivery->deliveryProducts->map(function ($deliveryProduct) use ($delivery) {
            // Fetch the product detail price
            $productDetail = $delivery->purchaseOrder->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

            return [
                'product_id' => $deliveryProduct->product->id,
                'name' => $deliveryProduct->product->product_name,
                'quantity' => $deliveryProduct->quantity,
                'price' => $productDetail ? $productDetail->price : 'N/A', // Use product detail price or fallback
            ];
        });

        // Return the delivery and its products
        return response()->json([
            'delivery_id' => $delivery->id,
            'purchase_order_id' => $delivery->purchaseOrder->id,
            'delivery_no' => $delivery->delivery_no,
            'products' => $formattedProducts,
        ]);
    }

    public function updateDeliveryEmployee(Request $request, $deliveryId)
    {
        // Validate the request data
        $validated = $request->validate([
            'delivery_man_id' => 'required|exists:users,id', // Ensure the delivery man exists
        ]);

        // Find the delivery
        $delivery = Delivery::find($deliveryId);

        // Check if the delivery exists
        if (!$delivery) {
            return response()->json([
                'error' => true,
                'message' => 'Delivery not found.',
            ], 404);
        }

        // Update the delivery man
        $delivery->user_id = $validated['delivery_man_id'];
        $delivery->save();

        // Return a success response
        return response()->json([
            'success' => true,
            'message' => 'Delivery man updated successfully.',
            'delivery' => [
                'delivery_id' => $delivery->id,
                'delivery_no' => $delivery->delivery_no,
                'delivery_man' => [
                    'user_id' => $delivery->user->id,
                    'name' => $delivery->user->name,
                    'number' => $delivery->user->number,
                ],
            ],
        ]);
    }

    public function deliveryCount()
    {
        // Count deliveries with status 'S' (Success)
        $successCount = Delivery::where('status', 'S')->count();

        // Count deliveries with status 'OD' (On Delivery)
        $onDeliveryCount = Delivery::where('status', 'OD')->count();

        // Count deliveries with status 'P' (Pending)
        $pendingCount = Delivery::where('status', 'P')->count();

        // Count deliveries with status 'F' (Failed)
        $failedCount = Delivery::where('status', 'F')->count();

        // Return the counts as a response
        return response()->json([
            'success' => $successCount,
            'on_delivery' => $onDeliveryCount,
            'pending' => $pendingCount,
            'failed' => $failedCount,
        ]);
    }




}
