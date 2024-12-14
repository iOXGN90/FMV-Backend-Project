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
        $timePeriod = $request->input('timePeriod', 'all'); // Default to 'all'
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
                $dateLimit = null; // No date limit for 'all'
                break;
        }

        // Fetch Restock Transactions (IN Transactions)
        $restocks = DB::table('product_restock_orders')
            ->join('products', 'product_restock_orders.product_id', '=', 'products.id')
            ->select(
                'product_restock_orders.quantity',
                DB::raw('FORMAT(product_restock_orders.quantity * products.original_price, 2) as total_value'),
                'product_restock_orders.created_at as date',
                DB::raw('"IN" as transaction_type')
            )
            ->where('product_restock_orders.product_id', $product_id);

        if ($dateLimit) {
            $restocks->where('product_restock_orders.created_at', '>=', $dateLimit);
        }

        // Paginate Restock Transactions
        $perPage = $request->input('perPage', 20); // Default to 20 per page
        $currentPage = $request->input('page', 1);

        $restockResults = $restocks
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $totalRestocks = $restocks->count();

        // Fetch Delivery Transactions (OUT Transactions)
        $deliveries = DB::table('delivery_products')
            ->join('deliveries', 'delivery_products.delivery_id', '=', 'deliveries.id')
            ->join('products', 'delivery_products.product_id', '=', 'products.id')
            ->join('product_details', 'delivery_products.product_id', '=', 'product_details.product_id')
            ->select(
                'delivery_products.delivery_id',
                'delivery_products.quantity', // Fetch exact quantity per delivery
                DB::raw('FORMAT(delivery_products.quantity * product_details.price, 2) as total_value'), // Format total value to 2 decimal places
                'deliveries.created_at as date',
                'deliveries.status as delivery_status',
                'delivery_products.no_of_damages' // Fetch exact damages per row
            )
            ->where('delivery_products.product_id', $product_id)
            ->whereIn('deliveries.status', ['OD', 'P', 'S'])
            ->groupBy(
                'delivery_products.id', // Ensure unique rows
                'delivery_products.delivery_id',
                'delivery_products.quantity',
                'delivery_products.no_of_damages',
                'deliveries.created_at',
                'deliveries.status',
                'product_details.price'
            );

        if ($dateLimit) {
            $deliveries->where('deliveries.created_at', '>=', $dateLimit);
        }

        // Paginate Delivery Transactions
        $deliveryResults = $deliveries
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $totalDeliveries = $deliveries->count();

        // Combine Paginated Results
        return response()->json([
            'product_name' => $product->product_name,
            'product_created_date' => $product->created_at->format('m/d/Y'),
            'remaining_quantity' => $product->quantity,
            'data' => [
                'restocks' => [
                    'transactions' => $restockResults,
                    'pagination' => [
                        'total' => $totalRestocks,
                        'perPage' => $perPage,
                        'currentPage' => $currentPage,
                        'lastPage' => ceil($totalRestocks / $perPage),
                    ],
                ],
                'deliveries' => [
                    'transactions' => $deliveryResults,
                    'pagination' => [
                        'total' => $totalDeliveries,
                        'perPage' => $perPage,
                        'currentPage' => $currentPage,
                        'lastPage' => ceil($totalDeliveries / $perPage),
                    ],
                ],
            ],
        ]);
    }


}
