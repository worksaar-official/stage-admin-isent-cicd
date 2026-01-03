<?php

namespace App\Helpers;

use App\Models\Order;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Factory;
use React\Socket\Connector as SocketConnector;

class WebSocketBroadcaster
{
    /**
     * Broadcast order update to all connected clients
     *
     * @param int $orderId
     * @return void
     */
    public static function broadcastOrderUpdate($orderId)
    {
        // In a real implementation, you would connect to your WebSocket server
        // and broadcast the update to all clients subscribed to this order
        // For now, we'll just log the update
        \Log::info("Order update broadcasted for order ID: " . $orderId);
    }

    /**
     * Send real-time location update for delivery man
     *
     * @param int $deliveryManId
     * @param float $latitude
     * @param float $longitude
     * @param string $location
     * @return void
     */
    public static function sendDeliveryManLocationUpdate($deliveryManId, $latitude, $longitude, $location)
    {
        // This would send a location update to the WebSocket server
        \Log::info("Delivery man location update sent", [
            'delivery_man_id' => $deliveryManId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location' => $location
        ]);
    }

    /**
     * Get order tracking data for real-time updates
     *
     * @param int $orderId
     * @return array
     */
    public static function getOrderTrackingData($orderId)
    {
        $order = Order::with([
            'store:id,name,latitude,longitude,address,phone',
            'delivery_man:id,f_name,l_name,phone,image',
            'delivery_history'
        ])->find($orderId);

        if (!$order) {
            return null;
        }

        $deliveryAddress = json_decode($order->delivery_address, true);

        $trackingData = [
            'event' => 'order_data_update',
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'delivery_man' => $order->delivery_man ? [
                'id' => $order->delivery_man->id,
                'name' => $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name,
                'phone' => $order->delivery_man->phone,
                'image' => $order->delivery_man->image,
                'current_location' => [
                    'latitude' => $order->delivery_man->latitude ?? null,
                    'longitude' => $order->delivery_man->longitude ?? null
                ]
            ] : null,
            'delivery_path' => [],
            'map_data' => [
                'delivery_man_location' => $order->delivery_man ? [
                    'latitude' => $order->delivery_man->latitude ?? null,
                    'longitude' => $order->delivery_man->longitude ?? null,
                    'name' => $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name
                ] : null
            ]
        ];

        // Add delivery history points for the path
        if ($order->delivery_history) {
            foreach ($order->delivery_history as $history) {
                $trackingData['delivery_path'][] = [
                    'latitude' => $history->latitude ?? null,
                    'longitude' => $history->longitude ?? null,
                    'location' => $history->location ?? null,
                    'timestamp' => $history->created_at ? (is_object($history->created_at) ? $history->created_at->format('Y-m-d H:i:s') : (is_string($history->created_at) ? $history->created_at : null)) : null
                ];
            }
        }

        return $trackingData;
    }
}
