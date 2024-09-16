<?php

namespace App\Http\Controllers\API;

use app\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;


class ViewDeliveryPurchaseOrder extends BaseController
{
    /**
     *! ------------- Start *VIEW* Purchase Order - Delivery -----------------
     */

    // Get Purchase Order Records
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('address', 'productDetails')->get();
        return response()->json($purchaseOrders);
    }

    // Get all Purchase Orders
    public function index_purchase_order()
    {
        $purchaseOrders = PurchaseOrder::with(['address', 'productDetails.product'])->where('sale_type_id', 1)->get();

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

    // Get a specific purchase order by ID
    public function show_purchase_order($id)
    {
        $purchaseOrder = PurchaseOrder::with('address', 'productDetails')->find($id);

        if (is_null($purchaseOrder)) {
            return response()->json(['message' => 'Purchase Order not found'], 404);
        }

        return response()->json($purchaseOrder);
    }

}
    /**
     *! ------------- End *VIEW* Purchase Order - Delivery -----------------
     */

    /**
     *! ------------- Start *VIEW* Purchase Order - Walkin -------------------
    *
    */

        Class ViewWalkinPurchaseOrder extends BaseController
        {
            //* Start View WalkIn
                public function index_walk_in()
                {
                    // Filter and get all walk-in orders where sale_type_id is 2
                    $walkInOrders = PurchaseOrder::with(['address', 'productDetails.product'])
                        ->where('sale_type_id', 2)
                        ->get();

                    return response()->json($walkInOrders);
                }
            //* End View WalkIn
        }


    /**
     *! ---------------- End *VIEW* Purchase Order - Walkin -------------------
     */

