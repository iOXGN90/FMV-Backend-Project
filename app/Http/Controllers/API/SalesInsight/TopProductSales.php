<?php

namespace App\Http\Controllers\API\SalesInsight;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\Product;


class TopProductSales extends BaseController
{
    // Month's Data
    public function topThreeProducts(Request $request)
    {
        // Get the month and year from the request, defaulting to the current month and year
        $month = $request->input('month', now()->format('m'));
        $year = $request->input('year', now()->format('Y'));

        // Query to calculate top 3 products based on successful deliveries (total sold)
        $topProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(COALESCE(delivery_products.quantity, 0)) +
                        SUM(COALESCE(product_details.quantity, 0)) as total_sold')
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where(function ($query) use ($month, $year) {
                $query->where('deliveries.status', 'S')  // Only include successfully delivered products
                      ->whereMonth('deliveries.created_at', $month)  // Filter by month
                      ->whereYear('deliveries.created_at', $year)    // Filter by year
                      ->orWhere(function ($query) use ($year) {
                          $query->whereYear('purchase_orders.created_at', $year); // Include walk-ins
                      });
            })
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->having('total_sold', '>', 0)  // Exclude products with 0 total_sold
            ->orderByDesc('total_sold')     // Sort by total sold
            ->limit(3)                      // Fetch top 3 products
            ->get();

        // Query to calculate top 3 products with the most damages (no_of_damages)
        $topDamagedProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(COALESCE(delivery_products.no_of_damages, 0)) as total_damages')
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->whereYear('deliveries.created_at', $year)  // Match the year for deliveries
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->having('total_damages', '>', 0)  // Exclude products with 0 total_damages
            ->orderByDesc('total_damages')     // Sort by total damages
            ->limit(3)                         // Limit to top 3 products
            ->get();

        // Return response as JSON
        return response()->json([
            'success' => true,
            'top_sold_products' => $topProducts,
            'top_damaged_products' => $topDamagedProducts,
        ], 200);
    }


