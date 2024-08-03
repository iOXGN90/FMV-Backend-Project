<?php

namespace App\Http\Controllers\API;

use App\Models\Delivery;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliveryController extends BaseController
{
    // Get all deliveries
    public function index()
    {
        $deliveries = Delivery::with('purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images')->get();
        return response()->json($deliveries);
    }

    // Update a specific delivery by ID
    public function update_delivery(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'delivery_no' => 'sometimes|integer',
            'notes' => 'sometimes|string',
            'status' => 'sometimes|in:P,F,S',
            'no_of_damage' => 'sometimes|integer',
            'images' => 'sometimes|array',
            'images.*' => 'sometimes|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $delivery = Delivery::find($id);

        if (is_null($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        DB::beginTransaction();
        try {
            $delivery->update($request->only(['delivery_no', 'notes', 'status', 'no_of_damage']));

            // Handle image uploads
            if ($request->has('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $path = $imageFile->store('images', 'public');
                    Image::create([
                        'delivery_id' => $delivery->id,
                        'image_url' => $path
                    ]);
                }
            }

            DB::commit();
            return response()->json($delivery->load(['purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while updating the delivery: ' . $e->getMessage()], 500);
        }
    }

    // Get a specific delivery by ID
    public function show($id)
    {
        $delivery = Delivery::with('purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images')->find($id);

        if (is_null($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        return response()->json($delivery);
    }

    // Delete a specific delivery by ID
    public function destroy($id)
    {
        $delivery = Delivery::find($id);

        if (is_null($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        $delivery->delete();
        return response()->json(null, 204);
    }

     // Get pending deliveries for a specific employee
     public function my_pending_deliveries(Request $request)
     {
         $validator = Validator::make($request->all(), [
             'user_id' => 'required|exists:users,id',
         ]);

         if ($validator->fails()) {
             return response()->json($validator->errors(), 400);
         }

         $employee = User::find($request->input('user_id'));

         // Ensure the user is an employee
         // if ($employee->user_type_id != 2) {
         //     return response()->json(['error' => 'User does not have the correct permissions to view deliveries'], 403);
         // }

         // Retrieve deliveries with status 'P' (Pending)
         $deliveries = Delivery::with('purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images')
             ->where('user_id', $employee->id)
             ->where('status', 'P')
             ->get();

         return response()->json($deliveries);
     }

     // Get successful deliveries for a specific employee
     public function my_successful_deliveries(Request $request)
     {
         $validator = Validator::make($request->all(), [
             'user_id' => 'required|exists:users,id',
         ]);

         if ($validator->fails()) {
             return response()->json($validator->errors(), 400);
         }

         $employee = User::find($request->input('user_id'));

         // Ensure the user is an employee
         // if ($employee->user_type_id != 2) {
         //     return response()->json(['error' => 'User does not have the correct permissions to view deliveries'], 403);
         // }

         // Retrieve deliveries with status 'S' (Successful)
         $deliveries = Delivery::with('purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images')
             ->where('user_id', $employee->id)
             ->where('status', 'S')
             ->get();

         return response()->json($deliveries);
     }
}
