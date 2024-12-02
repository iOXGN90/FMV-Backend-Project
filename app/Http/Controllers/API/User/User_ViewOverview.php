<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class User_ViewOverview extends BaseController
{
    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Paginate the users, 20 per page
            $users = User::paginate(20);

            if ($users->isEmpty()) {
                Log::warning('No users found in the database.');
                return response()->json(['message' => 'No users found'], 404);
            }

            Log::info('Successfully fetched users.', ['users' => $users]);
            return response()->json(['success' => 'Users retrieved successfully.', 'data' => $users], 200);
        } catch (\Exception $e) {
            Log::error('Error occurred while fetching users.', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Error occurred while fetching users.'], 500);
        }
    }
}
