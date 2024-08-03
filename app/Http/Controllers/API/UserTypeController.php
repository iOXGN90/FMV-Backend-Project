<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'user_type',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $userType = UserType::create($request->all());

        return response()->json(['success' => 'User type created successfully.', 'data' => $userType], 201);
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

        $userType->delete();

        return response()->json(['success' => 'User type deleted successfully.'], 200);
    }
}
