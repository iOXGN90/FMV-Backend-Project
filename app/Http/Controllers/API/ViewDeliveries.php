<?php

namespace App\Http\Controllers\API;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ViewDeliveries extends BaseController
{
    public function get_employees_with_deliveries_by_status($status)
    {
        $employees = User::whereHas('deliveries', function ($query) use ($status) {
            $query->where('status', $status);
        })->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => "No employees with the status of '{$status}' deliveries found"], 404);
        }

        return response()->json($employees, 200);
    }

    //* Fetch "pending" Status deliveries
    public function pending_deliveries(){
        return $this->get_employees_with_deliveries_by_status('P');
    }

    //* Fetch "On Delivery" Status deliveries
    public function on_delivery(){
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
