<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PurchaseOrder_GetRemainingBalanceOfProductToDeliver
{
    public function getRemainingQuantities($purchaseOrderId)
    {
        // Query to fetch deliveries and their associated product information
        $deliveries = DB::table('deliveries as d')
            ->join('delivery_products as dp', 'd.id', '=', 'dp.delivery_id') // Join delivery_products to get product quantities
            ->join('products as p', 'dp.product_details_id', '=', 'p.id') // Join products to get product details
            ->where('d.purchase_order_id', $purchaseOrderId) // Filter by the given purchase order ID
            ->select(
                'd.id as delivery_id', // Select delivery ID
                'd.delivery_no', // Select delivery number
                'd.status', // Select delivery status
                DB::raw("DATE_FORMAT(d.created_at, '%m/%d/%Y') as date"), // Format the created_at date
                'dp.quantity as delivered_quantity', // Select delivered quantity from delivery_products
                'p.id as product_id', // Select product ID
                'p.product_name' // Select product name
            )
            ->orderBy('d.delivery_no') // Order results by delivery number
            ->get(); // Execute the query and get results

        // Group deliveries with their respective products
        $groupedDeliveries = $deliveries->groupBy('delivery_id')->map(function ($deliveryGroup) {
            $firstDelivery = $deliveryGroup->first();
            return [
                'delivery_id' => $firstDelivery->delivery_id,
                'delivery_no' => $firstDelivery->delivery_no,
                'status' => $firstDelivery->status,
                'date' => $firstDelivery->date,
                'products' => $deliveryGroup->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'delivered_quantity' => $item->delivered_quantity,
                    ];
                })->toArray(),
            ];
        })->values();

        // Calculate total delivered quantity for each product
        $totalDeliveredQuantities = $deliveries->groupBy('product_id')->map(function ($productGroup) {
            $firstProduct = $productGroup->first();
            return [
                'product_id' => $firstProduct->product_id,
                'product_name' => $firstProduct->product_name,
                'total_delivered_quantity' => $productGroup->sum('delivered_quantity'),
            ];
        })->values();

        // Fetch total ordered quantities from product_details
        $orderedQuantities = DB::table('product_details as pd')
            ->join('products as p', 'pd.product_id', '=', 'p.id')
            ->where('pd.purchase_order_id', $purchaseOrderId)
            ->select(
                'pd.product_id',
                'p.product_name',
                'pd.quantity as ordered_quantity',
                'pd.price', // Use price from product_details if applicable
                DB::raw('pd.quantity * pd.price as total_value') // Calculate total value using price from product_details
            )
            ->get();

        // Combine the ordered and delivered data
        $combinedQuantities = $orderedQuantities->map(function ($ordered) use ($totalDeliveredQuantities) {
            $delivered = $totalDeliveredQuantities->firstWhere('product_id', $ordered->product_id);
            return [
                'product_id' => $ordered->product_id,
                'product_name' => $ordered->product_name,
                'ordered_quantity' => $ordered->ordered_quantity,
                'total_value' => $ordered->total_value,
                'total_delivered_quantity' => $delivered ? $delivered['total_delivered_quantity'] : 0,
                'remaining_quantity' => $ordered->ordered_quantity - ($delivered ? $delivered['total_delivered_quantity'] : 0),
            ];
        });

        // Return the response in JSON format
        return response()->json([
            'purchase_order_id' => $purchaseOrderId,
            'deliveries' => $groupedDeliveries,
            'Remaining' => [
                'products' => $combinedQuantities,
            ],
        ]);
    }
}
