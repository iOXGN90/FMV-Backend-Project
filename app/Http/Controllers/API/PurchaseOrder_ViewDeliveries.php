<?php

namespace App\Http\Controllers\API;

use App\Models\DeliveryProduct;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrder_ViewDeliveries extends BaseController
{
    // Get Purchase Order Records

    public function show_purchase_order($id)
    {
        $purchaseOrder = PurchaseOrder::with('address', 'productDetails')
            ->where('sale_type_id', 1)  //? This condition for sale_type_id = 1
            ->find($id);

        if (is_null($purchaseOrder)) {
            return response()->json(['message' => 'Delivery [Purchase Order] is not found'], 404);
        }

        return response()->json($purchaseOrder);
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
