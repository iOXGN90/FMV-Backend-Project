<?php

namespace App\Http\Controllers\API;

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
}
