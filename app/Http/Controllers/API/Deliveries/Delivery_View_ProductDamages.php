<?php

namespace App\Http\Controllers\API\Deliveries;

use App\Http\Controllers\API\BaseController;

use App\Models\Damage;
use Illuminate\Http\Request;

class Delivery_View_ProductDamages extends BaseController
{
    // Method to get all damages
    public function index()
    {
        $damages = Damage::with('delivery')->get();
        return response()->json($damages, 200);
    }

    // Method to filter damages based on delivery ID
    public function filterByDelivery(Request $request)
    {
        $deliveryId = $request->input('delivery_id');

        if ($deliveryId) {
            $damages = Damage::where('delivery_id', $deliveryId)
                             ->with('delivery')
                             ->get();
            return response()->json($damages, 200);
        }

        return response()->json(['message' => 'Delivery ID not provided'], 400);
    }
}
