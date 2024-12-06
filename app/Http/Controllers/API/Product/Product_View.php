<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class Product_View extends BaseController
{

    public function index(Request $request)
    {
        $query = Product::with('category');

        // Filter by multiple categories
        if ($request->has('categories') && !empty($request->input('categories'))) {
            $categories = $request->input('categories'); // Expect an array
            $query->whereHas('category', function ($query) use ($categories) {
                $query->whereIn('category_name', $categories);
            });
        }

        // Calculate the total value of assets before applying pagination
        $totalValue = $query->sum(DB::raw('original_price * quantity'));

        // Add pagination
        $products = $query->paginate(30);

        // Format the response
        $formattedProducts = collect($products->items())->map(function ($product) {
            return [
                'product_id' => $product->id,
                'category_name' => $product->category->category_name ?? 'Uncategorized', // Fallback for missing category
                'product_name' => $product->product_name,
                'original_price' => number_format($product->original_price, 2, '.', ''), // Ensure 2 decimal places
                'quantity' => $product->quantity,
            ];
        });

        return response()->json([
            'products' => $formattedProducts,
            'pagination' => [
                'total' => $products->total(),
                'perPage' => $products->perPage(),
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
            ],
            'totalValue' => number_format($totalValue, 2, '.', ''), // Include total value for all products
        ]);
    }







    public function index_overview(Request $request)
    {
        // Validate or set default values
        $maxQuantity = $request->input('maxQuantity', 1000);  // Default to 100 if not specified

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
