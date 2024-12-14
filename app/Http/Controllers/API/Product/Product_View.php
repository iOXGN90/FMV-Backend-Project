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
            $categories = $request->input('categories');
            $query->whereHas('category', function ($query) use ($categories) {
                $query->whereIn('category_name', $categories);
            });
        }

        // Filter by product name (search query)
        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where('product_name', 'LIKE', "%{$search}%");
        }

        // Add sorting with validation to prevent invalid columns
        $validSortColumns = ['id', 'quantity', 'original_price', 'product_name'];  // Fix: Changed 'product_id' to 'id'
        $sortBy = in_array($request->input('sort_by'), $validSortColumns) ? $request->input('sort_by') : 'id';  // Fix: Default to 'id'
        $sortOrder = in_array($request->input('sort_order'), ['asc', 'desc']) ? $request->input('sort_order') : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        // Calculate total value of assets
        $totalValue = $query->sum(DB::raw('original_price * quantity'));

        // Add pagination
        $products = $query->paginate(30);

        // Format the response
        $formattedProducts = collect($products->items())->map(function ($product) {
            return [
                'product_id' => $product->id,  // 'id' is the correct column
                'category_name' => $product->category->category_name ?? 'Uncategorized',
                'product_name' => $product->product_name,
                'original_price' => number_format($product->original_price, 2, '.', ''),
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
            'totalValue' => number_format($totalValue, 2, '.', ''),
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
