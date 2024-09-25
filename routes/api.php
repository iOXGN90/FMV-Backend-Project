<?php


// Start Import
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\API\CategoryController;
    use App\Http\Controllers\API\UserTypeController;
    use App\Http\Controllers\API\UserController;
    use App\Http\Controllers\API\PurchaseOrderController;
    use App\Http\Controllers\API\ProductRestockController;
    use App\Http\Controllers\API\ProductController;
    use App\Http\Controllers\API\DeliveryController;
    use App\Http\Controllers\API\SaleTypeController;
    use App\Http\Controllers\API\Deliveries_View;
    use App\Http\Controllers\API\Delivery_ViewDamages;
    use App\Http\Controllers\API\PurchaseOrder_AssignEmployeeController;
    use App\Http\Controllers\API\PurchaseOrder_ViewDeliveries;
    use App\Http\Controllers\API\PurchaseOrder_ViewWalkIns;
    use App\Http\Controllers\API\PurchaseOrder_WalkIn;
    use App\Http\Controllers\API\PurchaseOrder_AdminConfirms;
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
        Route::get('purchase-orders',[PurchaseOrder_ViewDeliveries::class, 'index']); //No filter
        Route::get('purchase-orders-delivery', [PurchaseOrder_ViewDeliveries::class, 'index_purchase_order']);
        Route::get('purchase-orders-delivery-pending', [PurchaseOrder_ViewDeliveries::class, 'pending_purchase_order']);
        Route::get('purchase-orders-delivery/{id}', [PurchaseOrder_ViewDeliveries::class, 'show_purchase_order']);

        Route::get('purchase-orders-walk-in', [PurchaseOrder_ViewWalkIns::class, 'index_walk_in']);
    //! End View All Purchase Order

    //! Start Delivery
        Route::post('purchase-orders-delivery', [PurchaseOrderController::class, 'create_purchase_order_delivery']);
        Route::put('purchase-orders-delivery', [PurchaseOrderController::class, 'update']);
    //! End Delivery

    //! Start Walk in
        Route::post('purchase-orders-walk-in', [PurchaseOrder_WalkIn::class, 'create_walk_in']);
    //! End Walk in

    //! Start Assign Delivery
        Route::post('assign-employee', [PurchaseOrder_AssignEmployeeController::class, 'assign_employee']);
        Route::post('remove-employee', [PurchaseOrder_AssignEmployeeController::class, 'remove_assigned_employee']);
    //! End Assign Delivery

    //! Start Delivery Update - delivery man is assigned to its purchase-order, this route will be initiated by the delivery man depending on its "status"!!!
        Route::put('update-delivery/{id}', [DeliveryController::class, 'update_delivery']);
        // Samples
        Route::put('update-delivery-status-OD/{id}', [DeliveryController::class, 'update_delivery_status_OD']);
        Route::put('update-delivery-status-P/{id}', [DeliveryController::class, 'update_delivery_status_P']);
    //! End Delivery Update

    //! Start Get Pending/Success User Delivery
        Route::get('my-deliveries/sample', [Deliveries_View::class, 'sample']);
        Route::get('my-deliveries/pending', [Deliveries_View::class, 'pending_deliveries']);
        Route::get('my-deliveries/on-delivery', [Deliveries_View::class, 'on_delivery']);
        Route::get('my-deliveries/successful', [Deliveries_View::class, 'successful_deliveries']);
        Route::get('my-deliveries/failed', [Deliveries_View::class, 'failed_deliveries']);
    //! End Get Pending/Success User Delivery

    //! Start Admin Initiates if Success or Failed
        Route::put('purchase-orders-admin-update/{id}', [PurchaseOrder_AdminConfirms::class, 'update_to_success']);
    //! End Admin Initiates if Success or Failed

    //! View remaining quantity to deliver
        Route::get('my-deliveries/remaining-balance/{id}', [PurchaseOrder_ViewDeliveries::class,'getRemainingToDeliver']);
    //! View remaining quantity to deliver

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

// Start View Damages
    Route::get('damages', [Delivery_ViewDamages::class, 'index']);
    Route::get('damages/filter-by-delivery', [Delivery_ViewDamages::class, 'filterByDelivery']);
// End View Damages


