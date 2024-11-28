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

            $baseUrl = config('app.url');
            $images = $delivery->images->map(function ($image) use ($baseUrl) {
                return [
                    'id' => $image->id,
                    'url' => $baseUrl . '/' . $image->url,
                    'created_at' => $image->created_at->format('m/d/Y H:i'),
                ];
            });

            // Extract the price from the first product detail associated with the product
            $products = $delivery->deliveryProducts->map(function ($deliveryProduct) {
                $productDetail = $deliveryProduct->product->productDetails->first(); // Assuming you want the first product detail
                return [
                    'delivery_product_id' => $deliveryProduct->id, // Adding delivery_product ID
                    'product_id' => $deliveryProduct->product_id,
                    'product_name' => $deliveryProduct->product->product_name,
                    'quantity_delivered' => $deliveryProduct->quantity,
                    'no_of_damages' => $deliveryProduct->no_of_damages,
                    'intact_quantity' => $deliveryProduct->quantity - $deliveryProduct->no_of_damages,
                    'price' => $productDetail ? $productDetail->price : null, // Check if product detail exists
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
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'number' => $user->number,
                ],
                'images' => $images,
                'products' => $products,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in ViewReport:', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
