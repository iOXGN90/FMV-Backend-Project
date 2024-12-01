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
        ->where('status', 'S')
        ->get();

    $responseData = [];
    $totalRevenueCurrentMonth = 0;
    $totalDamagesCurrentMonth = 0;

    foreach ($purchaseOrders as $order) {
        $totalRevenue = 0;
        $totalDamagesValue = 0;

        foreach ($order->productDetails as $product) {
            $totalRevenue += $product->price * $product->quantity;
        }

        foreach ($order->deliveries as $delivery) {
            foreach ($delivery->deliveryProducts as $deliveryProduct) {
                $totalDamagesValue += $deliveryProduct->no_of_damages * $deliveryProduct->quantity;
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
        ->where('status', 'S')
        ->get();

    $totalRevenuePreviousMonth = 0;
    $totalDamagesPreviousMonth = 0;

    foreach ($previousPurchaseOrders as $order) {
        $totalRevenue = 0;
        $totalDamagesValue = 0;

        foreach ($order->productDetails as $product) {
            $totalRevenue += $product->price * $product->quantity;
        }

        foreach ($order->deliveries as $delivery) {
            foreach ($delivery->deliveryProducts as $deliveryProduct) {
                $totalDamagesValue += $deliveryProduct->no_of_damages * $deliveryProduct->quantity;
            }
        }

        $totalRevenuePreviousMonth += $totalRevenue;
        $totalDamagesPreviousMonth += $totalDamagesValue;
    }

    $responseData['PreviousMonthRevenue'] = number_format($totalRevenuePreviousMonth, 2);
    $responseData['PreviousMonthDamages'] = number_format($totalDamagesPreviousMonth, 2);

    // Calculate total historical revenue and damages
    $totalHistoricalRevenue = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
        ->where('status', 'S')
        ->get()
        ->reduce(function ($carry, $order) use (&$totalDamagesCurrentMonth) {
            $totalRevenue = $order->productDetails->sum(function ($product) {
                return $product->price * $product->quantity;
            });
            $totalDamages = $order->deliveries->sum(function ($delivery) {
                return $delivery->deliveryProducts->sum('no_of_damages') * $delivery->deliveryProducts->first()->quantity;
            });
            return $carry + $totalRevenue;
        }, 0);

    $responseData['TotalRevenueOfPurchaseOrder'] = number_format($totalHistoricalRevenue, 2);
    $responseData['TotalDamagesOfPurchaseOrder'] = number_format($totalDamagesCurrentMonth, 2);  // Include total damages

    // Calculate and format the contribution percentage
    $totalCombinedRevenue = $totalRevenueCurrentMonth + $totalRevenuePreviousMonth;
    if ($totalCombinedRevenue > 0) {
        $contributionPercentage = ($totalRevenueCurrentMonth / $totalCombinedRevenue) * 100;
        $responseData['ContributionPercentage'] = number_format($contributionPercentage, 2);
    } else {
        $responseData['ContributionPercentage'] = '0.00%';
    }

    Log::info('Final Response Data:', $responseData);

    return response()->json($responseData, 200);
}





    public function recordPerMonths(Request $request)
    {
        // Retrieve the year from the request parameters or default to the current year if not specified
        $year = $request->query('year', Carbon::now()->year);

        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $purchaseOrders = PurchaseOrder::with(['productDetails', 'deliveries.deliveryProducts'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('status', 'S')
                ->get();

            $totalRevenue = 0; // Calculate total revenue
            $totalDamages = 0; // Calculate total damages (for reference)

            foreach ($purchaseOrders as $order) {
                foreach ($order->productDetails as $productDetail) {
                    foreach ($order->deliveries as $delivery) {
                        foreach ($delivery->deliveryProducts as $deliveryProduct) {
                            if ($productDetail->product_id === $deliveryProduct->product_id) {
                                $productPrice = $productDetail->price;
                                $quantity = $deliveryProduct->quantity;
                                $damages = $deliveryProduct->no_of_damages;

                                $totalRevenue += $quantity * $productPrice; // Only calculate revenue here
                                $totalDamages += $damages * $productPrice; // Still keeping damages for reference
                            }
                        }
                    }
                }
            }

            $formattedMonth = Carbon::create($year, $month, 1)->format('F');

            $monthlyData[] = [
                'month' => $formattedMonth,
                'total_revenue' => number_format($totalRevenue, 2),
                'total_damages' => number_format($totalDamages, 2), // Damages can be displayed optionally
            ];
        }

        return response()->json([
            'year' => $year,
            'data' => $monthlyData
        ], 200);
    }



}
