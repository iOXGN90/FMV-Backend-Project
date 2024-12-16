<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Models\ProductRestockOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductRestockController extends BaseController
{
    // Display a listing of the Product restock orders.
    public function index()
    {
        $ProductRestockOrders = ProductRestockOrder::with('user', 'product')->get();
        return response()->json($ProductRestockOrders);
    }

    public function reorderLevel()
    {
        // Define lead time
        $leadTime = 14; // Default lead time in days

        // Fetch all products with their category
        $products = Product::with('category')->get();

        $results = $products->map(function ($product) use ($leadTime) {
            // Fetch total successful deliveries (use the last 30 days by default)
            $successfulDeliveries = DB::table('delivery_products')
                ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
                ->where('delivery_products.product_id', $product->id)
                ->whereIn('deliveries.status', ['OD', 'P', 'S']) // Only successful statuses
                ->where('deliveries.created_at', '>=', now()->subDays(30)) // Last 30 days
                ->sum('delivery_products.quantity');

            // Calculate average daily usage
            $averageDailyUsage = $successfulDeliveries / 30; // Assume 30 days in the month

            // Determine safety stock (from category or default to 70)
            $safetyStock = $product->category->safety_stock ?? 70;

            // Calculate reorder level
            $reorderLevel = ($averageDailyUsage * $leadTime) + $safetyStock;

            return [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'current_quantity' => $product->quantity,
                'category_name' => $product->category->name ?? 'Uncategorized',
                'average_daily_usage' => round($averageDailyUsage, 2),
                'reorder_level' => round($reorderLevel, 2),
                'needs_reorder' => $product->quantity <= $reorderLevel,
            ];
        });

        return response()->json([
            'data' => $results,
        ]);
    }



    // Store a newly created restock order in storage.
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        DB::beginTransaction();
        try {
            // Create the product restock order
            $productRestockOrder = ProductRestockOrder::create($request->all());

            // Update the product quantity
            $product = Product::find($request->input('product_id'));
            $product->quantity += $request->input('quantity');
            $product->save();

            DB::commit();

            // Custom response
            $response = [
                'productRestock_id' => $productRestockOrder->id,
                'user' => [
                    'name' => $productRestockOrder->user->name,
                ],
                'product' => [
                    'name' => $product->product_name,
                    'restock_quantity' => $productRestockOrder->quantity,
                    'total quantity of product' => $product->quantity,
                ],
            ];

            return response()->json($response, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    // Display the specified product restock order.
    public function show($id)
    {
        $ProductRestockOrder = ProductRestockOrder::with('user', 'product')->find($id);

        if (is_null($ProductRestockOrder)) {
            return response()->json(['message' => 'Product Restock Order not found'], 404);
        }

        return response()->json($ProductRestockOrder);
    }

    // Update the specified restock order in storage.
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $ProductRestockOrder = ProductRestockOrder::find($id);

        if (is_null($ProductRestockOrder)) {
            return response()->json(['message' => 'Product Restock Order not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Adjust the stock quantity before updating the restock order
            $product = Product::find($ProductRestockOrder->product_id);
            $product->quantity -= $ProductRestockOrder->quantity; // Subtract the old quantity

            // Update the restock order
            $ProductRestockOrder->update($request->all());

            // Add the new quantity
            $product->quantity += $request->input('quantity');
            $product->save();

            DB::commit();
            return response()->json($ProductRestockOrder->load('user', 'product'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while updating the product restock order'], 500);
        }
    }

    // Remove the specified restock order from storage.
    public function destroy($id)
    {
        $ProductRestockOrder = ProductRestockOrder::find($id);

        if (is_null($ProductRestockOrder)) {
            return response()->json(['message' => 'Restock Order not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Adjust the stock quantity
            $product = Product::find($ProductRestockOrder->product_id);
            $product->quantity -= $ProductRestockOrder->quantity;
            $product->save();

            // Delete the restock order
            $ProductRestockOrder->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error occurred while deleting the restock order'], 500);
        }
    }


    public function productTransactions($product_id, Request $request)
    {
        // Validate product ID
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Fetch time period filter
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
            case 'all':
            default:
                $dateLimit = null;
                break;
        }

        // Fetch Restock Transactions (IN)
        $restocks = DB::table('product_restock_orders')
        ->join('products', 'product_restock_orders.product_id', '=', 'products.id')
        ->select(
            DB::raw('NULL as delivery_id'),
            'product_restock_orders.quantity',
            DB::raw('FORMAT(product_restock_orders.quantity * products.original_price, 2) as total_value'),
            'product_restock_orders.created_at as date', // Correct year from product_restock_orders
            DB::raw('"IN" as transaction_type'),
            DB::raw('NULL as delivery_status'),
            DB::raw('NULL as no_of_damages')
        );

        if ($dateLimit) {
            $restocks->where('product_restock_orders.created_at', '>=', $dateLimit);
        }

        $deliveries = DB::table('delivery_products')
        ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
        ->join('product_details', 'delivery_products.product_id', '=', 'product_details.product_id')
        ->select(
            'delivery_products.delivery_id',
            'delivery_products.quantity',
            DB::raw('FORMAT(delivery_products.quantity * product_details.price, 2) as total_value'),
            'deliveries.created_at as date', // Correct year from deliveries
            DB::raw('"OUT" as transaction_type'),
            'deliveries.status as delivery_status',
            'delivery_products.no_of_damages'
        );

        if ($dateLimit) {
            $deliveries->where('deliveries.created_at', '>=', $dateLimit);
        }

        // Combine Queries
        $transactions = $restocks->unionAll($deliveries);

        // Count Total Transactions
        $totalTransactions = DB::table(DB::raw("({$transactions->toSql()}) as combined"))
            ->mergeBindings($transactions)
            ->count();

        // Paginate Transactions
        $perPage = $request->input('perPage', 20);
        $currentPage = $request->input('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $paginatedTransactions = DB::table(DB::raw("({$transactions->toSql()}) as combined"))
            ->mergeBindings($transactions)
            ->orderBy('date', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Return Response
        return response()->json([
            'product_name' => $product->product_name,
            'product_created_date' => $product->created_at->format('m/d/Y'),
            'remaining_quantity' => $product->quantity,
            'product_id' => $product->id,
            'transactions' => [
                'data' => $paginatedTransactions,
                'pagination' => [
                    'total' => $totalTransactions,
                    'perPage' => $perPage,
                    'currentPage' => $currentPage,
                    'lastPage' => ceil($totalTransactions / $perPage),
                ],
            ],
        ]);
    }



}
