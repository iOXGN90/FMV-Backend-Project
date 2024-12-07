<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends BaseController
{

    // create a newly Product in storage.
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'original_price' => 'required|numeric',
            'product_name' => 'required|string|max:255',
            'quantity' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::create($request->all());

        // Load the category relationship
        $product->load('category');

        // Create a custom response array
        $response = [
            'product_id' => $product->id,
            'category_name' => $product->category->category_name,
            'product_name' => $product->product_name,
            'quantity' => $product->quantity,
        ];

        return response()->json($response, 200, [], JSON_UNESCAPED_SLASHES);
    }


    // Display the specified Product.
    public function show($id)
    {
        $Product = Product::find($id);

        if (is_null($Product)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($Product);
    }

    public function update(Request $request, $id)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'original_price' => 'required|numeric',
            'product_name' => 'required|string|max:255', // Ensure the field name matches the database schema
            'quantity' => 'required|integer',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Find the product or fail
            $Product = Product::findOrFail($id);

            // Update the product with only allowed fields
            $Product->update($request->only(['category_id', 'original_price', 'product_name', 'quantity']));

            // Return a success response
            return response()->json(['success' => true, 'data' => $Product], 200);

        } catch (ModelNotFoundException $e) {
            // Handle the case where the product is not found
            return response()->json(['message' => 'Product not found'], 404);
        } catch (\Exception $e) {
            // Handle other unexpected errors
            return response()->json(['message' => 'An error occurred while updating the product'], 500);
        }
    }


    // Remove the specified Product from storage.
    public function destroy($id)
    {
        $Product = Product::find($id);

        if (is_null($Product)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $Product->delete();
        return response()->json(null, 204);
    }
}
