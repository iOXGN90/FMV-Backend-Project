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

class Deliveries_View_OnDelivery_EmployeeID extends BaseController
{

    public function on_delivery_by_deliveryman_id($deliveryman_id, Request $request)
    {
        $status = $request->query('status');

        if (!$status) {
            return response()->json([
                'error' => 'Status is required'
            ], 400); // Bad request error
        }

        // Validate status
        $validStatuses = ['OD', 'F', 'S']; // Define valid statuses
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'error' => 'Invalid status'
            ], 400); // Invalid status error
        }

        // Build the query
        $data = DB::table('deliveries as a')
            ->join('users as b', 'b.id', '=', 'a.user_id')
            ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id')
            ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id')
            ->join('products as f', 'f.id', '=', 'd.product_id')
            ->join('product_details as e', 'f.id', '=', 'e.product_id')
            ->join('addresses as g', 'g.id', '=', 'c.address_id')
            ->select(
                'a.id as delivery_id',
                'a.delivery_no',
                'a.status',
                DB::raw("DATE_FORMAT(a.created_at, '%m/%d/%Y') as date"),
                'b.id as deliveryman_id',
                'b.name as deliveryman_name',
                'd.id as delivery_product_id',
                'c.id as purchase_order_id',
                'c.customer_name',
                'd.quantity',
                'd.no_of_damages',
                'e.price',
                'f.id as product_id',
                'f.product_name',
                'g.street',
                'g.barangay',
                'g.zip_code',
                'g.province',
                'g.city'
            )
            ->where('a.status', '=', $status)
            ->where('a.user_id', '=', $deliveryman_id)
            ->orderBy('c.id')
            ->get();

        // Handle empty results
        if ($data->isEmpty()) {
            return response()->json([
                'message' => 'No deliveries found for the given status.'
            ], 404);
        }

        // Group by purchase_order_id
        $groupedOrders = $data->groupBy('purchase_order_id');

        // Format data for the response
        $formattedData = $groupedOrders->map(function ($group) {
            $first = $group->first();

            // Check if there are any damages
            $hasDamages = $group->contains(function ($item) {
                return $item->no_of_damages > 0;
            });

            return [
                'purchase_order_id' => $first->purchase_order_id,
                'delivery_id' => $first->delivery_id,
                'delivery_no' => $first->delivery_no,
                'deliveryman_id' => $first->deliveryman_id,
                'delivery_product_id' => $first->delivery_product_id,
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
                'has_damages' => $hasDamages,
                'products' => $group->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'no_of_damages' => $item->no_of_damages,
                        'price' => $item->price,
                    ];
                })->values(),
            ];
        });

        return response()->json($formattedData->values());
    }

}
