<?php

namespace App\Http\Controllers\API\Deliveries;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\Delivery;
use Carbon\Carbon;


class Deliveries_View extends BaseController
{

    public function index(Request $request)
    {
        $query = Delivery::query();

        // Apply status filter only if it's not empty
        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        // Eager load purchase order details
        $deliveries = $query->with('purchaseOrder')->paginate(20);

        // Format the deliveries using collect and map
        $formattedDeliveries = collect($deliveries->items())->map(function ($delivery) {
            return [
                'delivery_id' => $delivery->id,
                'delivery_no' => $delivery->delivery_no,
                'notes' => $delivery->notes,
                'status' => $delivery->status,
                'formatted_date' => Carbon::parse($delivery->created_at)->format('m/d/Y H:i'),
                'purchase_order' => $delivery->purchaseOrder ? [
                    'purchase_order_id' => $delivery->purchaseOrder->id,
                    'customer_name' => $delivery->purchaseOrder->customer_name,
                    'status' => $delivery->purchaseOrder->status,
                    'date' => Carbon::parse($delivery->purchaseOrder->date)->format('m/d/Y H:i'),
                ] : null,
                'delivery_man' => $delivery->user ? [
                    'user_id' => $delivery->user->id,
                    'name' => $delivery->user->name,
                    'number' => $delivery->user->number,
                    'username' => $delivery->user->username,
                ] : null,
            ];
        });


        // Return the formatted data with pagination metadata
        return response()->json([
            'deliveries' => $formattedDeliveries,
            'pagination' => [
                'total' => $deliveries->total(),
                'perPage' => $deliveries->perPage(),
                'currentPage' => $deliveries->currentPage(),
                'lastPage' => $deliveries->lastPage(),
            ],
        ]);
    }

}
