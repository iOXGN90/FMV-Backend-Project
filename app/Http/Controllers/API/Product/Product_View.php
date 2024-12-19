<?php

namespace App\Http\Controllers\API\Product;
use App\Http\Controllers\API\Product\ProductRestockController;

use App\Http\Controllers\API\BaseController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ProductService;

class Product_View extends BaseController
{

    public function index(Request $request)
    {
        $query = Product::with(['category', 'deliveryProduct.delivery']); // Load necessary relationships

        // Filter by multiple categories
        if ($request->has('categories') && !empty($request->input('categories'))) {
            $categories = $request->input('categories');
            if (is_string($categories)) {
                $categories = explode(',', $categories); // Convert string to array
            }
            $query->whereHas('category', function ($query) use ($categories) {
                $query->whereIn('category_name', $categories);
            });
        }

        // Filter by product name (search query)
        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where('product_name', 'LIKE', "%{$search}%");
        }

        // Filter by needs_reorder
        if ($request->has('needs_reorder') && $request->input('needs_reorder') === 'true') {
            $query->where(function ($query) {
                $query->whereColumn('quantity', '<', DB::raw('(SELECT COALESCE(SUM(quantity), 0) / 30 * 5 + 20
                    FROM delivery_products dp
                    WHERE dp.product_id = products.id
                      AND EXISTS (SELECT 1 FROM deliveries d WHERE dp.delivery_id = d.id AND d.status = "S"))'));
            });
        }

        // Add sorting with validation to prevent invalid columns
        $validSortColumns = ['id', 'quantity', 'original_price', 'product_name'];
        $sortBy = in_array($request->input('sort_by'), $validSortColumns) ? $request->input('sort_by') : 'id';
        $sortOrder = in_array($request->input('sort_order'), ['asc', 'desc']) ? $request->input('sort_order') : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        // Calculate total value of assets
        $totalValue = $query->sum(DB::raw('COALESCE(original_price, 0) * COALESCE(quantity, 0)'));

        // Fetch products
        $products = $query->paginate(30);

        // Format the response
        $formattedProducts = collect($products->items())->map(function ($product) {
            // Calculate successful deliveries
            $successfulDeliveries = $product->deliveryProduct
                ->filter(function ($deliveryProduct) {
                    return optional($deliveryProduct->delivery)->status === 'S'; // Check delivery status
                })
                ->sum('quantity'); // Sum quantities for successful deliveries

            // Calculate reorder level
            $averageDailyUsage = $successfulDeliveries / 30 ?: 10; // Default average usage
            $leadTime = 5; // Assume lead time in days
            $safetyStock = 20; // Assume safety stock
            $reorderLevel = ($averageDailyUsage * $leadTime) + $safetyStock;

            // Determine if the product is below reorder level
            $needsReorder = $product->quantity < $reorderLevel;

            return [
                'product_id' => $product->id,
                'category_id' => $product->category->id,
                'category_name' => $product->category->category_name ?? 'Uncategorized',
                'product_name' => $product->product_name,
                'original_price' => number_format($product->original_price, 2, '.', ''), // Format price
                'quantity' => $product->quantity, // Integer quantity
                'successful_deliveries' => $successfulDeliveries,
                'reorder_level' => number_format($reorderLevel, 2, '.', ''), // Format reorder level
                'needs_reorder' => $needsReorder, // Boolean, no formatting needed
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
            'totalValue' => number_format($totalValue, 2, '.', ''), // Format total value
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


    public function lowProductLevel()
    {
        $restockController = new ProductRestockController();
        $reorderResponse = $restockController->reorderLevel();

        $reorderProducts = $reorderResponse->getData()->data;

        // Fetch low stock products where quantity <= 100
        $lowStockProducts = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id as product_id',
                'products.product_name',
                'categories.safety_stock',
                'products.quantity as quantity_left'
            )
            ->where('products.quantity', '<=', 120)  // Fetch only products where quantity is <= 100
            ->get();

        // Extract product ids of reorder products
        $reorderProductIds = collect($reorderProducts)->pluck('product_id')->toArray();

        // Filter out the products that are already part of the reorder list
        $filteredLowStock = $lowStockProducts->filter(function ($product) use ($reorderProductIds) {
            return !in_array($product->product_id, $reorderProductIds);
        });

        return response()->json([
            'data' => $filteredLowStock->values(),
        ]);
    }





}
