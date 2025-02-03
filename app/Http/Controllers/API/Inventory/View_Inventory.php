<?php

namespace App\Http\Controllers\API\Inventory;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\DB;

class View_Inventory extends BaseController
{
    public function ViewTransaction(Request $request)
    {
        // Get transaction type from the request
        $transactionType = $request->input('transactionType', 'all'); // Default to 'all' if not specified

        // Time period filter
        $timePeriod = $request->input('timePeriod', 'all');
        $dateLimit = null;

        switch ($timePeriod) {
            case '30_days':
                $dateLimit = now()->subDays(30);
                break;
            case '60_days':
                $dateLimit = now()->subDays(60);
                break;
            case '90_days':
                $dateLimit = now()->subDays(90);
                break;
            default:
                $dateLimit = null;
                break;
        }

        $transactions = collect();
        $totalRestockedQuantity = 0; // Initialize restock quantity sum

        // Fetch Restock Transactions if applicable
        if ($transactionType === 'all' || $transactionType === 'Restock') {
            $restocks = DB::table('product_restock_orders')
                ->join('products', 'product_restock_orders.product_id', '=', 'products.id')
                ->select(
                    'product_restock_orders.product_id', // Include product_id in the response
                    DB::raw('NULL as delivery_id'),
                    'product_restock_orders.quantity',
                    DB::raw('FORMAT(product_restock_orders.quantity * products.original_price, 2) as total_value'),
                    'product_restock_orders.created_at as date',
                    DB::raw('"Restock" as transaction_type'),
                    DB::raw('NULL as delivery_status'),
                    DB::raw('NULL as total_damages')
                );

            if ($dateLimit) {
                $restocks->where('product_restock_orders.created_at', '>=', $dateLimit);
            }

            // Calculate total restocked quantity
            $restockResults = $restocks->get();
            $totalRestockedQuantity = $restockResults->sum('quantity');

            $transactions = $transactions->merge($restockResults);
        }

        // Fetch Delivery Transactions if applicable
        if ($transactionType === 'all' || $transactionType === 'Delivery') {
            $deliveries = DB::table('delivery_products as dp')
                ->join('deliveries as d', 'dp.delivery_id', '=', 'd.id')
                ->join('product_details as pd', 'd.purchase_order_id', '=', 'pd.purchase_order_id')
                ->select(
                    'dp.product_id', // Include product_id in the response
                    'dp.delivery_id',
                    'dp.quantity',
                    'dp.no_of_damages',
                    'pd.price',
                    DB::raw('(dp.quantity * pd.price) AS total_value'),
                    'd.created_at as date',
                    DB::raw('"Delivery" as transaction_type'),
                    'd.status as delivery_status'
                )
                ->where('d.status', 'S')
                ->distinct();

            if ($dateLimit) {
                $deliveries->where('d.created_at', '>=', $dateLimit);
            }

            $transactions = $transactions->merge($deliveries->get());
        }

        // Fetch Walk-In Transactions if applicable
        if ($transactionType === 'all' || $transactionType === 'Walk-In') {
            $walkIns = DB::table('purchase_orders as po')
                ->join('product_details as pd', 'po.id', '=', 'pd.purchase_order_id')
                ->select(
                    'pd.product_id', // Include product_id in the response
                    DB::raw('NULL as delivery_id'),
                    'pd.quantity',
                    'pd.price',
                    DB::raw('(pd.quantity * pd.price) AS total_value'),
                    'po.created_at as date',
                    DB::raw('"Walk-In" as transaction_type'),
                    DB::raw('NULL as delivery_status'),
                    DB::raw('NULL as total_damages')
                )
                ->where('po.sale_type_id', '=', 2); // Assuming '2' corresponds to 'Walk-In'

            if ($dateLimit) {
                $walkIns->where('po.created_at', '>=', $dateLimit);
            }

            $transactions = $transactions->merge($walkIns->get());
        }

        // Sort transactions by date
        $sortedTransactions = $transactions->sortByDesc('date')->values();

        // Pagination Setup
        $perPage = $request->input('perPage', 10);
        $currentPage = $request->input('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $paginatedTransactions = $sortedTransactions->slice($offset, $perPage)->values();

        // Return response
        return response()->json([
            'total_restocked_quantity' => $totalRestockedQuantity, // Added total restock quantity here
            'transactions' => [
                'data' => $paginatedTransactions,
                'pagination' => [
                    'total' => $transactions->count(),
                    'perPage' => $perPage,
                    'currentPage' => $currentPage,
                    'lastPage' => ceil($transactions->count() / $perPage),
                ],
            ],
        ]);
    }
}
