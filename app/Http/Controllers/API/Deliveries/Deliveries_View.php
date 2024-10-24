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

class Deliveries_View extends BaseController
{
    public function get_employees_with_deliveries_by_status($status)
{
    // Fetch employees based on delivery status and include product details and related products
    $results = DB::table('delivery_products as a')
        ->join('deliveries as b', 'b.id', '=', 'a.delivery_id')
        ->join('product_details as c', 'c.id', '=', 'a.product_details_id')
        ->join('users as d', 'd.id', '=', 'b.user_id')
        ->join('purchase_orders as e', 'e.id', '=', 'c.purchase_order_id')
        ->join('products as f', 'f.id', '=', 'c.product_id')
        ->join('addresses as g', 'g.id', '=', 'e.address_id')
        ->select(
            'c.purchase_order_id',
            'c.product_id',
            'f.product_name',
            'a.quantity',
            'd.id as employee_id',
            'd.name as employee_name',
            'd.number as employee_number',
            'g.street',
            'g.barangay',
            'g.zip_code',
            'g.province',
            'b.status'
        )
        ->where('b.status', '=', $status)
        ->orderBy('c.purchase_order_id')
        ->get();

    if ($results->isEmpty()) {
        return response()->json(['message' => "No employees with the status of '{$status}' deliveries found"], 404);
    }

    $groupedResults = $results->groupBy('purchase_order_id');

    $properForm = $groupedResults->map(function($deliveryData) {
        // Get the first entry from the group to access common data
        $firstDeliveryData = $deliveryData->first();

        // Assume $firstDeliveryData has a 'status' field
        if ($firstDeliveryData->status === 'P') {
            // Logic for when status is 'P'
            return [
                'purchase_order_id' => $firstDeliveryData->purchase_order_id,
                'employee_id' => $firstDeliveryData->employee_id,
                'employee_name' => $firstDeliveryData->employee_name,
                'employees\'s_number' => $firstDeliveryData->employee_number,
                'address' => [
                    'street' => $firstDeliveryData->street,
                    'barangay' => $firstDeliveryData->barangay,
                    'province' => $firstDeliveryData->province,
                ],
                // Collect all products under this purchase order
                'Products Delivered' => $deliveryData->map(function($product) {
                    return [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'quantity' => $product->quantity
                    ];
                })
                ->unique('product_id') //! <---- This will choose unique data of product id; clone data will be disregard
                ->toArray()
            ];
        } else if ($firstDeliveryData->status === 'S') {
            // Logic for when status is not 'P' (if needed)
            return null; // You can return null, an empty array, or something else
        }
    })->filter(); // Use `filter()` to remove any null values if necessary

    return response()->json($properForm);


}

    //* Fetch "pending" Status deliveries
    //! PS: PENDINGS - ARE CREATED PURCHASE ORDER THAT ARE AFTER THE DELIVERY MAN ATTEMPTS TO DELIVER THE PRODUCTS WITH REMARKS. Which later on, will be reviewed by the admin of what did happened; and then acknowledges.

    public function pending_deliveries(){
        return $this->get_employees_with_deliveries_by_status('P');
    }


    //* Fetch "successful" Status deliveries
    public function successful_deliveries(){
        return $this->get_employees_with_deliveries_by_status('S');
    }

    //* Fetch "failed" Status deliveries
    public function failed_deliveries(){
        return $this->get_employees_with_deliveries_by_status('F');
    }


//! TEST AREA ------------------
    public function sample() {

        $results = DB::table('delivery_products as a')
        ->join('product_details as b', 'a.product_details_id', '=', 'a.id')
        ->join('purchase_orders as c', 'b.purchase_order_id', '=', 'c.id')
        ->join('products as d', 'b.product_id', '=', 'd.id')
        ->join('deliveries as e', 'a.delivery_id', '=', 'a.id')
        ->select(
            'c.id as purchase_order_id',
            'e.id as delivery_id',
            'b.product_id',
            'd.product_name',
            'b.quantity as Total_Quantity',
            'a.quantity as delivered_quantity'
        )
        // ->where('a.id', '=', $id)
        ->where('c.sale_type_id', '=', 1)
        ->distinct() //<--- is this a valid answer, GPT?
        // ->groupBy('b.id', 'd.id', 'a.id', 'b.quantity', 'd.product_name')
        ->orderBy('c.id')
        ->get();
        return response()->json($results);

    //     $results = DB::table('products as a')
    //     ->join('product_details as b', 'b.product_id', '=', 'a.id')
    //     ->join('purchase_orders as c', 'c.id', '=', 'b.purchase_order_id')
    //     ->join('deliveries as d', 'c.id', '=', 'd.purchase_order_id')
    //     ->join('delivery_products as e', 'e.delivery_id', '=', 'd.id')
    //     ->select(
    //         'c.id as purchase_order_id',
    //         'c.customer_name',
    //         'd.id as delivery_id',
    //         'a.id as product_id',
    //         'a.product_name'
    //     )
    //     ->where('d.status', '=', 'OD')
    //     ->distinct() // Ensures that only distinct records are returned
    //     ->orderBy('c.id')
    //     ->get();

    }
//! TEST AREA ------------------


    public function on_delivery()
    {
        $data = DB::table('deliveries as a')
            ->join('users as b', 'b.id', '=', 'a.user_id')
            ->join('purchase_orders as c', 'c.id', '=', 'a.purchase_order_id')
            ->join('delivery_products as d', 'a.id', '=', 'd.delivery_id')
            ->join('product_details as e', 'c.id', '=', 'e.purchase_order_id')
            ->join('products as f', 'f.id', '=', 'e.product_id')
            ->join('addresses as g', 'c.address_id', '=', 'g.id') // Correct join here
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
                    'g.street',
                    'g.barangay',
                    'g.zip_code',
                    'g.province',
                    'g.city'
            )
            ->where('a.status', '=', 'OD')
            // ->where('c.id', '=', 4)
            // ->distinct() //! <--- This will choose data that are unique; same data will be not shown
            ->orderBy('c.id')
            ->get();

            $groupedOrders = $data->groupBy('purchase_order_id');

            $properFormat = $groupedOrders->map(function($orderedGroup){
                $firstOrder = $orderedGroup->first();
                return [
                    'purchase_order_id' => $firstOrder->purchase_order_id,
                    'delivery_id' => $firstOrder->delivery_id,
                    'delivery_no' => $firstOrder->delivery_no,
                    'delivery_id' => $firstOrder->delivery_id,
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
                    'products' => $orderedGroup->map(function($item){
                        return[
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ];
                    })
                    ->unique('product_id') //! <---- This will choose unique data of product id; clone data will be disregard
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
                // Address fields
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
