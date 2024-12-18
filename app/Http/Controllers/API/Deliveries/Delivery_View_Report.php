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
                'deliveryProducts.product.productDetails',
                'images',
                'user',
            ])->find($delivery_id);

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            $timeExceeded = false;
            if ($delivery->delivered_at) {
                $deliveredAtPlusOneMinute = $delivery->delivered_at->copy()->addMinute(1);
                $timeExceeded = now()->greaterThanOrEqualTo($deliveredAtPlusOneMinute);
            }

            // Set the correct base URL for the images
            $baseUrl = url('storage'); // This will point to 'http://192.168.1.13:3000/storage'

            $images = $delivery->images->map(function ($image) use ($baseUrl) {
                return [
                    'id' => $image->id,
                    // Remove '/images' before appending the image path again
                    'url' => $baseUrl . '/' . $image->url, // This will correctly result in 'http://192.168.1.13:3000/storage/images/image.jpg'
                    'created_at' => $image->created_at->format('m/d/Y H:i'),
                ];
            });

            $products = $delivery->deliveryProducts->map(function ($deliveryProduct) {
                $return = $deliveryProduct->returns->first();
                $productDetail = $deliveryProduct->product->productDetails->first();

                return [
                    'delivery_product_id' => $deliveryProduct->id,
                    'product_id' => $deliveryProduct->product_id,
                    'return_status' => $return ? $return->status : 'NR',
                    'product_name' => $deliveryProduct->product->product_name,
                    'quantity_delivered' => $deliveryProduct->quantity,
                    'no_of_damages' => $deliveryProduct->no_of_damages,
                    'intact_quantity' => $deliveryProduct->quantity - $deliveryProduct->no_of_damages,
                    'price' => $productDetail ? $productDetail->price : null,
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
                    'updated_at' => $delivery->updated_at->format('m/d/Y H:i'),
                    'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('m/d/Y H:i') : null,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'number' => $user->number,
                ],
                'images' => $images,
                'products' => $products,
                'time_exceeded' => $timeExceeded ? 'yes' : 'no',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in ViewReport:', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
