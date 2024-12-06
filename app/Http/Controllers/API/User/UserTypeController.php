<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController;

class UserTypeController extends BaseController
{
    /**
     * Create a new user type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|string|max:255|unique:user_types,user_type',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $userType = UserType::create($request->only('user_type'));

            return response()->json(['success' => 'User type created successfully.', 'data' => $userType], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|string|max:255|unique:user_types,user_type,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $userType = UserType::find($id);

        if (is_null($userType)) {
            return response()->json(['error' => 'User type not found.'], 404);
        }

        try {
            $userType->update($request->only('user_type'));

            return response()->json(['success' => 'User type updated successfully.', 'data' => $userType], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }


        /**
     * View all user types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $userType = UserType::all();

        return response()->json(['success' => 'User types retrieved successfully.', 'data' => $userType], 200);
    }


    /**
     * Delete a user type.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $userType = UserType::find($id);

        if (is_null($userType)) {
            return response()->json(['error' => 'User type not found.'], 404);
        }

        // Check if there are users associated with this user type
        if ($userType->users()->exists()) {
            return response()->json([
                'error' => 'Cannot delete user type. There are users associated with this user type.'
            ], 400);
        }

        $userType->delete(); // Proceed with the soft delete

        return response()->json(['success' => 'User type soft deleted successfully.'], 200);
    }


}
