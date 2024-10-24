<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Http\Controllers\API\BaseController;

use Illuminate\Http\Request;
use app\Models\PurchaseOrder;

class PurchaseOrder_ViewWalkIns extends BaseController
{
        //* Start View WalkIn
        public function index_walk_in()
        {
            // Filter and get all walk-in orders where sale_type_id is 2
            $walkInOrders = PurchaseOrder::with(['address', 'productDetails.product'])
                ->where('sale_type_id', 2)
                ->get();

            return response()->json($walkInOrders);
        }
        //* End View WalkIn

        //* Start View WalkIn ID
        public function show($id)
        {
            $walkIn = PurchaseOrder::with('address', 'productDetails')
                ->where('sale_type_id', 2)
                ->find($id);

            if (is_null($walkIn)){
                return response()->json(['message' => 'WalkIn [Purchase Order] is not found'], 404);
            }
        }
        //* End View WalkIn ID
}
