<?php


// Start Import

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\User\UserTypeController;
use App\Http\Controllers\API\User\UserController;
use App\Http\Controllers\API\User\User_ViewOverview;

use App\Http\Controllers\API\Product\CategoryController;
use App\Http\Controllers\API\Product\Product_View;
use App\Http\Controllers\API\Product\ProductRestockController;
use App\Http\Controllers\API\Product\ProductController;

use App\Http\Controllers\API\Deliveries\DeliveryController;
use App\Http\Controllers\API\Deliveries\Deliveries_View_OnDelivery_EmployeeID;
use App\Http\Controllers\API\Deliveries\Deliveries_View_Pending;
use App\Http\Controllers\API\Deliveries\Deliveries_View_Success;
use App\Http\Controllers\API\Deliveries\Deliveries_View;

use App\Http\Controllers\API\Deliveries\Deliveries_Returns;
use App\Http\Controllers\api\deliveries\Delivery_View_Report;
use App\Http\Controllers\API\Deliveries\Deliveries_Cancel;

use App\Http\Controllers\API\PurchaseOrder\SaleTypeController;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrderController;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_AssignEmployeeController;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_ViewDeliveries;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_ViewWalkIns;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_WalkIn;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_AdminConfirms;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_Edit;
use App\Http\Controllers\API\PurchaseOrder\PurchaseOrder_GetRemainingBalanceOfProductToDeliver;

use App\Http\Controllers\API\SalesInsight\PurchaseOrder_SalesInsights_View;
use App\Http\Controllers\API\SalesInsight\TopProductSales;

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
    Route::post('user-type/{id}/update', [UserTypeController::class, 'update']);
    Route::delete('user-type/{id}/delete', [UserTypeController::class, 'destroy']);
// End User type

// Start User
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/employee', [UserController::class, 'index_employee']);
    Route::post('users', [UserController::class, 'create']);
    Route::get('users/{id}', [UserController::class, 'user_by_id']);
    Route::put('user/{id}/update', [UserController::class, 'update']);
    Route::delete('users/{id}/delete', [UserController::class, 'destroy']);
    Route::get('users-limited', [UserController::class, 'limited']);
    // End User


