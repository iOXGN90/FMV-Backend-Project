<?php

namespace App\Http\Controllers\API\Deliveries;

use App\Http\Controllers\API\BaseController;

use App\Models\DeliveryProduct;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Returns;


use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


use Illuminate\Http\Request;

class Deliveries_Returns extends BaseController
{
    public function createReturns(Request $request)
{
    $validator = Validator::make($request->all(), [
        'returns' => 'required|array',
        'returns.*.delivery_product_id' => 'required|exists:delivery_products,id',
        'returns.*.quantity' => 'required|integer|min:1',
        'returns.*.reason' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    DB::beginTransaction();
    try {
        $returnsData = [];

        foreach ($request->input('returns') as $returnInput) {
            $deliveryProduct = DeliveryProduct::find($returnInput['delivery_product_id']);
            if ($deliveryProduct) {
                // Create the return entry
                $return = Returns::create([
                    'delivery_products_id' => $returnInput['delivery_product_id'],
                    'quantity' => $returnInput['quantity'],
                    'reason' => $returnInput['reason'],
                ]);

                $returnsData[] = [
                    'product_name' => $deliveryProduct->product->product_name,
                    'quantity_returned' => $returnInput['quantity'],
                    'reason' => $returnInput['reason'],
                ];
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Returns created successfully for damaged products.',
            'returns' => $returnsData,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}
