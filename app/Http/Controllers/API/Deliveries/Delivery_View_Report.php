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
            // Fetch the delivery data with relationships
            $delivery = Delivery::with([
                'images',
                'damages.product',
                'user',
                'deliveryProducts.productDetail.product'
            ])
                ->where('id', $delivery_id)
                ->first();

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Get the base URL from the .env file
            $baseUrl = config('app.url'); // Fetches the APP_URL value from your .env file

            // Modify the images to include the full URL
            $images = $delivery->images->map(function ($image) use ($baseUrl) {
                return [
                    'id' => $image->id,
                    'url' => $baseUrl . '/' . $image->url, // Prepend base URL to relative path
                    'created_at' => $image->created_at->format('m/d/Y H:i'),
                ];
            });

            // Combine delivery products and damages into a unified structure
            $combinedProducts = $delivery->deliveryProducts->map(function ($deliveryProduct) use ($delivery) {
                $damage = $delivery->damages->firstWhere('product_id', $deliveryProduct->productDetail->product_id);

                return [
                    'product_id' => $deliveryProduct->productDetail->product_id ?? null,
                    'product_name' => $deliveryProduct->productDetail->product->product_name ?? 'Unknown Product',
                    'quantity_delivered' => $deliveryProduct->quantity,
                    'no_of_damages' => $damage->no_of_damages ?? 0,
                    'reported_at' => $damage->created_at->format('m/d/Y H:i') ?? null,
                    'intact_quantity' => $deliveryProduct->quantity - ($damage->no_of_damages ?? 0), // Adjust intact quantity
                ];
            });

            // Fetch the user information
            $user = $delivery->user;

            // Format the response
            return response()->json([
                'delivery' => [
                    'delivery_id' => $delivery->id,
                    'purchase_order_id' => $delivery->purchase_order_id,
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
                'products' => $combinedProducts, // Unified products array
            ], 200);
        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Error in ViewReport:', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
