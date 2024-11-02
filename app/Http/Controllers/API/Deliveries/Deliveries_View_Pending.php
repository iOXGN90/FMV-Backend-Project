<?php

namespace App\Http\Controllers\API\Deliveries;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\BaseController;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\PurchaseOrder;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class Deliveries_View_Pending extends BaseController
{
    public function pending_deliveries_by_id($deliveryman_id)
    {
        $data = DB::table('deliveries as a')
            ->join('users as b', 'b.id', '=', 'a.user_id') // Join users (deliverymen)
            ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id') // Join purchase orders
            ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id') // Join delivery products
            ->join('product_details as e', 'c.id', '=', 'e.purchase_order_id') // Join product details
            ->join('products as f', 'f.id', '=', 'e.product_id') // Join products
            ->join('addresses as g', 'g.id', '=', 'c.address_id') // Correctly join the address table with purchase_orders
            ->select(
                'a.id as delivery_id',
                'a.delivery_no',
                'a.status',
                DB::raw("DATE_FORMAT(a.created_at, '%m/%d/%Y') as date"),  // Format date to MM/DD/YYYY
                'b.id as deliveryman_id',
                'b.name as deliveryman_name',
                'c.id as purchase_order_id',
                'c.customer_name',
                'd.quantity',
                'e.price',
                'f.id as product_id',
                'f.product_name',
                // Address fields{}
                'g.street',
                'g.barangay',
                'g.zip_code',
                'g.province',
                'g.city'
            )
            ->where('a.status', '=', 'P')
            ->where('a.user_id', '=', $deliveryman_id) // Filter by specific deliveryman_id (user_id)
            ->orderBy('c.id')
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
                ->unique('product_id') // Choose unique product data based on product_id
                ->values()
                ->toArray()
            ];
        });

        return response()->json($properFormat->values());
    }

    public function pending_deliveries(){
        $data = DB::table('deliveries as a')
        ->join('users as b', 'b.id', '=', 'a.user_id') // Join users (deliverymen)
        ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id') // Join purchase orders
        ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id') // Join delivery products
        ->join('product_details as e', 'c.id', '=', 'e.purchase_order_id') // Join product details
        ->join('products as f', 'f.id', '=', 'e.product_id') // Join products
        ->join('addresses as g', 'g.id', '=', 'c.address_id') // Correctly join the address table with purchase_orders
        ->select(
            'a.id as delivery_id',
            'a.delivery_no',
            'a.status',
            DB::raw("DATE_FORMAT(a.created_at, '%m/%d/%Y') as date"),  // Format date to MM/DD/YYYY
            'b.id as deliveryman_id',
            'b.name as deliveryman_name',
            'c.id as purchase_order_id',
            'c.customer_name',
            'd.quantity',
            'e.price',
            'f.id as product_id',
            'f.product_name',
            // Address fields{}
            'g.street',
            'g.barangay',
            'g.zip_code',
            'g.province',
            'g.city'
        )
        ->where('a.status', '=', 'P')
        // ->where('a.user_id', '=', $deliveryman_id) // Filter by specific deliveryman_id (user_id)
        ->orderBy('c.id')
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
            ->unique('product_id') // Choose unique product data based on product_id
            ->values()
            ->toArray()
        ];
    });

    return response()->json($properFormat->values());
    }


}
