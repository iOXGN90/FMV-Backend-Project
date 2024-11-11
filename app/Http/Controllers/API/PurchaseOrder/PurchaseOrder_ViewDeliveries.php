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
            ->leftJoin('product_details', 'delivery_products.product_details_id', '=', 'product_details.id')
            ->leftJoin('products', 'product_details.product_id', '=', 'products.id')
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
                'delivery_user.name as delivery_man_name',
                'delivery_products.quantity as delivery_product_quantity',
                'product_details.price',
                'products.product_name'
            )
            ->get();

        // Group data by delivery_id
        $groupedData = $purchaseOrderData->groupBy('delivery_id')->map(function ($items, $deliveryId) {
            $firstItem = $items->first();

            return [
                'delivery_id' => $deliveryId ?: null,
                'delivery_no' => $firstItem->delivery_no ?: null,
                'delivery_status' => $firstItem->delivery_status ?: null,
                'delivery_man_name' => $firstItem->delivery_man_name ?: null,
                'products' => $items->map(function ($item) {
                    // Only include products if product_name or quantity is available
                    return array_filter([
                        'product_name' => $item->product_name,
                        'quantity' => $item->delivery_product_quantity,
                        'price' => $item->price,
                    ], fn($value) => !is_null($value) && $value !== '');
                })->values(),
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
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])
                                        ->where('sale_type_id', 1)
                                        ->paginate(20);  // Paginate the orders

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
            ]
        ]);
    }

    public function latest_purchase_orders()
    {
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])
                        ->where('sale_type_id', 1)
                        ->orderBy('created_at', 'desc') // Order by latest
                        ->take(5) // Limit to 10 latest
                        ->get();

        $formattedOrders = $purchaseOrders->map(function ($order) {
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
