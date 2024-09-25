<?php

namespace App\Http\Controllers\API;

use App\Models\DeliveryProduct;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Carbon\Carbon;


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
        // Fetch the purchase order and its product details using the purchaseOrderId
        $purchaseOrder = PurchaseOrder::with('productDetails.product')->find($id);

        if (!$purchaseOrder) {
            return response()->json(['error' => 'Purchase order not found'], 404);
        }

        // Prepare an array to hold product and remaining quantity information
        $remainingToDeliver = $purchaseOrder->productDetails->map(function ($productDetail) use ($purchaseOrder) {
            // Calculate the delivered quantity for this product
            $deliveredQuantity = DeliveryProduct::whereHas('delivery', function ($query) use ($purchaseOrder) {
                $query->where('purchase_order_id', $purchaseOrder->id);
            })
            ->where('product_details_id', $productDetail->id)
            ->sum('quantity');

            $remainingQuantity = $productDetail->quantity - $deliveredQuantity;

            return [
                'product_name' => $productDetail->product->product_name,
                'original_quantity' => $productDetail->quantity,
                'delivered_quantity' => $deliveredQuantity,
                'remaining_quantity_to_deliver' => $remainingQuantity
            ];
        });

        return response()->json([
            'purchase_order_id' => $purchaseOrder->id,
            'remaining_to_deliver' => $remainingToDeliver
        ]);
    }

    //! VIEW THE REMAINING STOCKS TO DELIVER PER PURCHASE ORDER

}
