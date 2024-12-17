<?php

namespace App\Http\Controllers\API\SalesInsight;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

use App\Models\Product;
use Illuminate\Support\Facades\DB;


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
                    DB::raw('SUM(delivery_products.quantity) as total_sold')
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successfully delivered products
                ->whereMonth('deliveries.created_at', $month) // Filter by month
                ->whereYear('deliveries.created_at', $year)   // Filter by year
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_sold') // Sort by total_sold in descending order
                ->limit(3) // Fetch top 3 products
                ->get();

            // Query to calculate top 3 products with the most damages (no_of_damages)
            $topDamagedProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.no_of_damages) as total_damages')
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successfully delivered products
                ->whereMonth('deliveries.created_at', $month) // Filter by month
                ->whereYear('deliveries.created_at', $year)   // Filter by year
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages') // Sort by total_damages in descending order
                ->limit(3) // Fetch top 3 products with the most damages
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

            // Query to fetch top products within the specified month and year
            $topProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.quantity) as total_sold') // Only include successful records
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only successful deliveries
                ->whereMonth('deliveries.created_at', $month) // Filter by month
                ->whereYear('deliveries.created_at', $year)   // Filter by year
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
            // Inputs from the request
            $month = $request->input('month', now()->format('m')); // Default to current month
            $year = $request->input('year', now()->format('Y'));   // Default to current year
            $perPage = $request->input('perPage', 20);             // Default to 20 items per page

            // Query to fetch top products with the most damages within the specified month and year
            $topDamagedProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.no_of_damages) as total_damages') // Only sum valid damages
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successful deliveries
                ->whereMonth('deliveries.created_at', $month) // Filter by month
                ->whereYear('deliveries.created_at', $year)   // Filter by year
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages') // Sort by total damages in descending order
                ->paginate($perPage);

            // Return response with paginated data and pagination metadata
            return response()->json([
                'data' => $topDamagedProducts->items(), // Paginated data
                'pagination' => [
                    'total' => $topDamagedProducts->total(),
                    'perPage' => $topDamagedProducts->perPage(),
                    'currentPage' => $topDamagedProducts->currentPage(),
                    'lastPage' => $topDamagedProducts->lastPage(),
                ],
            ], 200);
        }
    // Month's Data

    // Annual's Data

        public function annualTopThreeProducts(Request $request)
        {
            // Get the year from the request, defaulting to the current year
            $year = $request->input('year', now()->format('Y'));

            // Query to calculate Top 3 Products based on total quantity sold for the year
            $topProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.quantity) as total_sold')
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successfully delivered products
                ->whereYear('deliveries.created_at', $year) // Filter by year
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_sold') // Sort by total sold
                ->limit(3) // Fetch top 3 products
                ->get();

            // Query to calculate Top 3 Products with the most damages for the year
            $topDamagedProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.no_of_damages) as total_damages')
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successfully delivered products
                ->whereYear('deliveries.created_at', $year) // Filter by year
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages') // Sort by total damages
                ->limit(3) // Fetch top 3 products with most damages
                ->get();

            // Return response as JSON
            return response()->json([
                'success' => true,
                'year' => $year,
                'top_sold_products' => $topProducts,
                'top_damaged_products' => $topDamagedProducts,
            ], 200);
        }


        public function annualTopSoldProducts(Request $request)
        {
            // Inputs from the request
            $year = $request->input('year', now()->format('Y')); // Default to current year
            $perPage = $request->input('perPage', 20);           // Default to 20 items per page

            // Query to fetch top sold products for the entire year
            $topProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.quantity) as total_sold') // Total quantity sold
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only successful deliveries
                ->whereYear('deliveries.created_at', $year) // Filter by year
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
            // Inputs from the request
            $year = $request->input('year', now()->format('Y')); // Default to current year
            $perPage = $request->input('perPage', 20);           // Default to 20 items per page

            // Query to fetch top damaged products for the entire year
            $topDamagedProducts = Product::select(
                    'products.id as product_id',
                    'products.product_name',
                    'products.original_price as price',
                    DB::raw('SUM(delivery_products.no_of_damages) as total_damages') // Sum valid damages
                )
                ->join('delivery_products', 'products.id', '=', 'delivery_products.product_id')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('deliveries.status', 'S') // Only include successful deliveries
                ->whereYear('deliveries.created_at', $year) // Filter by year only
                ->groupBy('products.id', 'products.product_name', 'products.original_price')
                ->orderByDesc('total_damages') // Sort by total damages in descending order
                ->paginate($perPage);

            // Return response with paginated data and pagination metadata
            return response()->json([
                'success' => true,
                'data' => $topDamagedProducts->items(), // Paginated data
                'pagination' => [
                    'total' => $topDamagedProducts->total(),
                    'perPage' => $topDamagedProducts->perPage(),
                    'currentPage' => $topDamagedProducts->currentPage(),
                    'lastPage' => $topDamagedProducts->lastPage(),
                ],
            ], 200);
        }

    // Annual's Data



}
