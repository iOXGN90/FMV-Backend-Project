<?php

namespace App\Http\Controllers\API\Test;

use App\Models\Test\UploadImageSample;
use Illuminate\Http\Request;

class UploadImage
{
    public function store(Request $request)
    {
        // Validate the incoming image file from the request
        $request->validate([
            // 'delivery_id'
            'url' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle the file upload and move to 'public/images' folder
        $imageName = time() . '.' . $request->url->extension();
        $request->url->move(public_path('images'), $imageName);

        // Save the file path to the database
        UploadImageSample::create([
            'url' => 'images/' . $imageName,
        ]);

        // Return a JSON response with success message
        return response()->json([
            'success' => true,
            'message' => 'Image uploaded and product created successfully!',
            'image_url' => 'images/' . $imageName,
        ]);
    }
}
