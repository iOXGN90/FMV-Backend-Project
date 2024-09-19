<?php

namespace App\Http\Controllers\API;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Deliveries_View extends BaseController
{
    public function get_employees_with_deliveries_by_status($status)
    {
        // Fetch employees based on delivery status
        $employees = User::with(['deliveries' => function ($query) use ($status) {
            $query->where('status', $status);
        }, 'deliveries.deliveryProducts.productDetail.product']) // Eager load related deliveries and products
        ->whereHas('deliveries', function ($query) use ($status) {
            $query->where('status', $status);
        })->get();

        if ($status === 'OD'){
             // Join the relevant tables and select necessary columns
            $onDeliveryOrders = DB::table('product_details')
            ->join('delivery_products', 'product_details.id', '=', 'delivery_products.product_details_id')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('users', 'deliveries.user_id', '=', 'users.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->where('deliveries.status', '=', 'OD') // Only fetch deliveries with 'OD' status
            ->select(
                'product_details.purchase_order_id',
                'users.id as user_id',
                'deliveries.status as delivery_status',
                'users.name',
                'users.number',
                'users.username',
                'products.id as product_id',
                'products.product_name',
                'delivery_products.quantity'
            )
            ->get();

            // Format the results as necessary
            $formattedOrders = $onDeliveryOrders->map(function ($order) {
                return [
                    'purchase_order_id' => $order->purchase_order_id,
                    'user_id' => $order->user_id,
                    'delivery_status' => $order->delivery_status,
                    'name' => $order->name,
                    'number' => $order->number,
                    'username' => $order->username,
                    'delivery' => [
                        [
                            'product_id' => $order->product_id,
                            'product_details.product_name' => $order->product_name,
                            'product_details.quantity' => $order->quantity,
                        ]
                    ],
                ];
            });

            return response()->json($formattedOrders);
        }

        if ($employees->isEmpty()) {
            return response()->json(['message' => "No employees with the status of '{$status}' deliveries found"], 404);
        }

        // For other statuses, return basic employee details with their deliveries
        return response()->json($employees, 200);
    }

    //* Fetch "pending" Status deliveries
    public function pending_deliveries(){
        return $this->get_employees_with_deliveries_by_status('P');
    }

    public function on_delivery()
    {
        return $this->get_employees_with_deliveries_by_status('OD');
    }

    //* Fetch "successful" Status deliveries
    public function successful_deliveries(){
        return $this->get_employees_with_deliveries_by_status('S');
    }

    //* Fetch "failed" Status deliveries
    public function failed_deliveries(){
        return $this->get_employees_with_deliveries_by_status('F');
    }
}
