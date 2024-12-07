<?php

namespace App\Http\Controllers\API\Deliveries;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\Delivery;
use App\Models\DeliveryProduct;
use App\Models\Product;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Deliveries_Cancel extends BaseController
{
    public function cancelDelivery(Request $request, $deliveryId)
{
    $delivery = Delivery::find($deliveryId);

    if (!$delivery) {
        return response()->json(['error' => 'Delivery not found'], 404);
    }

    if ($delivery->status === 'F') {
        return response()->json(['message' => 'Delivery is already canceled'], 400);
    }

    DB::beginTransaction();

    try {
        // Update the status of the delivery to 'F' (Failed)
        $delivery->status = 'F';
        $delivery->save();

        // Reverse the product quantities in inventory
        $deliveryProducts = DeliveryProduct::where('delivery_id', $deliveryId)->get();

        foreach ($deliveryProducts as $dp) {
            $product = Product::find($dp->product_id);
            if ($product) {
                $product->quantity += $dp->quantity; // Add back the canceled quantities
                $product->save();
            }
        }

        DB::commit();

        return response()->json(['message' => 'Delivery canceled successfully'], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => 'Cancellation failed: ' . $e->getMessage()], 500);
    }
}

}
