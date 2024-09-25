<?php

namespace App\Http\Controllers\API;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrder_AdminConfirms extends BaseController
{
    public function update_to_success(Request $request, $id)
    {
        $validator = Validator::make($request->all(),[
            'status' => 'required|in:S,F'
        ]);

        if ($validator->fails()){
            return response()->json($validator->errors(), 400);
        }
        // Find the Purchase Order by its ID
        $purchaseOrder = PurchaseOrder::find($id);

        // Find the all related deliveries for the given purchase order
        $deliveries = Delivery::where('purchase_order_id', $id)->get();

        if (is_null($purchaseOrder)){
            return response()->json(['message' => 'Purchase Order not found.']);
        }

        $purchaseOrder->status = $request->input('status');
        $purchaseOrder->save();

        if ($deliveries->isEmpty()){
            return response()->json(['message' => 'No deliveries found for this Purchase Order']);
        }

        foreach ($deliveries as $delivery){
            $delivery->status = $request->input('status');
            $delivery->save();
        }

        return response()->json(['message' => 'Purchase Order status updated successfully.','purchase_order' => $purchaseOrder], 200);

    }
}
