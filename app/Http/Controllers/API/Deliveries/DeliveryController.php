<?php

namespace App\Http\Controllers\API\Deliveries;

use App\Http\Controllers\API\BaseController;

use App\Models\Delivery;
use App\Models\Image;
use App\Models\User;
use App\Models\DeliveryProduct;
use App\Models\Product;
use App\Models\Returns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psy\Readline\Hoa\Console;

class DeliveryController extends BaseController
{


    public function update_delivery(Request $request, $id)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
            'images' => 'sometimes|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,svg',
            'damages' => 'sometimes|array',
            'damages.*.product_id' => 'required_with:damages.*.no_of_damages|exists:delivery_products,product_id',
            'damages.*.no_of_damages' => 'required_with:damages.*.product_id|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        DB::beginTransaction();
        try {
            $delivery = Delivery::find($id);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Update notes
            $delivery->notes = $request->input('notes', 'no comment');

            // Handle damages and returns status update
            $hasDamages = false;
            if ($request->has('damages')) {
                foreach ($request->input('damages') as $damage) {
                    $deliveryProduct = DeliveryProduct::where('delivery_id', $delivery->id)
                        ->where('product_id', $damage['product_id'])
                        ->first();

                    if ($deliveryProduct) {
                        $deliveryProduct->update(['no_of_damages' => $damage['no_of_damages']]);

                        // Update or create a return record
                        $return = Returns::firstOrCreate(
                            ['delivery_product_id' => $deliveryProduct->id],
                            ['reason' => $request->input('reason', 'Damage reported')]
                        );

                        if ($damage['no_of_damages'] > 0) {
                            $return->status = 'P'; // Set to Pending if damages exist
                            $hasDamages = true;
                        } else {
                            $return->status = 'NR'; // No Return if no damages
                        }
                        $return->save();
                    }
                }
            }

            // Always update `delivered_at` regardless of damages
            if (!$delivery->delivered_at) {
                $delivery->delivered_at = now(); // Record the current timestamp
            }

            // Set delivery status
            if ($hasDamages) {
                $delivery->status = 'OD'; // Keep the delivery as On Delivery if there are damages
            } else {
                $delivery->status = 'P'; // Set status to Pending if no damages
            }

            $delivery->save();
            DB::commit();

            // Fetch related products with damages information
            $products = $delivery->deliveryProducts()->with('product')->get()->map(function ($deliveryProduct) {
                return [
                    'product_id' => $deliveryProduct->product_id,
                    'quantity' => $deliveryProduct->quantity,
                    'no_of_damages' => $deliveryProduct->no_of_damages,
                ];
            });

            // Fetch images related to the delivery
            $baseUrl = config('app.url');
            $images = $delivery->images->map(function ($image) use ($baseUrl) {
                return [
                    'id' => $image->id,
                    'url' => $baseUrl . '/' . $image->url,
                    'created_at' => $image->created_at->format('m/d/Y H:i'),
                ];
            });

            return response()->json([
                'message' => 'Delivery updated successfully!',
                'delivery' => [
                    'id' => $delivery->id,
                    'purchase_order_id' => $delivery->purchase_order_id,
                    'user_id' => $delivery->user_id,
                    'delivery_no' => $delivery->delivery_no,
                    'notes' => $delivery->notes,
                    'status' => $delivery->status,
                    'delivered_at' => $delivery->delivered_at, // Include delivered_at in the response
                    'products' => $products,
                    'images' => $images,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }





    public function final_update(Request $request, $delivery_id)
    {
        // Validate the reason field in the request
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        // Start a database transaction to ensure all queries succeed or fail together
        DB::beginTransaction();
        try {
            // Find the delivery by ID
            $delivery = Delivery::find($delivery_id);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Update delivery status to 'P' (Pending)
            $delivery->status = 'P';
            $delivery->save();

            // Update all related returns to 'S' (Success) where the delivery ID matches
            Returns::whereHas('deliveryProduct', function ($query) use ($delivery) {
                $query->where('delivery_id', $delivery->id);
            })->update([
                'status' => 'S',
                'reason' => $request->reason, // Set the reason provided in the request
            ]);

            // Commit the transaction
            DB::commit();

            return response()->json(['message' => 'Delivery and returns updated successfully!'], 200);
        } catch (\Exception $e) {
            // Rollback transaction if something went wrong
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
         if ($employee->user_type_id != 2) {
             return response()->json(['error' => 'User does not have the correct permissions to view deliveries'], 403);
         }

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
         if ($employee->user_type_id != 2) {
             return response()->json(['error' => 'User does not have the correct permissions to view deliveries'], 403);
         }

         // Retrieve deliveries with status 'S' (Successful)
         $deliveries = Delivery::with('purchaseOrder', 'user', 'deliveryProducts.productDetail.product', 'images')
             ->where('user_id', $employee->id)
             ->where('status', 'S')
             ->get();

         return response()->json($deliveries);
     }

}
