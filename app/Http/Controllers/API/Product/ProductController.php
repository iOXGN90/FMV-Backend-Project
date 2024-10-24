<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends BaseController
{
    // Display a listing of the Product.
    public function index()
    {
        // Eager load the category relationship to avoid N+1 query problem
        $products = Product::with('category')->get();

        // Create a custom response array
        $response = $products->map(function($product) {
            return [
                'product_id' => $product->id,
                'category_name' => $product->category->category_name,
                'product_name' => $product->product_name,
                'original_price' => $product->original_price,
                'quantity' => $product->quantity,
            ];
        });

        return response()->json($response);
    }


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

        return response()->json($response, 201);
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

    // Update the specified Product in storage.
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'original_price' => 'required|numeric',
            'Product_name' => 'required|string|max:255',
            'quantity' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $Product = Product::find($id);

        if (is_null($Product)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $Product->update($request->all());
        return response()->json($Product);
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
