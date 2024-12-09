<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\DeliveryProduct;
use App\Models\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\API\BaseController;

use Carbon\Carbon;

class PurchaseOrder_ViewDeliveries extends BaseController
{
    public function show_deliveries_by_purchase_order($id)
    {
        try {
            // Enable query logging to debug any issues with the query
            DB::enableQueryLog();

            // Fetch the purchase order with all related data
            $purchaseOrderData = DB::table('purchase_orders')
                ->leftJoin('addresses', 'purchase_orders.address_id', '=', 'addresses.id')
                ->leftJoin('deliveries', 'purchase_orders.id', '=', 'deliveries.purchase_order_id')
                ->leftJoin('delivery_products', 'deliveries.id', '=', 'delivery_products.delivery_id')
                ->leftJoin('products', 'delivery_products.product_id', '=', 'products.id')
                ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
                ->leftJoin('users as delivery_user', 'deliveries.user_id', '=', 'delivery_user.id')
                ->leftJoin('users as admin_user', 'purchase_orders.user_id', '=', 'admin_user.id')
                ->leftJoin('returns', 'delivery_products.id', '=', 'returns.delivery_product_id') // Corrected column and table name
                ->where('purchase_orders.id', $id)
                ->select(
                    'purchase_orders.*',
                    'admin_user.name as admin_name',
                    'addresses.street',
                    'addresses.barangay',
                    'addresses.city',
                    'addresses.province',
                    'addresses.zip_code',
                    'deliveries.id as delivery_id',
                    'deliveries.delivery_no',
                    'deliveries.status as delivery_status',
                    'deliveries.created_at as delivery_date',
                    'deliveries.updated_at as updated_date',
                    'delivery_user.name as delivery_man_name',
                    'delivery_products.id as delivery_product_id',
                    'delivery_products.product_id',
                    'delivery_products.quantity as delivery_product_quantity',
                    'delivery_products.no_of_damages',
                    'product_details.price',
                    'products.product_name',
                    'returns.id as return_id',
                    'returns.status as return_status',
                    'returns.reason as return_reason',
                    'returns.created_at as return_date'
                )
                ->get();

            if ($purchaseOrderData->isEmpty()) {
                return response()->json(['message' => 'No data found for this purchase order ID.'], 404);
            }

            // Group data by delivery_id
            $groupedData = $purchaseOrderData->groupBy('delivery_id')->map(function ($items, $deliveryId) {
                $firstItem = $items->first();

                // Group products by product_id to ensure unique products within each delivery
                $uniqueProducts = $items->groupBy('product_id')->map(function ($productItems) {
                    $firstProductItem = $productItems->first();

                    // Get returns data for each product
                    $returns = $productItems->map(function ($item) {
                        return [
                            'return_id' => $item->return_id,
                            'status' => $item->return_status,
                            'reason' => $item->return_reason,
                            'date' => $item->return_date,
                        ];
                    })->filter(function ($return) {
                        // Filter out null returns
                        return $return['return_id'] !== null;
                    })->values();

                    return [
                        'delivery_product_id' => $firstProductItem->delivery_product_id,
                        'product_name' => $firstProductItem->product_name,
                        'quantity' => $firstProductItem->delivery_product_quantity,
                        'price' => $firstProductItem->price,
                        'no_of_damages' => $firstProductItem->no_of_damages,
                        'returns' => $returns,
                    ];
                })->values();

                return [
                    'delivery_id' => $deliveryId ?: null,
                    'delivery_no' => $firstItem->delivery_no ?: null,
                    'delivery_status' => $firstItem->delivery_status ?: null,
                    'delivery_man_name' => $firstItem->delivery_man_name ?: null,
                    'delivery_created' => $firstItem->delivery_date,
                    'delivery_updated' => $firstItem->updated_date,
                    'products' => $uniqueProducts,
                ];
            })->filter(function ($delivery) {
                return $delivery['delivery_id'] !== null || $delivery['delivery_no'] !== null;
            })->values();

            // Structure the final response, filtering out null values in the address and main fields
            $response = array_filter([
                'purchase_order_id' => $purchaseOrderData->first()->id,
                'admin_name' => $purchaseOrderData->first()->admin_name,
                'customer_name' => $purchaseOrderData->first()->customer_name,
                'status' => $purchaseOrderData->first()->status,
                'created_at' => $purchaseOrderData->first()->created_at,
                'updated_at' => $purchaseOrderData->first()->updated_at,
                'address' => array_filter([
                    'street' => $purchaseOrderData->first()->street,
                    'barangay' => $purchaseOrderData->first()->barangay,
                    'city' => $purchaseOrderData->first()->city,
                    'province' => $purchaseOrderData->first()->province,
                    'zip_code' => $purchaseOrderData->first()->zip_code,
                ], fn($value) => !is_null($value) && $value !== ''),
                'deliveries' => $groupedData,
            ], fn($value) => !is_null($value) && $value !== '');

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error in show_deliveries_by_purchase_order:', [
                'error' => $e->getMessage(),
                'query' => DB::getQueryLog()
            ]);
            return response()->json(['error' => 'Something went wrong while retrieving the data.'], 500);
        }
    }

    // Get all Purchase Orders based on filter
    public function index_purchase_order(Request $request)
    {
        $statusFilter = $request->query('status', null); // Get the status query parameter if available

        // Get all purchase orders with related data
        $deliveries = PurchaseOrder::with(['address', 'productDetails.product'])
                            ->where('sale_type_id', 1)
                            ->when($statusFilter && $statusFilter !== 'All', function ($query) use ($statusFilter) {
                                return $query->where('status', $statusFilter);
                            })
                            ->paginate(20); // Paginate the orders

        // Get the total count of purchase orders
        $totalDeliveries = PurchaseOrder::where('sale_type_id', 1)->count();

        // Calculate the total worth of all purchase orders
        $totalWorth = DB::table('product_details')
            ->join('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.sale_type_id', 1)
            ->select(DB::raw('SUM(product_details.quantity * product_details.price) as total_worth'))
            ->value('total_worth');

        // Map over the Paginator's items
        $formattedOrders = collect($deliveries->items())->map(function ($order) {
            return [
                'purchase_order_id' => $order->id,
                'sale_type_name' => $order->saleType->sale_type_name,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'created_at' => Carbon::parse($order->created_at)->format('l, M d, Y'),
                'address' => [
                    'street' => $order->address->street,
                    'barangay' => $order->address->barangay,
                    'city' => $order->address->city,
                    'zip_code' => $order->address->zip_code,
                    'province' => $order->address->province,
                ],
                'product_details' => $order->productDetails->map(function ($detail) {
                    return [
                        'product_details_id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->product_name ?? 'N/A',
                        'price' => $detail->price,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        return response()->json([
            'orders' => $formattedOrders,
            'pagination' => [
                'total' => $deliveries->total(),
                'perPage' => $deliveries->perPage(),
                'currentPage' => $deliveries->currentPage(),
                'lastPage' => $deliveries->lastPage(),
            ],
            'summary' => [
                'totalPurchaseOrders' => $totalDeliveries, // Total number of purchase orders
                'totalWorth' => number_format($totalWorth, 2, '.', ''), // Total worth of all purchase orders
            ],
        ]);
    }

    // This PO Function here is to Fetch For OVERVIEW Page
    public function latest_purchase_orders()
    {
        // Get the latest 5 purchase orders with related data
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product', 'saleType'])
            ->where('sale_type_id', 1)
            ->where('status', 'P')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Calculate total number of purchase orders
        $totalPurchaseOrders = PurchaseOrder::where('sale_type_id', 1)->count();

        // Calculate the total accumulated money from all purchase orders
        $totalMoneyAccumulated = PurchaseOrder::with('productDetails')
            ->get()
            ->reduce(function ($carry, $order) {
                return $carry + $order->productDetails->reduce(function ($carryDetails, $detail) {
                    return $carryDetails + ($detail->quantity * $detail->price);
                }, 0);
            }, 0);

        // Calculate the count of purchase orders based on status
        $purchaseOrderStatusCounts = PurchaseOrder::where('sale_type_id', 1)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pendingCount = $purchaseOrderStatusCounts['P'] ?? 0;
        $failedCount = $purchaseOrderStatusCounts['F'] ?? 0;
        $successCount = $purchaseOrderStatusCounts['S'] ?? 0;

        // Format and map the orders with total worth for each order
        $formattedOrders = $purchaseOrders->map(function ($order) {
            // Calculate total worth for each order
            $totalWorth = $order->productDetails->reduce(function ($carry, $detail) {
                return $carry + ($detail->quantity * $detail->price);
            }, 0);

            return [
                'purchase_order_id' => $order->id,
                'sale_type_name' => $order->saleType->sale_type_name,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'total_worth' => number_format($totalWorth, 2, '.', ''), // Total worth of this purchase order
                'created_at' => Carbon::parse($order->created_at)->format('l, M d, Y'),
                'address' => [
                    'street' => $order->address->street,
                    'barangay' => $order->address->barangay,
                    'city' => $order->address->city,
                    'zip_code' => $order->address->zip_code,
                    'province' => $order->address->province,
                ],
                'product_details' => $order->productDetails->map(function ($detail) {
                    return [
                        'product_details_id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->product_name ?? 'N/A',
                        'price' => $detail->price,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        return response()->json([
            'orders' => $formattedOrders,
            'summary' => [
                'totalPurchaseOrders' => $totalPurchaseOrders, // Total number of purchase orders
                'totalMoneyAccumulated' => number_format($totalMoneyAccumulated, 2, '.', ''), // Total money accumulated
                'pendingCount' => $pendingCount, // Number of purchase orders with status 'P' (Pending)
                'failedCount' => $failedCount, // Number of purchase orders with status 'F' (Failed)
                'successCount' => $successCount, // Number of purchase orders with status 'S' (Success)
            ],
        ]);
    }

    // Get all Purchase Orders by status = 'P'
    public function pending_purchase_order()
    {
        $purchaseOrders = PurchaseOrder::with(['address, productDetails.product'])
            ->where('sale_type_id', 1)
            ->where('status', 'P') //This will fetch data solely for status that has pending.
            ->get();

        $formattedOrders = $purchaseOrders->map(function ($order) {
            return [
                'purchase_order_id' => $order->id,
                'user_id' => $order->user_id,
                // 'address_id' => $order->address_id,
                'sale_type_name' => $order->saleType->sale_type_name,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'created_at' => Carbon::parse($order->created_at)->format('l, M d, Y'), // Readable date format
                'address' => [
                    // 'id' => $order->address->id,
                    'street' => $order->address->street,
                    'barangay' => $order->address->barangay,
                    'zip_code' => $order->address->zip_code,
                    'province' => $order->address->province,
                    // 'created_at' => Carbon::parse($order->address->created_at)->format('l, M d, Y'), // Readable date format
                ],
                'product_details | Products to Deliver' => $order->productDetails->map(function ($detail) {
                    return [
                        'product_details_id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->product_name ?? 'N/A', // Include product name
                        // 'purchase_order_id' => $detail->purchase_order_id,
                        'price' => $detail->price,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        return response()->json($formattedOrders);
    }

}
