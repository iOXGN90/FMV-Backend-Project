<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Http\Controllers\API\BaseController;

use app\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrder_ViewWalkIns extends BaseController
{
    public function index_walk_in()
    {
        try {
            // Fetch raw data from the database
            $purchaseOrders = DB::table('purchase_orders as a')
                ->join('addresses as b', 'b.id', '=', 'a.address_id')
                ->join('product_details as c', 'c.purchase_order_id', '=', 'a.id')
                ->join('products as p', 'p.id', '=', 'c.product_id') // Join products for product name/details
                ->select(
                    'a.id as purchase_order_id',
                    'a.customer_name',
                    'a.status',
                    'a.created_at as purchase_date',
                    'b.street',
                    'b.barangay',
                    'b.city',
                    'b.province',
                    'b.zip_code',
                    'c.product_id',
                    'c.price',
                    'c.quantity',
                    'p.product_name'
                )
                ->where('a.sale_type_id', 2)
                ->get();

            // Group data by `purchase_order_id`
            $groupedOrders = [];
            foreach ($purchaseOrders as $order) {
                if (!isset($groupedOrders[$order->purchase_order_id])) {
                    $groupedOrders[$order->purchase_order_id] = [
                        'purchase_order_id' => $order->purchase_order_id,
                        'customer_name' => $order->customer_name,
                        'status' => $order->status,
                        'purchase_date' => $order->purchase_date,
                        'address' => [
                            'street' => $order->street,
                            'barangay' => $order->barangay,
                            'city' => $order->city,
                            'province' => $order->province,
                            'zip_code' => $order->zip_code,
                        ],
                        'products' => []
                    ];
                }
                $groupedOrders[$order->purchase_order_id]['products'][] = [
                    'product_id' => $order->product_id,
                    'product_name' => $order->product_name,
                    'price' => $order->price,
                    'quantity' => $order->quantity
                ];
            }

            // Convert the associative array to an indexed array for JSON response
            $groupedOrders = array_values($groupedOrders);

            return response()->json($groupedOrders, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }





        //* End View WalkIn

        //* Start View WalkIn ID
        public function show($id)
        {
            $walkIn = PurchaseOrder::with('address', 'productDetails')
                ->where('sale_type_id', 2)
                ->find($id);

            if (is_null($walkIn)){
                return response()->json(['message' => 'WalkIn [Purchase Order] is not found'], 404);
            }
        }
        //* End View WalkIn ID
}