// Start Category
    Route::post('categories', [CategoryController::class, 'create']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::post('categories/{id}/update', [CategoryController::class, 'update']);
    Route::delete('categories/{id}/delete', [CategoryController::class, 'destroy']);
    // Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
// End Category

// Start Sale Type -- apiResource uses all method in one call
    Route::get('sale-type', [SaleTypeController::class, 'index']);
    Route::post('sale-type', [SaleTypeController::class, 'store']);
    Route::delete('sale-type/{id}/delete', [SaleTypeController::class, 'destroy']);
    Route::post('sale-type/{sale_type_id}/update', [SaleTypeController::class, 'update']);
// End Sale Type


//* Start Purchase Order | Delivery and Walk-in -- The Walk-in still uses the table of purchase-order table

    //! Start View All Purchase Order
        Route::get('purchase-orders-delivery', [PurchaseOrder_ViewDeliveries::class, 'index_purchase_order']);
        Route::get('purchase-orders-delivery/pending', [PurchaseOrder_ViewDeliveries::class, 'pending_purchase_order']);
        Route::get('purchase-orders-delivery/{id}', [PurchaseOrder_ViewDeliveries::class, 'show_purchase_order']);
        Route::get('purchase-orders-delivery-record/{id}', [PurchaseOrder_ViewDeliveries::class, 'show_deliveries_by_purchase_order']);
        Route::get('purchase-orders-delivery-latest', [PurchaseOrder_ViewDeliveries::class, 'latest_purchase_orders']);

        Route::post('/purchase-orders/{purchaseOrderId}/cancel', [PurchaseOrderController::class, 'cancelPurchaseOrder']);

        Route::get('purchase-orders-get-remaining-balance/{purchaseOrderId}', [PurchaseOrder_GetRemainingBalanceOfProductToDeliver::class, 'getRemainingQuantities']);

        //! End View All Purchase Order

        // Start Purchase Order - Delivery
        Route::post('purchase-orders-delivery', [PurchaseOrderController::class, 'create_purchase_order_delivery']);
        Route::post('/purchase-orders/{id}/update-date', [PurchaseOrderController::class, 'updatePurchaseOrderDate']);

        Route::put('purchase-orders-delivery', [PurchaseOrderController::class, 'update']);
        // End Delivery

        // Start Walk in
            Route::get('purchase-orders/walk-in', [PurchaseOrder_ViewWalkIns::class, 'index_walk_in']);
            Route::post('purchase-orders/create/walk-in', [PurchaseOrder_WalkIn::class, 'create_walk_in']);
        // End Walk in

    //! Start Assign Delivery
        Route::post('assign-employee', [PurchaseOrder_AssignEmployeeController::class, 'assign_employee']);
        Route::post('remove-employee', [PurchaseOrder_AssignEmployeeController::class, 'remove_assigned_employee']);
    //! End Assign Delivery

    //! Start Delivery Update - delivery man is assigned to its purchase-order, this route will be initiated by the delivery man depending on its "status"!!!

            Route::post('update-delivery/{delivery_id}', [DeliveryController::class, 'update_delivery']);
            Route::post('update-delivery/{delivery_id}/final', [DeliveryController::class, 'final_update']);

    //! End Delivery Update

    //! Start Get Pending/Success User Delivery
        // Route::get('my-deliveries/sample', [Deliveries_View::class, 'sample']);
        Route::get('deliveries/index', [Deliveries_View::class, 'index']);
        Route::get('deliveries/{deliveryId}/product-lists', [Deliveries_View::class, 'getDeliveryProducts']);
        Route::get('deliveries/overview', [Deliveries_View::class, 'deliveryCount']);

        // Update function for Web incase
        Route::put('deliveries/{deliveryId}/update', [Deliveries_View::class, 'updateDeliveryDetails']);

        Route::put('deliveries/{deliveryId}/cancel', [Deliveries_Cancel::class, 'cancelDelivery']);

        Route::get('my-deliveries/pending', [Deliveries_View_Pending::class, 'pending_deliveries']);
        Route::get('my-deliveries/pending/{deliveryman_id}', [Deliveries_View_Pending::class, 'pending_deliveries_by_id']);

        Route::get('my-deliveries/successful', [Deliveries_View_Success::class, 'successful_deliveries']);


        // Start View Report
            Route::get('my-deliveries/on-deliveryman/{deliveryman_id}', [Deliveries_View_OnDelivery_EmployeeID::class, 'on_delivery_by_deliveryman_id']);
            Route::post('/deliveries/return', [Deliveries_Returns::class, 'createReturns']);
            Route::get('/deliveries/{delivery_id}/report', [Delivery_View_Report::class, 'ViewReport']);

        // End View Damages

        //! End Get Pending/Success User Delivery

    //! Start Admin Initiates - Admin Reviews
        Route::post('purchase-orders-/{delivery_id}/final-update-delivery', [PurchaseOrder_AdminConfirms::class, 'update_to_success']);
    //! End Admin Initiates - Admin Reviews

    //! View remaining quantity to deliver
        Route::get('my-deliveries/remaining-balance/{id}', [PurchaseOrder_ViewDeliveries::class,'getRemainingToDeliver']);
    //! View remaining quantity to deliver

//* End Purchase Order | Delivery and Walk-in

// Start Product
    Route::post('products', [ProductController::class, 'create']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::get('products', [Product_View::class, 'index']);
    Route::get('products-overview', [Product_View::class, 'index_overview']);


// End Product

    Route::get('view/{product_id}/per-product-restock/', [ProductRestockController::class, 'productTransactions']);
    Route::get('view/reorder-level', [ProductRestockController::class, 'reorderLevel']);

// Start Product ReStocks
    Route::post('products-restock', [ProductRestockController::class, 'create']);
    Route::get('products-restock', [ProductRestockController::class, 'index']);
    Route::get('products-restock/{id}', [ProductRestockController::class, 'show']);
    Route::put('products-restock/{id}', [ProductRestockController::class, 'update']);
    Route::delete('products-restock', [ProductRestockController::class, 'destroy']);
// End Product ReStocks


// Sales and Insights

    // Month
        Route::get('Insights/View/Month-Data', [PurchaseOrder_SalesInsights_View::class, 'MonthData']);
        Route::get('Insights/View/Month-Data/Chart', [PurchaseOrder_SalesInsights_View::class, 'MonthChartData']);
        Route::get('Insights/View/Month-Data/Top-3-Products', [TopProductSales::class, 'topThreeProducts']);
        Route::get('Insights/View/Month-Data/Top-Sold-Products', [TopProductSales::class, 'topSoldProducts']);
        Route::get('Insights/View/Month-Data/Top-Damaged-Products', [TopProductSales::class, 'topDamagedProducts']);
    // Month

    // Annual
        Route::get('Insights/View/Annual-Data/', [PurchaseOrder_SalesInsights_View::class, 'AnnualData']);
        Route::get('Insights/View/Annual-Data/Chart', [PurchaseOrder_SalesInsights_View::class, 'AnnualChartData']);
        Route::get('Insights/View/Annual-Data/Top-3-Products', [TopProductSales::class, 'annualTopThreeProducts']);
        Route::get('Insights/View/Annual-Data/Top-Sold-Products', [TopProductSales::class, 'annualTopSoldProducts']);
        Route::get('Insights/View/Annual-Data/Top-Damaged-Products', [TopProductSales::class, 'annualTopDamagedProducts']);
    // Annual

// Sales and Insights


    // EDIT and Cancel

    Route::get('PurchaseOrder/View/{purchase_order_id}', [PurchaseOrder_Edit::class, 'getPurchaseOrderDetails']);
    Route::post('PurchaseOrder/Edit/{purchase_order_id}/Update', [PurchaseOrder_Edit::class, 'update_purchase_order']);



    // Route::get('Insights/Monthly-Data', [PurchaseOrder_SalesInsights_View::class, 'monthlyData']);
    // Route::get('Insights/Monthly-Records', [PurchaseOrder_SalesInsights_View::class, 'recordPerMonths']);
    // Route::get('Insights/TopSold-Items', [TopProductSales::class, 'topFiveProducts']);
