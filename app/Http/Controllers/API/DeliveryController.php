<?php

namespace App\Http\Controllers\API;

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
    // Validate the incoming request
    $validator = Validator::make($request->all(), [
        'delivery_no' => 'sometimes|integer',
        'notes' => 'sometimes|string',
        'status' => 'sometimes|in:P,F,S,OD',
        'images' => 'sometimes|array', // Handle multiple images as an array
        'images.*' => 'file|mimes:jpeg,png,jpg,gif,svg|max:2048', // Each image validation
    ]);

    // Return validation errors if they occur
    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    // Start a database transaction
    DB::beginTransaction();
    try {
        // Check if the delivery exists using raw SQL
        $delivery = DB::select('SELECT * FROM deliveries WHERE id = ?', [$id]);

        if (empty($delivery)) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        // Update delivery details using raw SQL
        $dataToUpdate = $request->only(['delivery_no', 'notes', 'status']);
        $dataToUpdate['status'] = 'P'; // Always set 'P'

        DB::update('UPDATE deliveries SET delivery_no = ?, notes = ?, status = ? WHERE id = ?', [
            $dataToUpdate['delivery_no'] ?? $delivery[0]->delivery_no,
            $dataToUpdate['notes'] ?? $delivery[0]->notes,
            $dataToUpdate['status'],
            $id,
        ]);

        // Handle multiple image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // Log image info to check if files are being processed
                Log::info("Processing image: " . $image->getClientOriginalName());

                // Create a unique name for the image
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('public/images', $imageName); // Store the image in 'storage/app/public/images'

                // Make sure the correct URL path is used (accessible via 'storage/')
                $imageUrl = 'storage/images/' . $imageName;

                // Insert the image record using raw SQL
                DB::insert('INSERT INTO images (delivery_id, image_url, date) VALUES (?, ?, NOW())', [
                    $id, // Use the delivery's ID
                    $imageUrl, // Image URL path
                ]);
            }
        }

        DB::commit();

        // Reload the delivery with images using raw SQL
        $deliveryWithImages = DB::select('
            SELECT d.*, i.image_url
            FROM deliveries d
            LEFT JOIN images i ON d.id = i.delivery_id
            WHERE d.id = ?
        ', [$id]);

        // Log the loaded delivery images
        Log::info($deliveryWithImages);

        // Return a success message along with the updated delivery and its images
        return response()->json([
            'message' => 'Delivery updated successfully!',
            'delivery' => $deliveryWithImages,
        ], 200);
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
