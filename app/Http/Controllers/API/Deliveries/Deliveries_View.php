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
        // Default sorting parameters
        $sortColumn = $request->input('sort_column', 'created_at');
        $sortDirection = $request->input('sort_direction', 'asc');

        // Validate allowed sort columns
        $validColumns = ['id', 'purchase_order_id', 'status', 'created_at', 'delivery_man'];
        if (!in_array($sortColumn, $validColumns)) {
            return response()->json(['error' => 'Invalid sort column'], 400);
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            return response()->json(['error' => 'Invalid sort direction'], 400);
        }

        // Base Query
        $query = Delivery::query()
            ->with(['purchaseOrder', 'user', 'deliveryProducts.returns'])
            ->join('users', 'users.id', '=', 'deliveries.user_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'deliveries.purchase_order_id')
            ->select(
                'deliveries.*',
                'purchase_orders.id as purchase_order_id',
                'users.name as delivery_man'
            );

        // Apply sorting logic
        if ($sortColumn === 'purchase_order_id') {
            $query->orderBy('purchase_orders.id', $sortDirection);
        } elseif ($sortColumn === 'delivery_man') {
            $query->orderBy('users.name', $sortDirection);
        } elseif ($sortColumn === 'status') {
            // Custom sorting for delivery status
            $query->orderByRaw("
                CASE deliveries.status
                    WHEN 'F' THEN 1    -- Failed (Highest Priority)
                    WHEN 'OD' THEN 2   -- On-Delivery
                    WHEN 'P' THEN 3    -- Pending
                    WHEN 'S' THEN 4    -- Delivered (Lowest Priority)
                    ELSE 5
                END {$sortDirection}, deliveries.created_at {$sortDirection}
            ");
        } else {
            $query->orderBy($sortColumn === 'id' ? 'deliveries.id' : "deliveries.$sortColumn", $sortDirection);
        }

        // Filter by return_status
        if ($request->filled('return_status') && in_array($request->input('return_status'), ['NR', 'P', 'S'])) {
            $query->whereHas('deliveryProducts', function ($q) use ($request) {
                if ($request->input('return_status') === 'NR') {
                    // Handle "NR" (No Returns) case
                    $q->doesntHave('returns');
                } else {
                    // Handle "P" and "S" cases
                    $q->whereHas('returns', function ($r) use ($request) {
                        $r->where('status', $request->input('return_status'));
                    });
                }
            });
        }

        // Filter by delivery status
        if ($request->filled('status') && in_array($request->input('status'), ['F', 'P', 'OD', 'S'])) {
            $query->where('deliveries.status', $request->input('status'));
        }

        $deliveries = $query->paginate(20);

        // Format response
        $formattedDeliveries = collect($deliveries->items())->map(function ($delivery) {
            $returnStatuses = $delivery->deliveryProducts->flatMap(function ($deliveryProduct) {
                return $deliveryProduct->returns->pluck('status');
            });

            $returnStatus = $returnStatuses->contains('P')
                ? 'P'
                : ($returnStatuses->contains('S') && $returnStatuses->every(fn($status) => $status === 'S')
                    ? 'S'
                    : 'NR');

            // Calculate time exceeded
            $timeExceeded = $delivery->delivered_at
                ? now()->greaterThanOrEqualTo(Carbon::parse($delivery->delivered_at)->addMinutes(1))
                : false;

            return [
                'delivery_id' => $delivery->id,
                'delivery_no' => $delivery->delivery_no,
                'status' => $delivery->status,
                'return_status' => $returnStatus,
                'formatted_date' => Carbon::parse($delivery->created_at)->format('m/d/Y H:i'),
                'time_exceeded' => $timeExceeded ? 'yes' : 'no',
                'purchase_order' => [
                    'purchase_order_id' => $delivery->purchase_order_id,
                    'customer_name' => $delivery->purchaseOrder->customer_name ?? null,
                ],
                'delivery_man' => [
                    'name' => $delivery->delivery_man,
                ],
            ];
        });

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

    // public function updateDeliveryEmployee(Request $request, $deliveryId)
    // {
    //     // Validate the request data
    //     $validated = $request->validate([
    //         'delivery_man_id' => 'required|exists:users,id', // Ensure the delivery man exists
    //     ]);

    //     // Find the delivery
    //     $delivery = Delivery::find($deliveryId);

    //     // Check if the delivery exists
    //     if (!$delivery) {
    //         return response()->json([
    //             'error' => true,
    //             'message' => 'Delivery not found.',
    //         ], 404);
    //     }

    //     // Update the delivery man
    //     $delivery->user_id = $validated['delivery_man_id'];
    //     $delivery->save();

    //     // Return a success response
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Delivery man updated successfully.',
    //         'delivery' => [
    //             'delivery_id' => $delivery->id,
    //             'delivery_no' => $delivery->delivery_no,
    //             'delivery_man' => [
    //                 'user_id' => $delivery->user->id,
    //                 'name' => $delivery->user->name,
    //                 'number' => $delivery->user->number,
    //             ],
    //         ],
    //     ]);
    // }

    public function updateDeliveryDetails(Request $request, $deliveryId)
    {
        // Validate the request data
        $validated = $request->validate([
            'delivery_man_id' => 'nullable|exists:users,id',
            'damages' => 'required|array',
            'damages.*.product_id' => 'required|exists:delivery_products,product_id',
            'damages.*.no_of_damages' => 'required|integer|min:0',
        ]);

        // Find the delivery
        $delivery = Delivery::with('deliveryProducts.returns')->find($deliveryId);

        // Check if the delivery exists
        if (!$delivery) {
            return response()->json([
                'error' => true,
                'message' => 'Delivery not found.',
            ], 404);
        }

        // Restrict updates if status is OD
        if ($delivery->status === 'OD') {
            return response()->json([
                'error' => true,
                'message' => 'Delivery cannot be edited while it is on delivery.',
            ], 403);
        }

        // Update delivery_products and returns
        foreach ($validated['damages'] as $damage) {
            $deliveryProduct = $delivery->deliveryProducts
                ->where('product_id', $damage['product_id'])
                ->first();

            if ($deliveryProduct) {
                $deliveryProduct->no_of_damages = $damage['no_of_damages'];
                $deliveryProduct->save();

                $return = $deliveryProduct->returns->first();
                if ($return) {
                    $return->status = 'P';
                    $return->save();
                }
            }
        }

        // Update delivery status to 'P'
        $delivery->status = 'P';

        // Optionally update the delivery man
        if (!empty($validated['delivery_man_id'])) {
            $delivery->user_id = $validated['delivery_man_id'];
        }

        $delivery->save();

        return response()->json([
            'success' => true,
            'message' => 'Delivery details updated successfully.',
            'delivery' => [
                'delivery_id' => $delivery->id,
                'delivery_no' => $delivery->delivery_no,
                'delivery_status' => $delivery->status,
                'delivery_man' => $delivery->user ? [
                    'user_id' => $delivery->user->id,
                    'name' => $delivery->user->name,
                    'number' => $delivery->user->number,
                ] : null,
                'updated_products' => $delivery->deliveryProducts->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'no_of_damages' => $product->no_of_damages,
                    ];
                }),
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
