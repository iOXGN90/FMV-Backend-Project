<?php

namespace App\Http\Controllers\API\SalesInsight;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

use App\Models\Product;
use Illuminate\Support\Facades\DB;


class TopProductSales extends BaseController
{
    public function topThreeProducts()
    {
        // Query to calculate top 3 products based on successful deliveries
        $topProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(delivery_products.quantity) as total_sold')
            )
            ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S') // Only include successfully delivered products
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->orderByDesc('total_sold') // Sort by total_sold in descending order
            ->limit(3) // Fetch top 3 products
            ->get();

        // Return response as JSON
        return response()->json([
            'success' => true,
            'data' => $topProducts,
        ], 200);
    }

    public function topProducts(Request $request)
    {
        $perPage = $request->input('perPage', 10); // Default to 10 items per page

        $topProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(delivery_products.quantity) as total_sold')
            )
            ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S') // Only include successfully delivered products
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->orderByDesc('total_sold') // Sort by total_sold in descending order
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $topProducts->items(), // Paginated items
            'pagination' => [
                'total' => $topProducts->total(),
                'perPage' => $topProducts->perPage(),
                'currentPage' => $topProducts->currentPage(),
                'lastPage' => $topProducts->lastPage(),
            ],
        ], 200);
    }
}
