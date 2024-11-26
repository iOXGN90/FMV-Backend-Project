<?php

namespace App\Http\Controllers\API\Deliveries;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\BaseController;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Http\Request;

class Deliveries_View_OnDelivery extends BaseController
{

    public function on_delivery()
    {
        $data = DB::table('deliveries as a')
            ->join('users as b', 'b.id', '=', 'a.user_id')
            ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id')
            ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id')
            ->join('product_details as e', function($join) {
                $join->on('c.id', '=', 'e.purchase_order_id')
                     ->on('e.product_id', '=', 'd.product_details_id'); // Ensure product matching
            })
            ->join('products as f', 'f.id', '=', 'e.product_id')
            ->join('addresses as g', 'c.address_id', '=', 'g.id')
            ->select(
                'a.id as delivery_id',
                'a.delivery_no',
                'a.status',
                DB::raw("DATE_FORMAT(a.created_at, '%m/%d/%Y') as date"),
                'b.id as deliveryman_id',
                'b.name as deliveryman_name',
                'c.id as purchase_order_id',
                'c.customer_name',
                'd.quantity',
                'e.price',
                'f.id as product_id',
                'f.product_name',
                'g.street',
                'g.barangay',
                'g.zip_code',
                'g.province',
                'g.city'
            )
            ->where('a.status', '=', 'OD')
            ->orderBy('a.id', 'desc') // Order by delivery ID to show the latest first
            ->get();

        $groupedOrders = $data->groupBy('purchase_order_id');

        $properFormat = $groupedOrders->map(function($orderedGroup) {
            $firstOrder = $orderedGroup->first();
            return [
                'purchase_order_id' => $firstOrder->purchase_order_id,
                'delivery_id' => $firstOrder->delivery_id,
                'delivery_no' => $firstOrder->delivery_no,
                'deliveryman_id' => $firstOrder->deliveryman_id,
                'deliveryman_name' => $firstOrder->deliveryman_name,
                'customer_name' => $firstOrder->customer_name,
                'status' => $firstOrder->status,
                'date' => $firstOrder->date,
                'address' => [
                    'street' => $firstOrder->street,
                    'barangay' => $firstOrder->barangay,
                    'zip_code' => $firstOrder->zip_code,
                    'province' => $firstOrder->province,
                    'city' => $firstOrder->city,
                ],
                'products' => $orderedGroup->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                })
                ->unique('product_id')
                ->values()
                ->toArray()
            ];
        });

        return response()->json($properFormat->values());
    }

    public function on_delivery_by_deliveryman_id($deliveryman_id)
    {
        $data = DB::table('deliveries as a')
            ->join('users as b', 'b.id', '=', 'a.user_id') // Join users (deliverymen)
            ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id') // Join purchase orders
            ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id') // Join delivery products
            ->join('products as f', 'f.id', '=', 'd.product_id') // Join products directly with delivery_products
            ->join('product_details as e', 'f.id', '=', 'e.product_id') // Join product details through products
            ->join('addresses as g', 'g.id', '=', 'c.address_id') // Join addresses
            ->select(
                'a.id as delivery_id',
                'a.delivery_no',
                'a.status',
                DB::raw("DATE_FORMAT(a.created_at, '%m/%d/%Y') as date"),
                'b.id as deliveryman_id',
                'b.name as deliveryman_name',
                'd.id as delivery_product_id', // Include delivery_product_id
                'c.id as purchase_order_id',
                'c.customer_name',
                'd.quantity',
                'e.price',
                'f.id as product_id',
                'f.product_name',
                'g.street',
                'g.barangay',
                'g.zip_code',
                'g.province',
                'g.city'
            )
            ->where('a.status', '=', 'OD')
            ->where('a.user_id', '=', $deliveryman_id) // Filter by specific deliveryman_id (user_id)
            ->orderBy('c.id')
            ->get();

        $groupedOrders = $data->groupBy('purchase_order_id');

        $formattedData = $groupedOrders->map(function ($group) {
            $first = $group->first();
            return [
                'purchase_order_id' => $first->purchase_order_id,
                'delivery_id' => $first->delivery_id,
                'delivery_no' => $first->delivery_no,
                'deliveryman_id' => $first->deliveryman_id,
                'delivery_product_id' => $first->delivery_product_id, // Include delivery_product_id here
                'deliveryman_name' => $first->deliveryman_name,
                'customer_name' => $first->customer_name,
                'status' => $first->status,
                'date' => $first->date,
                'address' => [
                    'street' => $first->street,
                    'barangay' => $first->barangay,
                    'zip_code' => $first->zip_code,
                    'province' => $first->province,
                    'city' => $first->city,
                ],
                'products' => $group->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                })->unique('product_id')->values(),
            ];
        });

        return response()->json($formattedData->values());
    }



}
