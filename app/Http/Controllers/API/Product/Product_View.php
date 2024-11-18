<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Product_View extends BaseController
{

    public function index()
    {
        // Eager load the category relationship to avoid N+1 query problem
        $products = Product::with('category')->paginate(20);

        // Transform the paginated items to create a custom response array
        $response = collect($products->items())->map(function($product) {
            return [
                'product_id' => $product->id,
                'category_name' => $product->category->category_name,
                'product_name' => $product->product_name,
                'original_price' => number_format($product->original_price, 2, '.', ''),
                'quantity' => $product->quantity,
            ];
        });

        return response()->json([
            'products' => $response,
            'pagination' => [
                'total' => $products->total(),
                'perPage' => $products->perPage(),
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
            ]
        ]);
    }

    public function index_overview(Request $request)
    {
        // Validate or set default values
        $maxQuantity = $request->input('maxQuantity', 450);  // Default to 100 if not specified

        // Fetch products where quantity is less than or equal to $maxQuantity
        $products = Product::where('quantity', '<=', $maxQuantity)
                    ->orderBy('quantity', 'asc') // Order by quantity to get the lowest first
                    ->take(10) // Limit the results to 10
                    ->get();

        // Return the products as JSON
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
