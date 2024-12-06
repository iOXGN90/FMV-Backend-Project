<?php

namespace App\Http\Controllers\API\PurchaseOrder;

use App\Models\SaleType;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;

class SaleTypeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $saleTypes = SaleType::all();
        return response()->json($saleTypes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sale_type_name' => 'required|string|max:255',
        ]);

        $saleType = SaleType::create($request->all());
        return response()->json($saleType, 201);
    }

    public function update(Request $request, $sale_type_id)
    {
        // Validate input
        $request->validate([
            'sale_type_name' => 'required|string|max:255|unique:sale_types,sale_type_name,' . $sale_type_id,
        ]);

        // Find the SaleType entry
        $saleType = SaleType::findOrFail($sale_type_id);

        // Update the sale type name
        $saleType->update([
            'sale_type_name' => $request->input('sale_type_name'),
        ]);

        // Return success response
        return response()->json(['success' => 'Sale type updated successfully.', 'data' => $saleType], 200);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $saleType = SaleType::find($id);
        if (is_null($saleType)) {
            return response()->json(['message' => 'Sale Type not found'], 404);
        }
        return response()->json($saleType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $saleType = SaleType::find($id);

        if (is_null($saleType)) {
            return response()->json(['message' => 'Sale Type not found'], 404);
        }

        // Check if there are any purchase orders associated with this sale type
        if ($saleType->purchaseOrders()->exists()) {
            return response()->json([
                'error' => 'Cannot delete sale type. There are purchase orders associated with this sale type.'
            ], 400);
        }

        $saleType->delete(); // Proceed with the soft delete

        return response()->json(['success' => 'Sale type deleted successfully.'], 200);
    }


}
