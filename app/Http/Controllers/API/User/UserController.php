<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\API\BaseController;

class UserController extends BaseController
{

    public function limited(): JsonResponse
    {
        // Get all users
        $users = User::all();

        return response()->json(['success' => 'All users retrieved successfully.', 'data' => $users], 200);
    }


// Start VIEW ALL USER
    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // Paginate the users, 20 per page
        $users = User::all();

        return response()->json(['success' => 'Users retrieved successfully.', 'data' => $users], 200);
    }

    public function index_employee(): JsonResponse
    {
        // Paginate the users, 20 per page
        $users = User::paginate(20);

        return response()->json(['success' => 'Users retrieved successfully.', 'data' => $users], 200);
    }

    public function user_by_id($id): JsonResponse
    {
        // Use the find() method to get the user by ID with deliveries count and delivery products eager loaded
        $user = User::withCount('deliveries')->with('deliveries.deliveryProducts')->find($id);

        // If the user is not found, return a 404 response
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Remove any existing appendages of deliveries_count if present
        $user->makeVisible('deliveries_count');

        // Return the user data as JSON
        return response()->json($user);
    }

// End VIEW ALL USER

// Start CREATE USER
    /**
     * Create a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8',
            'user_type_id' => 'required|integer',
            'number' => 'required|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
        $user = User::create($input);

        return response()->json(['success' => 'User created successfully.', 'data' => $user], 201);
    }
// End CREATE USER

// Start DISPLAY SPECIFIC USER
    /**
     * Display the specified user.
     *`
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        return response()->json(['success' => 'User retrieved successfully.', 'data' => $user], 200);
    }
// End DISPLAY SPECIFIC USER


    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_type_id' => 'sometimes|required|integer',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
            'password' => 'sometimes|required|string|min:8',
            'number' => 'sometimes|required|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $input = $request->all();

        if (isset($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        }

        $user->update($input);

        return response()->json(['success' => 'User updated successfully.', 'data' => $user], 200);
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $user->delete();

        return response()->json(['success' => 'User deleted successfully.'], 200);
    }


    // LOGIN/LOGOUT SESSION AREA

    // Start LOGIN
    /**
     * Handle user login and issue a token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function login(Request $request)
{
    // Validate the login input
    $validator = Validator::make($request->all(), [
        'username' => 'required|string|max:255',
        'password' => 'required|string|min:5',
    ]);

    // Return validation errors if validation fails
    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    $credentials = $request->only('username', 'password');

    // Check user credentials
    if (!Auth::attempt($credentials)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // Retrieve the authenticated user
    /** @var \App\Models\User $user **/
    $user = Auth::user();

    // Check if the user has an associated user type
    $userType = $user->userType; // Assuming you've defined a relationship in the User model

    if (!$userType) {
        return response()->json(['error' => 'User type not found for this account.'], 403);
    }

    // Generate an access token
    $token = $user->createToken('MyApp')->accessToken;

    return response()->json([
        'success' => 'User logged in successfully.',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'user_type' => $userType->user_type, // Include user type name
            'user_type_id' => $userType->id, // Include user type ID
        ],
    ], 200);
}


 // End LOGIN

 // Start LOGOUT
      /**
      * Handle user logout and revoke token.
      *
      * @param  \Illuminate\Http\Request  $request
      * @return \Illuminate\Http\JsonResponse
      */
     public function logout(Request $request)
     {
         if (Auth::user()) {
             $request->user()->token()->revoke();

             return response()->json([
                 'success' => true,
                 'message' => 'Logged out successfully',
             ], 200);
         } else {
             return response()->json([
                 'success' => false,
                 'message' => 'Logged out failed',
             ], 401);
         }
     }
 // End LOGOUT

}
