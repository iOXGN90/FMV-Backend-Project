<?php


// Start Import

    use App\Http\Controllers\API\WalkInController;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\API\CategoryController;
    use App\Http\Controllers\API\UserTypeController;
    use App\Http\Controllers\API\UserController;
    use App\Http\Controllers\API\LoginController;
    use App\Http\Controllers\API\PurchaseOrderController;
    use App\Http\Controllers\API\ProductRestockController;
    use App\Http\Controllers\API\ProductController;
    use App\Http\Controllers\api\DeliveryController;
    use App\Http\Controllers\api\SaleTypeController;
    use App\Http\Controllers\api\AssignEmployeeController;

// End Import

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

// Start Login/Logout
    Route::post('login', [UserController::class, 'login']);
    Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:api');
// End Login/Logout

// Start User type
    Route::post('user-type', [UserTypeController::class, 'create']);
    Route::get('user-type', [UserTypeController::class, 'index']);
    Route::delete('user-type/{id}', [UserTypeController::class, 'delete']);
// End User type

// Start User
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'create']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
// End User


// Start Category
    Route::post('categories', [CategoryController::class, 'create']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
// End Category

// Start Sale Type -- apiResource uses all method in one call
    Route::apiResource('sale-types', SaleTypeController::class);
// End Sale Type


//* Start Purchase Order and Walk-in -- The Walk-in still uses the table of purchase-order table

    //! Start View All Purchase Order
        Route::get('purchase-orders',[PurchaseOrderController::class, 'index']);
    //! End View All Purchase Order

    //! Start Delivery
        Route::get('purchase-orders-delivery', [PurchaseOrderController::class, 'index_purchase_order']);
        Route::post('purchase-orders-delivery', [PurchaseOrderController::class, 'create_purchase_order']);
        Route::put('purchase-orders-delivery', [PurchaseOrderController::class, 'update']);
        Route::get('purchase-orders-delivery/{id}', [PurchaseOrderController::class, 'show']);
    //! End Delivery

    //! Start Walk in
        Route::post('purchase-orders-walk-in', [WalkInController::class, 'create_walk_in']);
        Route::get('purchase-orders-walk-in', [WalkInController::class, 'index_walk_in']);
    //! End Walk in

    //! Start Assign Delivery
        Route::post('assign-employee', [AssignEmployeeController::class, 'assign_employee']);
        Route::post('remove-employee', [AssignEmployeeController::class, 'remove_employee']);
    //! End Assign Delivery

    //! Start Show Pending/Success User Delivery
        Route::post('my-deliveries/pending', [DeliveryController::class, 'my_pending_deliveries']);
        Route::post('my-deliveries/successful', [DeliveryController::class, 'my_successful_deliveries']);
    //! End Show Pending/Success User Delivery

//* End Purchase Order and Walk-in

// Start Product
    Route::post('products', [ProductController::class, 'create']);
    Route::get('products', [ProductController::class, 'index']);
// End Product

// Start Product ReStocks
    Route::post('products-restock', [ProductRestockController::class, 'create']);
    Route::get('products-restock', [ProductRestockController::class, 'index']);
    Route::get('products-restock/{id}', [ProductRestockController::class, 'show']);
    Route::put('products-restock/{id}', [ProductRestockController::class, 'update']);
    Route::delete('products-restock', [ProductRestockController::class, 'destroy']);
// End Product ReStocks


