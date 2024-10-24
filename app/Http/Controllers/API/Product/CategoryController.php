<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends BaseController
{
    /**
     * Display a listing of the categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $categories = Category::all();

        return response()->json(['success' => 'Categories retrieved successfully.', 'data' => $categories], 200);
    }

    /**
     * create a newly category in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $category = Category::create($request->all());

        return response()->json(['success' => 'Category created successfully.', 'data' => $category], 201);
    }

    /**
     * Display the specified category.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $category = Category::find($id);

        if (is_null($category)) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        return response()->json(['success' => 'Category retrieved successfully.', 'data' => $category], 200);
    }

    /**
     * Update the specified category in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $category = Category::find($id);

        if (is_null($category)) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        $category->update($request->all());

        return response()->json(['success' => 'Category updated successfully.', 'data' => $category], 200);
    }

    /**
     * Remove the specified category from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $category = Category::find($id);

        if (is_null($category)) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        $category->delete();

        return response()->json(['success' => 'Category deleted successfully.'], 200);
    }
}
