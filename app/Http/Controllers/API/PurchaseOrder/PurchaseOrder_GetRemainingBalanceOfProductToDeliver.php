<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PurchaseOrder_GetRemainingBalanceOfProductToDeliver
{
    public function getRemainingQuantities($purchaseOrderId)
    {
        $data = DB::table('purchase_orders as po')
            ->join('product_details as pd', 'po.id', '=', 'pd.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'pd.product_id')
            ->leftJoin('deliveries as d', function ($join) {
                $join->on('po.id', '=', 'd.purchase_order_id')
                     ->whereIn('d.status', ['OD', 'P', 'S']); // Include 'OD' and 'S' deliveries
            })
            ->leftJoin('delivery_products as dp', function ($join) {
                $join->on('d.id', '=', 'dp.delivery_id')
                     ->on('p.id', '=', 'dp.product_id'); // Ensure we are linking by product ID
            })
            ->select(
                'po.id as purchase_order_id',
                'po.customer_name',
                'pd.quantity as ordered_quantity',
                'pd.price as product_price',
                'p.id as product_id',
                'p.product_name',
                DB::raw('COALESCE(SUM(dp.quantity), 0) as delivered_quantity')
            )
            ->where('po.id', '=', $purchaseOrderId)
            ->groupBy('po.id', 'po.customer_name', 'pd.id', 'pd.quantity', 'pd.price', 'p.id', 'p.product_name')
            ->get();

        // Calculate totals for the order
        $totalOrderedQuantity = $data->sum('ordered_quantity');
        $totalDeliveredQuantity = $data->sum('delivered_quantity');
        $totalPrice = $data->sum(function ($item) {
            return $item->product_price * $item->ordered_quantity; // Calculate total price based on ordered quantity
        });

        // Prepare response
        $response = [
            'purchase_order_id' => $purchaseOrderId,
            'customer_name' => $data->first()->customer_name ?? '',
            'total_ordered_quantity' => $totalOrderedQuantity,
            'total_delivered_quantity' => $totalDeliveredQuantity,
            'total_price' => $totalPrice,
            'Products' => $data->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'ordered_quantity' => $item->ordered_quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'price' => $item->product_price
                ];
            })->toArray()
        ];

        return response()->json($response);
    }


}
