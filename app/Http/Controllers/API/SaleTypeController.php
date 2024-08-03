<?php

namespace App\Http\Controllers\API;

use App\Models\SaleType;
use Illuminate\Http\Request;

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'sale_type_name' => 'required|string|max:255',
        ]);

        $saleType = SaleType::find($id);
        if (is_null($saleType)) {
            return response()->json(['message' => 'Sale Type not found'], 404);
        }

        $saleType->update($request->all());
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

        $saleType->delete();
        return response()->json(null, 204);
    }
}