    public function topSoldProducts(Request $request)
    {
        // Inputs from the request
        $month = $request->input('month', now()->format('m')); // Default to current month
        $year = $request->input('year', now()->format('Y'));   // Default to current year
        $perPage = $request->input('perPage', 20);             // Default to 20 items per page

        // Query to fetch top sold products (combining delivery_products and product_details)
        $topProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('
                    SUM(COALESCE(delivery_products.quantity, 0)) +
                    SUM(COALESCE(product_details.quantity, 0)) as total_sold
                ') // Add quantities from both tables
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where(function ($query) use ($month, $year) {
                $query->where(function ($subQuery) use ($month, $year) {
                    $subQuery->whereMonth('deliveries.created_at', $month)
                        ->whereYear('deliveries.created_at', $year);
                }) // Filter by month and year for deliveries
                ->orWhere(function ($subQuery) use ($year) {
                    $subQuery->whereYear('purchase_orders.created_at', $year); // Include walk-ins
                });
            })
            ->where(function ($query) {
                $query->where('deliveries.status', 'S') // Only successful deliveries
                    ->orWhere('purchase_orders.sale_type_id', 2); // Only Walk-In sales
            })
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->orderByDesc('total_sold') // Sort by total_sold in descending order
            ->paginate($perPage);

        // Return paginated data along with pagination metadata
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

    public function topDamagedProducts(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', null); // Optional month filter
        $perPage = $request->input('perPage', 20); // Default to 20 items per page

        Log::info("Fetching Top Damaged Products - Year: {$year}, Month: {$month}");

        try {
            // Base query
            $query = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(delivery_products.no_of_damages) as total_damages')
            )
            ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S') // Only include successful deliveries
            ->whereYear('deliveries.created_at', $year);

            // Apply optional month filter
            if (!is_null($month)) {
                Log::info("Applying month filter: {$month}");
                $query->whereMonth('deliveries.created_at', $month);
            }

            // Group and order results
            $topDamagedProducts = $query
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages')
                ->paginate($perPage);

            Log::info("Query executed successfully.");

            return response()->json([
                'success' => true,
                'data' => $topDamagedProducts->items(),
                'pagination' => [
                    'total' => $topDamagedProducts->total(),
                    'perPage' => $topDamagedProducts->perPage(),
                    'currentPage' => $topDamagedProducts->currentPage(),
                    'lastPage' => $topDamagedProducts->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error fetching top damaged products: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Server error occurred.'], 500);
        }
    }



    // Month's Data

    // Annual's Data
    public function annualTopThreeProducts(Request $request)
    {
        // Get the year from the request, defaulting to the current year
        $year = $request->input('year', now()->format('Y'));

        // Query to calculate Top 3 Sold Products (Delivery + Walk-In)
        $topSoldProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('
                    SUM(COALESCE(delivery_products.quantity, 0)) +
                    SUM(COALESCE(product_details.quantity, 0)) as total_sold
                ') // Add quantities from both deliveries and walk-ins
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where(function ($query) use ($year) {
                $query->whereYear('deliveries.created_at', $year) // Match the year for deliveries
                      ->orWhereYear('purchase_orders.created_at', $year); // Match the year for walk-ins
            })
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->having('total_sold', '>', 0) // Exclude products with 0 total_sold
            ->orderByDesc('total_sold') // Sort by total sold
            ->limit(3) // Limit to top 3 products
            ->get();

        // Query to calculate Top 3 Damaged Products (Delivery Only)
        $topDamagedProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(COALESCE(delivery_products.no_of_damages, 0)) as total_damages')
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->whereYear('deliveries.created_at', $year) // Match the year for deliveries
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->having('total_damages', '>', 0) // Exclude products with 0 total_damages
            ->orderByDesc('total_damages') // Sort by total damages
            ->limit(3) // Limit to top 3 products
            ->get();

        // Return response as JSON
        return response()->json([
            'success' => true,
            'year' => $year,
            'top_sold_products' => $topSoldProducts,
            'top_damaged_products' => $topDamagedProducts,
        ], 200);
    }

    public function annualTopSoldProducts(Request $request)
    {
        // Inputs from the request
        $year = $request->input('year', now()->format('Y')); // Default to current year
        $perPage = $request->input('perPage', 20);           // Default to 20 items per page

        // Query to fetch top sold products (combining delivery_products and product_details)
        $topProducts = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('
                    SUM(COALESCE(delivery_products.quantity, 0)) +
                    SUM(COALESCE(product_details.quantity, 0)) as total_sold
                ') // Add quantities from both tables
            )
            ->leftJoin('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->leftJoin('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->leftJoin('product_details', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('purchase_orders', 'product_details.purchase_order_id', '=', 'purchase_orders.id')
            ->where(function ($query) use ($year) {
                $query->whereYear('deliveries.created_at', $year)
                    ->orWhereYear('purchase_orders.created_at', $year);
            }) // Filter by year for both deliveries and purchase_orders
            ->where(function ($query) {
                $query->where('deliveries.status', 'S') // Only successful deliveries
                    ->orWhere('purchase_orders.sale_type_id', 2); // Only Walk-In sales
            })
            ->groupBy('products.id', 'products.product_name', 'products.original_price')
            ->orderByDesc('total_sold') // Sort by total_sold in descending order
            ->paginate($perPage);

        // Return paginated data along with pagination metadata
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

    public function annualTopDamagedProducts(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', null); // Optional month filter

        Log::info("Fetching Top Damaged Products - Year: {$year}, Month: {$month}");

        try {
            $query = Product::select(
                'products.id as product_id',
                'products.product_name',
                'products.original_price as price',
                DB::raw('SUM(delivery_products.no_of_damages) as total_damages')
            )
            ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S') // Only successful deliveries
            ->whereYear('deliveries.created_at', $year);

            // Optional month filter
            if (!is_null($month)) {
                Log::info("Applying month filter: {$month}");
                $query->whereMonth('deliveries.created_at', $month);
            }

            $topDamagedProducts = $query
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages')
                ->paginate(20);

            Log::info("Query executed successfully.");

            return response()->json([
                'success' => true,
                'data' => $topDamagedProducts->items(),
                'pagination' => [
                    'total' => $topDamagedProducts->total(),
                    'perPage' => $topDamagedProducts->perPage(),
                    'currentPage' => $topDamagedProducts->currentPage(),
                    'lastPage' => $topDamagedProducts->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching top damaged products: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Server error occurred.'], 500);
        }
    }



    // Annual's Data

}
