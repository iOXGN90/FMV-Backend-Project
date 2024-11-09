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
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])->where('sale_type_id', 1)->get();

        $formattedOrders = $purchaseOrders->map(function ($order) {
            return [
                'purchase_order_id' => $order->id,
                // 'admin id' => $order->user_id,
                'sale_type_name' => $order->saleType->sale_type_name,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'created_at' => Carbon::parse($order->created_at)->format('l, M d, Y'), // Readable date format
                'address' => [
                    'street' => $order->address->street,
                    'barangay' => $order->address->barangay,
                    'city' => $order->address->city,
                    'zip_code' => $order->address->zip_code,
                    'province' => $order->address->province,
                ],
                'product_details | Products to Deliver' => $order->productDetails->map(function ($detail) {
                    return [
                        'product_details_id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->product_name ?? 'N/A', // Include product name
                        'price' => $detail->price,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        return response()->json($formattedOrders);
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

    //! VIEW THE REMAINING STOCKS TO DELIVER PER PURCHASE ORDER
    public function getRemainingToDeliver($id)
    {
        $results = DB::table('delivery_details as a')
            ->select(
                'a.id as delivery_details_id',
                'a.quantity'
            )
            // ->where('a.id', '=', $id)
            // ->where('a.sale_type_id', '=', 1)
            // ->distinct('c.id') //<--- is this a valid answer, GPT?
            // ->groupBy('b.id', 'd.id', 'a.id', 'b.quantity', 'd.product_name')
            // ->orderBy('a.id')
            ->get();

        // return response()->json($results->values());
        // $data = DB::table('deliveries as a')
        // ->join('users as b', 'b.id', '=', 'a.user_id')
        // ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id')
        // ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id')
        // ->join('product_details as e', 'c.id', '=', 'e.purchase_order_id')
        // ->join('products as f', 'f.id', '=', 'e.product_id')
        // ->select(
        //     'a.id as delivery_id',
        //     'a.status',
        //     'b.id as deliveryman_id',
        //     'b.name as deliveryman_name',
        //     'c.id as purchase_order_id',
        //     'c.customer_name',
        //     'd.quantity',
        //     'e.price',
        //     'f.id as product_id',
        //     'f.product_name'
        // )
        // // ->where('a.status', '=', 'OD')
        // ->where('a.id', '=', 4)
        // // ->distinct() //! <--- This will choose data that are unique; same data will be not shown
        // ->orderBy('c.id')
        // ->get();

        // $groupedOrders = $data->groupBy('purchase_order_id');

        // $properFormat = $groupedOrders->map(function($orderedGroup){
        //     $firstOrder = $orderedGroup->first();
        //     return [
        //         'purchase_order_id' => $firstOrder->purchase_order_id,
        //         'delivery_id' => $firstOrder->delivery_id,
        //         'deliveryman_id' => $firstOrder->deliveryman_id,
        //         'deliveryman_name' => $firstOrder->deliveryman_name,
        //         'customer_name' => $firstOrder->customer_name,
        //         'status' => $firstOrder->status,
        //         'products' => $orderedGroup->map(function($item){
        //             return[
        //                 'product_id' => $item->product_id,
        //                 'product_name' => $item->product_name,
        //                 'quantity' => $item->quantity,
        //                 'price' => $item->price
        //             ];
        //         })
        //         ->unique('product_id') //! <---- This will choose unique data of product id; clone data will be disregard
        //         ->values()
        //         ->toArray(),
        //     ];
        // });

        // return response()->json($properFormat->values());
}
    //! VIEW THE REMAINING STOCKS TO DELIVER PER PURCHASE ORDER
}
