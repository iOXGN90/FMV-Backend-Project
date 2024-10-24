<?php

namespace App\Http\Controllers\API\Deliveries;

use App\Http\Controllers\API\BaseController;

use App\Models\Delivery;
use App\Models\Image;
use App\Models\User;
use App\Models\DeliveryProduct;
use App\Models\Damage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psy\Readline\Hoa\Console;

class DeliveryController extends BaseController
{
    public function sample_upload(Request $request)
    {
        // Validate the file input and delivery_id
        $request->validate([
            'image' => 'required|mimes:jpg,jpeg,png|max:2048', // Limit file types and size
            'delivery_id' => 'required|integer', // Ensure delivery_id is provided
        ]);

        // Handle the file upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('images', $filename, 'public'); // Save to 'storage/app/public/images'

            // Insert into the database using raw SQL
            DB::insert('INSERT INTO images (delivery_id, image_url, date) VALUES (?, ?, NOW())', [
                $request->input('delivery_id'), // Foreign key to the delivery table
                $path, // Path where the image is stored
            ]);

            return back()->with('success', 'Image uploaded successfully!');
        }

        return back()->with('error', 'No file was uploaded.');
    }



    public function update_delivery_status_OD($id, $newStatus = 'OD')
    {
        // Perform a raw SQL update query
        DB::update('UPDATE deliveries SET status = ? WHERE id = ?', [$newStatus, $id]);

        return response()->json([
            'message' => 'Delivery status updated successfully!',
            'delivery_id' => $id,
            'new_status' => $newStatus
        ], 200);
    }

    public function update_delivery_status_P(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
        'status' => 'required|in:P,F,S,OD', // Validating status input
        'notes' => 'nullable|string',      // Allow notes to be null or string
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    // Extract status and notes from the request
    $newStatus = 'P';
    $notes = $request->input('notes');

    // Perform a raw SQL update query to update both status and notes
    DB::update('UPDATE deliveries SET status = ?, notes = ? WHERE id = ?', [$newStatus, $notes, $id]);

    return response()->json([
        'message' => 'Delivery status and notes updated successfully!',
        'delivery_id' => $id,
        'new_status' => $newStatus,
        'notes' => $notes
    ], 200);
    }

    //! This code will run once the delivery man sends the status of the delivery
    // Update a specific delivery by ID
    public function update_delivery(Request $request, $id)
    {
        // DB::update('UPDATE deliveries SET notes = ? WHERE id = ?', [
        //     $request->input('notes'), $id
        // ]);

        // return response()->json([
        //     'message' => 'Delivery updated successfully!',
        //     'inputted_notes' => $request->input('notes')  // Reflect the updated notes back in the response
        // ]);

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string',
            'url' => 'sometimes|file|mimes:jpeg,png,jpg,gif,svg|max:2048', // Single image validation
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Start a database transaction
        DB::beginTransaction();
        try {
            // Check if the delivery exists
            $delivery = Delivery::find($id);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Update notes
            $delivery->notes = $request->input('notes');
            $delivery->status = 'P';

            // Handle single image upload
            if ($request->hasFile('url') && $id) {
                $file = $request->file('url');
                $imageName = time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images'), $imageName);

                // Assuming Image model is correctly set up to handle delivery_id
                $image = new Image();
                $image->url = 'images/' . $imageName;
                $image->delivery_id = $id;  // ensure this is not null
                $image->save();
            }

            $delivery->save();
            DB::commit();

            return response()->json([
                'message' => 'Delivery updated successfully!',
                'delivery' => $delivery,
                'changed_status' => 'P',
                'image' => $image // Add the image response
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while updating the delivery: ' . $e->getMessage()], 500);
        }
    }


    private function uploadImage($file, $delivery)
    {
        try {
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images'), $imageName);
            $delivery->image_url = 'images/' . $imageName; // Ensure 'image_url' is your database column
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }



//     public function uploadImage($file, $delivery_id) {
//         $imageName = time() . '.' . $file->getClientOriginalExtension();
//         $file->move(public_path('images'), $imageName); // Moves file to public/images

//         $delivery = Delivery::find($delivery_id);
//         if ($delivery) {
//             $delivery->image_url = 'images/' . $imageName;
//             $delivery->save();
//         }
//         return [
//             'success' => true,
//             'image_url' => asset('images/' . $imageName),
//         ];
// }


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


     private function logImageDetails($image, $path)
     {
         Log::info('Image uploaded:', [
             'original_name' => $image->getClientOriginalName(), // Log original file name
             'size' => $image->getSize(), // Log size in bytes
             'mime_type' => $image->getClientMimeType(), // Log mime type (e.g., image/jpeg)
             'stored_path' => $path // Log the path where the image is stored
         ]);
     }
}
