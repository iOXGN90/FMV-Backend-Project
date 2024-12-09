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
                'c.id as purchase_order_id',  // Ensure this is included
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
            ->orderBy('a.created_at')  // Order by created_at, so we get the latest deliveries
            ->get();

        // Handle empty results
        if ($data->isEmpty()) {
            return response()->json([
                'message' => 'No deliveries found for the given status.'
            ], 404);
        }

        // Group the data by delivery_id
        $groupedData = $data->groupBy('delivery_id');

        // Format the data
        $formattedData = $groupedData->map(function ($deliveryItems) {
            // Assume all items belong to the same delivery
            $firstItem = $deliveryItems->first();

            // Check if there are any damages in the products of this delivery
            $hasDamages = $deliveryItems->pluck('no_of_damages')->sum() > 0;

            // Format the grouped delivery
            return [
                'delivery_id' => $firstItem->delivery_id,
                'delivery_no' => $firstItem->delivery_no,
                'deliveryman_id' => $firstItem->deliveryman_id,
                'deliveryman_name' => $firstItem->deliveryman_name,
                'purchase_order_id' => $firstItem->purchase_order_id,  // Show purchase_order_id
                'customer_name' => $firstItem->customer_name,
                'status' => $firstItem->status,
                'date' => $firstItem->date,
                'address' => [
                    'street' => $firstItem->street,
                    'barangay' => $firstItem->barangay,
                    'zip_code' => $firstItem->zip_code,
                    'province' => $firstItem->province,
                    'city' => $firstItem->city,
                ],
                'has_damages' => $hasDamages,
                'products' => $deliveryItems->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'no_of_damages' => $item->no_of_damages,
                        'price' => $item->price,
                    ];
                })->toArray(),
            ];
        });

        return response()->json($formattedData);
    }






}
