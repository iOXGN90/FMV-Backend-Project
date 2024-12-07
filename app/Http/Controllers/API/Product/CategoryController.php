<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255|unique:categories,category_name',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Create the new category
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
        // Validate input
        $validator = Validator::make($request->all(), [
            'category_name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id) {
                    // Check if a category with the same name exists
                    $existingCategory = Category::withTrashed()
                        ->where('category_name', $value)
                        ->where('id', '!=', $id)
                        ->first();

                    if ($existingCategory) {
                        // If the category is not soft deleted, fail the validation
                        if (is_null($existingCategory->deleted_at)) {
                            $fail('The category name is already taken.');
                        }
                    }
                },
            ],
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Find the category
        $category = Category::find($id);

        // Handle category not found
        if (is_null($category)) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        try {
            // Update the category
            $category->update($request->only('category_name'));

            // Return success response
            return response()->json([
                'success' => 'Category updated successfully.',
                'data' => $category
            ], 200);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json(['error' => 'An error occurred while updating the category.'], 500);
        }
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
            Log::warning("Attempted to delete a category that does not exist. Category ID: $id");
            return response()->json(['error' => 'Category not found.'], 404);
        }

        // Check if there are products associated with this category
        if ($category->products()->exists()) {
            Log::info("Attempt to delete category with associated products. Category ID: $id");
            return response()->json([
                'error' => 'Cannot delete category. There are products associated with this category.'
            ], 400);
        }

        Log::info("Category deleted successfully. Category ID: $id, Category Name: {$category->category_name}");
        $category->delete(); // Soft delete

        return response()->json(['success' => 'Category deleted successfully.'], 200);
    }


}
