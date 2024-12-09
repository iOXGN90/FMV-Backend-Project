<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Http\Controllers\API\BaseController;

use app\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrder_ViewWalkIns extends BaseController
{
        //* Start View WalkIn
        public function index_walk_in()
        {
            try {
                $purchaseOrderWalkin = DB::table('purchase_orders as a')
                    ->join('addresses as b', 'b.id', '=', 'a.address_id')
                    ->join('product_details as c', 'c.purchase_order_id', '=', 'a.id')
                    ->select(
                        'a.id as purchase_order_id',
                        'a.customer_name',
                        'a.status',
                        'a.created_at as purchase_date', // Update this if `created_at` is the correct column
                        'b.street',
                        'b.barangay',
                        'b.city',
                        'b.province',
                        'c.product_id',
                        'c.price',
                        'c.quantity'
                    )
                    ->where('a.sale_type_id', 2)
                    ->paginate(20);

                return response()->json($purchaseOrderWalkin, 200);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
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
