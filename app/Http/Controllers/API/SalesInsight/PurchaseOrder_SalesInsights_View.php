<?php

namespace App\Http\Controllers\API\SalesInsight;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PurchaseOrder_SalesInsights_View extends BaseController
{
    public function monthlyData(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        Log::info("Fetching data for Month: $month, Year: $year");

        $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', 'S') // Only successful purchase orders
            ->get();

        $responseData = [];
        $totalRevenueCurrentMonth = 0;
        $totalDamagesCurrentMonth = 0;

        foreach ($purchaseOrders as $order) {
            $totalRevenue = 0;
            $totalDamagesValue = 0;

            // Calculate total revenue and damages for the order
            foreach ($order->productDetails as $productDetail) {
                $totalRevenue += $productDetail->price * $productDetail->quantity;
            }

            foreach ($order->deliveries as $delivery) {
                foreach ($delivery->deliveryProducts as $deliveryProduct) {
                    // Match the product in deliveryProducts with productDetails to get the price
                    $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

                    if ($productDetail) {
                        $totalDamagesValue += $deliveryProduct->no_of_damages * $productDetail->price;
                    }
                }
            }

            $totalRevenueCurrentMonth += $totalRevenue;
            $totalDamagesCurrentMonth += $totalDamagesValue;

            $responseData['Per_PurchaseOrderTotal'][] = [
                'purchase_order_id' => $order->id,
                'customer_name' => $order->customer_name,
                'total_revenue' => number_format($totalRevenue, 2),
                'date' => Carbon::parse($order->created_at)->format('M-d-Y'),
                'total_damages' => number_format($totalDamagesValue, 2),
            ];
        }

        $responseData['CurrentPurchaseOrderRevenue'] = number_format($totalRevenueCurrentMonth, 2);
        $responseData['CurrentMonthDamages'] = number_format($totalDamagesCurrentMonth, 2);

        // Process previous month's data
        $previousMonth = $month - 1;
        $previousYear = $year;
        if ($previousMonth == 0) {
            $previousMonth = 12;
            $previousYear--;
        }

        $previousPurchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
            ->whereYear('created_at', $previousYear)
            ->whereMonth('created_at', $previousMonth)
            ->where('status', 'S') // Only successful purchase orders
            ->get();

        $totalRevenuePreviousMonth = 0;
        $totalDamagesPreviousMonth = 0;

        foreach ($previousPurchaseOrders as $order) {
            $totalRevenue = 0;
            $totalDamagesValue = 0;

            foreach ($order->productDetails as $productDetail) {
                $totalRevenue += $productDetail->price * $productDetail->quantity;
            }

            foreach ($order->deliveries as $delivery) {
                foreach ($delivery->deliveryProducts as $deliveryProduct) {
                    $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

                    if ($productDetail) {
                        $totalDamagesValue += $deliveryProduct->no_of_damages * $productDetail->price;
                    }
                }
            }

            $totalRevenuePreviousMonth += $totalRevenue;
            $totalDamagesPreviousMonth += $totalDamagesValue;
        }

        $responseData['PreviousMonthRevenue'] = number_format($totalRevenuePreviousMonth, 2);
        $responseData['PreviousMonthDamages'] = number_format($totalDamagesPreviousMonth, 2);

        // Calculate total historical revenue and damages
        $totalHistoricalRevenue = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
            ->where('status', 'S') // Only successful purchase orders
            ->get()
            ->reduce(function ($carry, $order) {
                $totalRevenue = $order->productDetails->sum(function ($productDetail) {
                    return $productDetail->price * $productDetail->quantity;
                });
                return $carry + $totalRevenue;
            }, 0);

        $totalHistoricalDamages = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
            ->where('status', 'S') // Only successful purchase orders
            ->get()
            ->reduce(function ($carry, $order) {
                $totalDamages = 0;
                foreach ($order->deliveries as $delivery) {
                    foreach ($delivery->deliveryProducts as $deliveryProduct) {
                        $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

                        if ($productDetail) {
                            $totalDamages += $deliveryProduct->no_of_damages * $productDetail->price;
                        }
                    }
                }
                return $carry + $totalDamages;
            }, 0);

        $responseData['TotalRevenueOfPurchaseOrder'] = number_format($totalHistoricalRevenue, 2);
        $responseData['TotalDamagesOfPurchaseOrder'] = number_format($totalHistoricalDamages, 2);

        // Calculate and format the contribution percentage
        if ($totalRevenuePreviousMonth > 0) {
            $contributionPercentage = ($totalRevenueCurrentMonth / $totalRevenuePreviousMonth) * 100;
            $responseData['ContributionPercentage'] = number_format($contributionPercentage, 2);
        } else {
            $responseData['ContributionPercentage'] = '0.00';
        }

        Log::info('Final Response Data:', $responseData);

        return response()->json($responseData, 200);
    }





    public function recordPerMonths(Request $request)
    {
        // Get the year from the query parameters, default to the current year
        $year = $request->query('year', Carbon::now()->year);

        // Fetch distinct years from the PurchaseOrder table
        $availableYears = PurchaseOrder::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year'); // Retrieve available years as an array

        // Initialize the array to hold monthly data
        $monthlyData = [];

        // Loop through each month (1 to 12)
        for ($month = 1; $month <= 12; $month++) {
            // Get the purchase orders for the specific year and month with status = 'S'
            $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts', 'saleType'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('status', 'S') // Only successful purchase orders
                ->get();

            $totalRevenue = 0;
            $totalDamages = 0;

            // Loop through the purchase orders and calculate revenue and damages
            foreach ($purchaseOrders as $order) {
                if ($order->saleType->sale_type_name === 'Walk-In') {
                    // Process Walk-In orders using productDetails
                    foreach ($order->productDetails as $productDetail) {
                        $totalRevenue += $productDetail->price * $productDetail->quantity;
                    }
                } else {
                    // Process Delivery orders using deliveryProducts
                    foreach ($order->deliveries as $delivery) {
                        foreach ($delivery->deliveryProducts as $deliveryProduct) {
                            // Calculate revenue and damages for valid deliveries
                            $productDetail = $order->productDetails->firstWhere('product_id', $deliveryProduct->product_id);

                            if ($productDetail) {
                                $productPrice = $productDetail->price;
                                $quantity = $deliveryProduct->quantity;
                                $damages = $deliveryProduct->no_of_damages;

                                $totalRevenue += $quantity * $productPrice;
                                $totalDamages += $damages * $productPrice;
                            }
                        }
                    }
                }
            }

            // Format the month name
            $formattedMonth = Carbon::create($year, $month, 1)->format('F');

            // Add the monthly data to the array
            $monthlyData[] = [
                'month' => $formattedMonth,
                'total_revenue' => number_format($totalRevenue, 2),
                'total_damages' => number_format($totalDamages, 2),
            ];
        }

        // Return both the monthly data and available years in the response
        return response()->json([
            'year' => $year,
            'available_years' => $availableYears,
            'data' => $monthlyData
        ], 200);
    }



    // public function recordPerMonths(Request $request)
    // {
    //     // Fetch all purchase orders with related product details and deliveries
    //     $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])->get();

    //     // Optionally, you can log the number of fetched orders for debugging
    //     Log::info("Fetched " . $purchaseOrders->count() . " purchase orders.");

    //     return response()->json($purchaseOrders, 200);
    // }




}
