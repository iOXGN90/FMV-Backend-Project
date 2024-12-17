<?php

namespace App\Http\Controllers\API\SalesInsight;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\DeliveryProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrder_SalesInsights_View extends BaseController
{
    // Month Data

    public function MonthData(Request $request)
    {
        // Get the month and year from the request, or default to the current month
        $month = $request->input('month', now()->format('m'));
        $year = $request->input('year', now()->format('Y'));

        // Query for total successful delivered products (Deliveries + Walk-Ins)
        $totalSuccessDeliveredProduct = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S')
            ->whereMonth('deliveries.created_at', $month)
            ->whereYear('deliveries.created_at', $year)
            ->sum('delivery_products.quantity');

        // Add Walk-In product quantities with status 'S'
        $totalWalkInProduct = DB::table('product_details')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->where('purchase_orders.sale_type_id', 2) // Walk-In Sales
            ->where('purchase_orders.status', 'S') // Only successful Walk-In orders
            ->whereMonth('purchase_orders.created_at', $month)
            ->whereYear('purchase_orders.created_at', $year)
            ->sum('product_details.quantity');

        // Combine totals
        $totalSuccessDeliveredProduct += $totalWalkInProduct;

        // Query for total sales from deliveries
        $monthTotalSales = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->where('deliveries.status', 'S')
            ->whereMonth('deliveries.created_at', $month)
            ->whereYear('deliveries.created_at', $year)
            ->sum(DB::raw('delivery_products.quantity * product_details.price'));

        // Add Walk-In Sales to MonthTotalSales
        $walkInSales = DB::table('purchase_orders')
            ->join('product_details', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->where('purchase_orders.sale_type_id', 2)
            ->where('purchase_orders.status', 'S') // Only successful Walk-In orders
            ->whereMonth('purchase_orders.created_at', $month)
            ->whereYear('purchase_orders.created_at', $year)
            ->sum(DB::raw('product_details.quantity * product_details.price'));

        $monthTotalSales += $walkInSales;

        // Query for total damage cost
        $monthTotalDamageCost = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->where('deliveries.status', 'S')
            ->whereMonth('deliveries.created_at', $month)
            ->whereYear('deliveries.created_at', $year)
            ->sum(DB::raw('delivery_products.no_of_damages * product_details.price'));

        // Query for total successful deliveries count
        $successfulDeliveriesCount = DB::table('deliveries')
            ->where('status', 'S')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        // Format monthTotalSales and monthTotalDamageCost to 2 decimal places
        $formattedMonthTotalSales = number_format($monthTotalSales, 2, '.', ',');
        $formattedMonthTotalDamageCost = number_format($monthTotalDamageCost, 2, '.', ',');

        // Return response
        return response()->json([
            'totalSuccessDeliveredProduct' => $totalSuccessDeliveredProduct,
            'totalWalkInProduct' => $totalWalkInProduct,
            'monthTotalSales' => $formattedMonthTotalSales,
            'monthTotalDamageCost' => $formattedMonthTotalDamageCost,
            'successfulDeliveriesCount' => $successfulDeliveriesCount,
            'totalWalkInValueCost' => number_format($walkInSales, 2, '.', ','),
        ]);

    }


    public function MonthChartData(Request $request)
    {
        // Get the month and year from the request, or default to the current month
        $month = $request->input('month', now()->format('m'));
        $year = $request->input('year', now()->format('Y'));

        $dailyDeliveryData = DB::table('delivery_products')
        ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
        ->join('products', 'delivery_products.product_id', '=', 'products.id')
        ->join('product_details', 'products.id', '=', 'product_details.product_id')
        ->select(
            DB::raw('DAY(deliveries.created_at) as day'),
            DB::raw('SUM(delivery_products.quantity * product_details.price) as dailyTotalSales'),
            DB::raw('SUM(delivery_products.no_of_damages * product_details.price) as dailyTotalDamageCost')
        )
        ->where('deliveries.status', 'S')
        ->whereMonth('deliveries.created_at', $month)
        ->whereYear('deliveries.created_at', $year)
        ->groupBy(DB::raw('DAY(deliveries.created_at)'));

        $dailyWalkInData = DB::table('purchase_orders')
            ->join('product_details', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->select(
                DB::raw('DAY(purchase_orders.created_at) as day'),
                DB::raw('SUM(product_details.quantity * product_details.price) as dailyTotalSales'),
                DB::raw('0 as dailyTotalDamageCost') // No damages for walk-in sales
            )
            ->where('purchase_orders.sale_type_id', 2)
            ->whereMonth('purchase_orders.created_at', $month)
            ->whereYear('purchase_orders.created_at', $year)
            ->groupBy(DB::raw('DAY(purchase_orders.created_at)'));

        // Combine queries
        $combinedData = DB::table(DB::raw("({$dailyDeliveryData->toSql()} UNION ALL {$dailyWalkInData->toSql()}) as combined"))
            ->mergeBindings($dailyDeliveryData)
            ->mergeBindings($dailyWalkInData)
            ->select(
                'day',
                DB::raw('SUM(dailyTotalSales) as dailyTotalSales'),
                DB::raw('SUM(dailyTotalDamageCost) as dailyTotalDamageCost')
            )
            ->groupBy('day')
            ->orderBy('day', 'asc')
            ->get();

        // Format the results for the response
        $formattedDailyData = $combinedData->map(function ($data) {
            return [
                'day' => $data->day,
                'dailyTotalSales' => number_format($data->dailyTotalSales, 2, '.', ','),
                'dailyTotalDamageCost' => number_format($data->dailyTotalDamageCost, 2, '.', ','),
            ];
        });

        return response()->json([
            'dailyData' => $formattedDailyData,
        ]);
    }


    // Month Data

    // Annual Data
    public function AnnualData(Request $request)
    {
        // Get the year from the request, or default to the current year
        $year = $request->input('year', now()->format('Y'));

        // Query for total successful delivered products (Deliveries Only)
        $totalSuccessDeliveredProduct = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'S')
            ->whereYear('deliveries.created_at', $year)
            ->sum('delivery_products.quantity');

        // Query for total Walk-In product quantity (sale_type_id = 2)
        $totalWalkInProduct = DB::table('product_details')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->where('purchase_orders.sale_type_id', 2) // Walk-In Sale
            ->where('purchase_orders.status', 'S') // Only successful Walk-In orders
            ->whereYear('purchase_orders.created_at', $year)
            ->sum('product_details.quantity');

        // Combine total quantities
        $totalSuccessDeliveredProduct += $totalWalkInProduct;

        // Query for total sales from deliveries
        $annualTotalSales = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->where('deliveries.status', 'S')
            ->whereYear('deliveries.created_at', $year)
            ->sum(DB::raw('delivery_products.quantity * product_details.price'));

        // Query for Walk-In Sales (sale_type_id = 2)
        $walkInSales = DB::table('purchase_orders')
            ->join('product_details', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->where('purchase_orders.sale_type_id', 2)
            ->where('purchase_orders.status', 'S') // Only successful Walk-In orders
            ->whereYear('purchase_orders.created_at', $year)
            ->sum(DB::raw('product_details.quantity * product_details.price'));

        // Combine Walk-In Sales with Annual Total Sales
        $annualTotalSales += $walkInSales;

        // Query for total damage cost
        $annualTotalDamageCost = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->where('deliveries.status', 'S')
            ->whereYear('deliveries.created_at', $year)
            ->sum(DB::raw('delivery_products.no_of_damages * product_details.price'));

        // Query for total successful deliveries count
        $successfulDeliveriesCount = DB::table('deliveries')
            ->where('status', 'S')
            ->whereYear('created_at', $year)
            ->count();

        // Format annualTotalSales and annualTotalDamageCost to 2 decimal places
        $formattedAnnualTotalSales = number_format($annualTotalSales, 2, '.', ',');
        $formattedAnnualTotalDamageCost = number_format($annualTotalDamageCost, 2, '.', ',');

        // Return response in JSON format
        return response()->json([
            'totalSuccessDeliveredProduct' => $totalSuccessDeliveredProduct,
            'totalWalkInProduct' => $totalWalkInProduct,
            'annualTotalSales' => $formattedAnnualTotalSales,
            'annualTotalDamageCost' => $formattedAnnualTotalDamageCost,
            'successfulDeliveriesCount' => $successfulDeliveriesCount,
            'totalWalkInValueCost' => number_format($walkInSales, 2, '.', ','),
        ]);
    }




    public function AnnualChartData(Request $request)
    {
        // Get the year from the request, or default to the current year
        $year = $request->input('year', now()->format('Y'));

        // Query for monthly sales and damage cost (Deliveries)
        $monthlyDeliveriesData = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->select(
                DB::raw('MONTH(deliveries.created_at) as month'),
                DB::raw('SUM(delivery_products.quantity * product_details.price) as monthlyTotalSales'),
                DB::raw('SUM(delivery_products.no_of_damages * product_details.price) as monthlyTotalDamageCost')
            )
            ->where('deliveries.status', 'S') // Only include successful deliveries
            ->whereYear('deliveries.created_at', $year) // Filter by year
            ->groupBy(DB::raw('MONTH(deliveries.created_at)')); // Group by month

        // Query for monthly Walk-In sales
        $monthlyWalkInData = DB::table('purchase_orders')
            ->join('product_details', 'purchase_orders.id', '=', 'product_details.purchase_order_id')
            ->select(
                DB::raw('MONTH(purchase_orders.created_at) as month'),
                DB::raw('SUM(product_details.quantity * product_details.price) as monthlyTotalSales'),
                DB::raw('0 as monthlyTotalDamageCost') // Walk-in has no damages
            )
            ->where('purchase_orders.sale_type_id', 2) // Only Walk-In sales
            ->whereYear('purchase_orders.created_at', $year) // Filter by year
            ->groupBy(DB::raw('MONTH(purchase_orders.created_at)')); // Group by month

        // Combine Delivery and Walk-In Data using UNION ALL
        $combinedMonthlyData = DB::table(DB::raw("({$monthlyDeliveriesData->toSql()} UNION ALL {$monthlyWalkInData->toSql()}) as combined"))
            ->mergeBindings($monthlyDeliveriesData)
            ->mergeBindings($monthlyWalkInData)
            ->select(
                'month',
                DB::raw('SUM(monthlyTotalSales) as monthlyTotalSales'),
                DB::raw('SUM(monthlyTotalDamageCost) as monthlyTotalDamageCost')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Format the results for the response
        $formattedMonthlyData = $combinedMonthlyData->map(function ($data) {
            return [
                'month' => $data->month,
                'monthlyTotalSales' => number_format($data->monthlyTotalSales, 2, '.', ','),
                'monthlyTotalDamageCost' => number_format($data->monthlyTotalDamageCost, 2, '.', ','),
            ];
        });

        // Return formatted response
        return response()->json([
            'monthlyData' => $formattedMonthlyData,
        ]);
    }




    // Annual Data





    // public function recordPerMonths(Request $request)
    // {
    //     // Get the year from the query parameters, default to the current year
    //     $year = $request->query('year', Carbon::now()->year);

    //     // Fetch distinct years from the PurchaseOrder table
    //     $availableYears = PurchaseOrder::selectRaw('YEAR(created_at) as year')
    //         ->distinct()
    //         ->orderBy('year', 'desc')
    //         ->pluck('year'); // Retrieve available years as an array

    //     // Initialize the array to hold monthly data
    //     $monthlyData = [];

    //     // Loop through each month (1 to 12)
    //     for ($month = 1; $month <= 12; $month++) {
    //         // Get the purchase orders for the specific year and month with status = 'S'
    //         $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts', 'saleType'])
    //             ->whereYear('created_at', $year)
    //             ->whereMonth('created_at', $month)
    //             ->where('status', 'S') // Only successful purchase orders
    //             ->get();

    //         $totalRevenue = 0;
    //         $totalDamages = 0;

    //         // Loop through the purchase orders and calculate revenue and damages
    //         foreach ($purchaseOrders as $order) {
    //             if ($order->saleType->sale_type_name === 'Walk-In') {
    //                 // Process Walk-In orders using productDetails
    //                 foreach ($order->productDetails as $productDetail) {
    //                     $totalRevenue += $productDetail->price * $productDetail->quantity;
    //                 }
    //             } else {
    //                 // Process Delivery orders using deliveryProducts
    //                 foreach ($order->deliveries as $delivery) {
    //                     foreach ($delivery->deliveryProducts as $deliveryProduct) {
    //                         // Calculate revenue and damages for valid deliveries
    //                         $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

    //                         if ($productDetail) {
    //                             $productPrice = $productDetail->price;
    //                             $quantity = $deliveryProduct->quantity;
    //                             $damages = $deliveryProduct->no_of_damages;

    //                             $totalRevenue += $quantity * $productPrice;
    //                             $totalDamages += $damages * $productPrice;
    //                         }
    //                     }
    //                 }
    //             }
    //         }

    //         // Format the month name
    //         $formattedMonth = Carbon::create($year, $month, 1)->format('F');

    //         // Add the monthly data to the array
    //         $monthlyData[] = [
    //             'month' => $formattedMonth,
    //             'total_revenue' => number_format($totalRevenue, 2),
    //             'total_damages' => number_format($totalDamages, 2),
    //         ];
    //     }

    //     // Return both the monthly data and available years in the response
    //     return response()->json([
    //         'year' => $year,
    //         'available_years' => $availableYears,
    //         'data' => $monthlyData
    //     ], 200);
    // }


    // public function monthlyData(Request $request)
    // {
    //     $month = $request->input('month', Carbon::now()->month);
    //     $year = $request->input('year', Carbon::now()->year);

    //     Log::info("Fetching data for Month: $month, Year: $year");

    //     $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
    //         ->whereYear('created_at', $year)
    //         ->whereMonth('created_at', $month)
    //         ->where('status', 'S') // Only successful purchase orders
    //         ->get();

    //     $responseData = [];
    //     $totalRevenueCurrentMonth = 0;
    //     $totalDamagesCurrentMonth = 0;

    //     foreach ($purchaseOrders as $order) {
    //         $totalRevenue = 0;
    //         $totalDamagesValue = 0;

    //         // Calculate total revenue and damages for the order
    //         foreach ($order->productDetails as $productDetail) {
    //             $totalRevenue += $productDetail->price * $productDetail->quantity;
    //         }

    //         foreach ($order->deliveries as $delivery) {
    //             foreach ($delivery->deliveryProducts as $deliveryProduct) {
    //                 // Match the product in deliveryProducts with productDetails to get the price
    //                 $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

    //                 if ($productDetail) {
    //                     $totalDamagesValue += $deliveryProduct->no_of_damages * $productDetail->price;
    //                 }
    //             }
    //         }

    //         $totalRevenueCurrentMonth += $totalRevenue;
    //         $totalDamagesCurrentMonth += $totalDamagesValue;

    //         $responseData['Per_PurchaseOrderTotal'][] = [
    //             'purchase_order_id' => $order->id,
    //             'customer_name' => $order->customer_name,
    //             'total_revenue' => number_format($totalRevenue, 2),
    //             'date' => Carbon::parse($order->created_at)->format('M-d-Y'),
    //             'total_damages' => number_format($totalDamagesValue, 2),
    //         ];
    //     }

    //     $responseData['CurrentPurchaseOrderRevenue'] = number_format($totalRevenueCurrentMonth, 2);
    //     $responseData['CurrentMonthDamages'] = number_format($totalDamagesCurrentMonth, 2);

    //     // Process previous month's data
    //     $previousMonth = $month - 1;
    //     $previousYear = $year;
    //     if ($previousMonth == 0) {
    //         $previousMonth = 12;
    //         $previousYear--;
    //     }

    //     $previousPurchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
    //         ->whereYear('created_at', $previousYear)
    //         ->whereMonth('created_at', $previousMonth)
    //         ->where('status', 'S') // Only successful purchase orders
    //         ->get();

    //     $totalRevenuePreviousMonth = 0;
    //     $totalDamagesPreviousMonth = 0;

    //     foreach ($previousPurchaseOrders as $order) {
    //         $totalRevenue = 0;
    //         $totalDamagesValue = 0;

    //         foreach ($order->productDetails as $productDetail) {
    //             $totalRevenue += $productDetail->price * $productDetail->quantity;
    //         }

    //         foreach ($order->deliveries as $delivery) {
    //             foreach ($delivery->deliveryProducts as $deliveryProduct) {
    //                 $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

    //                 if ($productDetail) {
    //                     $totalDamagesValue += $deliveryProduct->no_of_damages * $productDetail->price;
    //                 }
    //             }
    //         }

    //         $totalRevenuePreviousMonth += $totalRevenue;
    //         $totalDamagesPreviousMonth += $totalDamagesValue;
    //     }

    //     $responseData['PreviousMonthRevenue'] = number_format($totalRevenuePreviousMonth, 2);
    //     $responseData['PreviousMonthDamages'] = number_format($totalDamagesPreviousMonth, 2);

    //     // Calculate total historical revenue and damages
    //     $totalHistoricalRevenue = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
    //         ->where('status', 'S') // Only successful purchase orders
    //         ->get()
    //         ->reduce(function ($carry, $order) {
    //             $totalRevenue = $order->productDetails->sum(function ($productDetail) {
    //                 return $productDetail->price * $productDetail->quantity;
    //             });
    //             return $carry + $totalRevenue;
    //         }, 0);

    //     $totalHistoricalDamages = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
    //         ->where('status', 'S') // Only successful purchase orders
    //         ->get()
    //         ->reduce(function ($carry, $order) {
    //             $totalDamages = 0;
    //             foreach ($order->deliveries as $delivery) {
    //                 foreach ($delivery->deliveryProducts as $deliveryProduct) {
    //                     $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

    //                     if ($productDetail) {
    //                         $totalDamages += $deliveryProduct->no_of_damages * $productDetail->price;
    //                     }
    //                 }
    //             }
    //             return $carry + $totalDamages;
    //         }, 0);

    //     $responseData['TotalRevenueOfPurchaseOrder'] = number_format($totalHistoricalRevenue, 2);
    //     $responseData['TotalDamagesOfPurchaseOrder'] = number_format($totalHistoricalDamages, 2);

    //     // Calculate and format the contribution percentage
    //     if ($totalRevenuePreviousMonth > 0) {
    //         $contributionPercentage = ($totalRevenueCurrentMonth / $totalRevenuePreviousMonth) * 100;
    //         $responseData['ContributionPercentage'] = number_format($contributionPercentage, 2);
    //     } else {
    //         $responseData['ContributionPercentage'] = '0.00';
    //     }

    //     Log::info('Final Response Data:', $responseData);

    //     return response()->json($responseData, 200);
    // }

}
