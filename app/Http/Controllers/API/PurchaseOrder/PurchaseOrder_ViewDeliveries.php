<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\DeliveryProduct;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\BaseController;

class PurchaseOrder_ViewDeliveries extends BaseController
{
    // Get Purchase Order Records

    public function show_purchase_order($id)
    {
        $purchaseOrder = PurchaseOrder::with('address', 'productDetails.product')
            ->where('sale_type_id', 1)  //? This condition for sale_type_id = 1
            ->find($id);

        if (is_null($purchaseOrder)) {
            return response()->json([
                'message' => 'Delivery [Purchase Order] is not found'], 404);
        }

        return response()->json($purchaseOrder);
    }


    public function show_deliveries_by_purchase_order($id)
    {
        $purchaseOrderData = DB::table('purchase_orders')
            ->leftJoin('addresses', 'purchase_orders.address_id', '=', 'addresses.id')
            ->leftJoin('deliveries', 'purchase_orders.id', '=', 'deliveries.purchase_order_id')
            ->leftJoin('delivery_products', 'deliveries.id', '=', 'delivery_products.delivery_id')
            ->leftJoin('products', 'delivery_products.product_id', '=', 'products.id')
            ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('users as delivery_user', 'deliveries.user_id', '=', 'delivery_user.id')
            ->leftJoin('users as admin_user', 'purchase_orders.user_id', '=', 'admin_user.id')
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
                'delivery_products.product_id',
                'delivery_products.quantity as delivery_product_quantity',
                'delivery_products.no_of_damages',
                'product_details.price',
                'products.product_name'
            )
            ->get();

        // Group data by delivery_id
        $groupedData = $purchaseOrderData->groupBy('delivery_id')->map(function ($items, $deliveryId) {
            $firstItem = $items->first();

            // Group products by product_id to ensure unique products within each delivery
            $uniqueProducts = $items->groupBy('product_id')->map(function ($productItems) {
                $firstProductItem = $productItems->first();

                return [
                    'product_name' => $firstProductItem->product_name,
                    'quantity' => $firstProductItem->delivery_product_quantity,
                    'price' => $firstProductItem->price,
                    'no_of_damages' => $firstProductItem->no_of_damages,
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
            // Remove deliveries with all null fields (if there’s no delivery_id, delivery_no, etc.)
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
    }


    // Get all Purchase Orders
    public function index_purchase_order()
    {
        // Get all purchase orders with related data
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])
                            ->where('sale_type_id', 1)
                            ->paginate(20);  // Paginate the orders

        // Get the total count of purchase orders
        $totalPurchaseOrders = PurchaseOrder::where('sale_type_id', 1)->count();

        // Calculate the total worth of all purchase orders
        $totalWorth = DB::table('product_details')
            ->join('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.sale_type_id', 1)
            ->select(DB::raw('SUM(product_details.quantity * product_details.price) as total_worth'))
            ->value('total_worth');

        // Map over the Paginator's items
        $formattedOrders = collect($purchaseOrders->items())->map(function ($order) {
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
                'total' => $purchaseOrders->total(),
                'perPage' => $purchaseOrders->perPage(),
                'currentPage' => $purchaseOrders->currentPage(),
                'lastPage' => $purchaseOrders->lastPage(),
            ],
            'summary' => [
                'totalPurchaseOrders' => $totalPurchaseOrders, // Total number of purchase orders
                'totalWorth' => number_format($totalWorth, 2, '.', ''), // Total worth of all purchase orders
            ],
        ]);
    }

    public function latest_purchase_orders()
    {
        // Get the latest 5 purchase orders with related data
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])
            ->where('sale_type_id', 1)
            ->where('status', 'P')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Calculate total number of purchase orders
        $totalPurchaseOrders = PurchaseOrder::where('sale_type_id', 1)->count();

        // Calculate the total accumulated money from all purchase orders
        $totalMoneyAccumulated = PurchaseOrder::with('productDetails')
            // ->where('sale_type_id', 1)
            ->get()
            ->reduce(function ($carry, $order) {
                return $carry + $order->productDetails->reduce(function ($carryDetails, $detail) {
                    return $carryDetails + ($detail->quantity * $detail->price);
                }, 0);
            }, 0);

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
