<?php

namespace App\Http\Controllers\API;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AssignEmployeeController extends BaseController
{
    // Assign employee to a purchase order
    public function assign_employee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $purchaseOrder = PurchaseOrder::find($request->input('purchase_order_id'));
        $employee = User::find($request->input('user_id'));

        if (!$purchaseOrder) {
            return response()->json(['error' => 'Purchase order not found'], 404);
        }

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check if the user being assigned is an employee
        if ($employee->user_type_id != 2) {
            return response()->json(['error' => 'The user being assigned must be an employee'], 403);
        }

        // Check if the user is already assigned to the purchase order
        $existingDelivery = Delivery::where('purchase_order_id', $purchaseOrder->id)
            ->where('user_id', $employee->id)
            ->first();

        if ($existingDelivery) {
            return response()->json(['error' => 'This employee is already assigned to the purchase order'], 400);
        }

        DB::beginTransaction();
        try {
            // Create the delivery record
            $deliveryData = [
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $employee->id,
                'delivery_no' => Delivery::where('purchase_order_id', $purchaseOrder->id)->max('delivery_no') + 1,
                'status' => 'P', // Default status for delivery
                'no_of_damage' => 0, // Default value
                'notes' => $request->input('notes', '') // Optional notes
            ];

            Delivery::create($deliveryData);

            DB::commit();
            return response()->json(['message' => 'Employee assigned successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while assigning the employee: ' . $e->getMessage()], 500);
        }
    }

    // Remove assigned employee from a delivery
    public function remove_employee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_id' => 'required|exists:deliveries,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $admin = auth()->user();

        // Check if the user is an admin
        if ($admin->user_type_id != 1) {
            return response()->json(['error' => 'User does not have the correct permissions to remove an assignment'], 403);
        }

        $delivery = Delivery::find($request->input('delivery_id'));

        if (is_null($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        DB::beginTransaction();
        try {
            $delivery->delete();
            DB::commit();
            return response()->json(['message' => 'Employee unassigned successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while removing the assignment: ' . $e->getMessage()], 500);
        }
    }


    //* Show All Pending Employee
    public function get_employees_with_pending_deliveries()
    {
        $employees = User::whereHas('deliveries', function ($query) {
            $query->where('status', 'P');
        })->get();

        return response()->json($employees);
    }



    //* Show All Successful Employee
    public function get_employees_with_successful_deliveries()
    {
        // Fetch employees with deliveries that have a 'S' status (Success)
        $employeesWithSuccessfulDeliveries = User::whereHas('deliveries', function ($query) {
            $query->where('status', 'S');
        })->get();

        if ($employeesWithSuccessfulDeliveries->isEmpty()) {
            return response()->json(['message' => 'No employees with successful deliveries found'], 404);
        }

        return response()->json($employeesWithSuccessfulDeliveries, 200);
    }


}
