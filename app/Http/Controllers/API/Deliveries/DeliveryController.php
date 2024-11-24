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


    //! This code will run once the delivery man sends the status of the delivery
    // Update a specific delivery by ID
    public function update_delivery(Request $request, $id)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string',
            'images' => 'sometimes|array', // Update to handle multiple images
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate each image in the array
            'damages' => 'sometimes|array',
            'damages.*.delivery_products_id' => 'required_with:damages|exists:delivery_products,id',
            'damages.*.no_of_damages' => 'integer|min:0', // No longer required to allow it to default to 0
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
            $delivery->status = 'P'; // Set the status to Pending

            // Handle multiple image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $imageName = time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images'), $imageName);

                    // Create a new Image instance and save it
                    $image = new Image();
                    $image->url = 'images/' . $imageName;
                    $image->delivery_id = $id; // Ensure this is not null
                    $image->save();
                }
            }

            // Handle damages
            if ($request->has('damages')) {
                foreach ($request->input('damages') as $damage) {
                    Damage::updateOrCreate(
                        [
                            'delivery_products_id' => $damage['delivery_products_id']
                        ],
                        [
                            'delivery_id' => $id, // Set the delivery ID here
                            'no_of_damages' => $damage['no_of_damages'] ?? 0, // Use 0 if not provided
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            $delivery->save();
            DB::commit();

            return response()->json([
                'message' => 'Delivery updated successfully!',
                'delivery' => $delivery,
                'status' => 'P', // Reflect the updated status
                'images' => Image::where('delivery_id', $id)->get(), // Return all images related to this delivery
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
