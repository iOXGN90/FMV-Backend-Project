<?php

namespace App\Http\Controllers\api\deliveries;

use App\Http\Controllers\API\BaseController;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Delivery_View_Report extends BaseController
{
    public function ViewReport($delivery_id)
    {
        try {
            $delivery = Delivery::with([
                'deliveryProducts.product.productDetails', // Access product details through products
                'images',
                'user',
            ])->find($delivery_id);

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Calculate `timeExceeded` based on `delivered_at`
            $timeExceeded = false; // Default to false
            if ($delivery->delivered_at) {
                $deliveredAtPlusOneMinute = $delivery->delivered_at->copy()->addMinute(1); // Add 1 minute to `delivered_at`
                // $deliveredAtPlusSevenDays = $delivery->delivered_at->copy()->addDays(7); // Add 7 days to `delivered_at`

                $timeExceeded = now()->greaterThanOrEqualTo($deliveredAtPlusOneMinute); // Check if current time exceeds the threshold
            }

            $baseUrl = config('app.url');
            $images = $delivery->images->map(function ($image) use ($baseUrl) {
                return [
                    'id' => $image->id,
                    'url' => $baseUrl . '/' . $image->url,
                    'created_at' => $image->created_at->format('m/d/Y H:i'),
                ];
            });

            // Extract product details and calculate return information
            $products = $delivery->deliveryProducts->map(function ($deliveryProduct) {
                $return = $deliveryProduct->returns->first(); // Get the first return if exists
                $productDetail = $deliveryProduct->product->productDetails->first(); // Get the first product detail

                return [
                    'delivery_product_id' => $deliveryProduct->id, // Add delivery_product ID
                    'product_id' => $deliveryProduct->product_id,
                    'return_status' => $return ? $return->status : 'NR', // Default to 'NR' if no return found
                    'product_name' => $deliveryProduct->product->product_name,
                    'quantity_delivered' => $deliveryProduct->quantity,
                    'no_of_damages' => $deliveryProduct->no_of_damages,
                    'intact_quantity' => $deliveryProduct->quantity - $deliveryProduct->no_of_damages,
                    'price' => $productDetail ? $productDetail->price : null, // Include price if exists
                ];
            });

            $user = $delivery->user;

            return response()->json([
                'delivery' => [
                    'delivery_id' => $delivery->id,
                    'purchase_order_id' => $delivery->purchaseOrder->id,
                    'delivery_no' => $delivery->delivery_no,
                    'notes' => $delivery->notes,
                    'status' => $delivery->status,
                    'created_at' => $delivery->created_at->format('m/d/Y H:i'),
                    'updated_at' => $delivery->updated_at->format('m/d/Y H:i'), // Include `updated_at` in response
                    'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('m/d/Y H:i') : null, // Include `delivered_at` in response
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'number' => $user->number,
                ],
                'images' => $images,
                'products' => $products,
                'time_exceeded' => $timeExceeded ? 'yes' : 'no', // Return 'yes' or 'no' as a string
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in ViewReport:', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
