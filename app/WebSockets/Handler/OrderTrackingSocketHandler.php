<?php

namespace App\WebSockets\Handler;

use App\Models\Order;
use App\Models\DeliveryHistory;
use Ratchet\ConnectionInterface;
use BeyondCode\LaravelWebSockets\Apps\App;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use BeyondCode\LaravelWebSockets\QueryParameters;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\UnknownAppKey;

class OrderTrackingSocketHandler implements MessageComponentInterface
{
    protected $clients;
    protected $subscriptions;

    public function __construct()
    {
        $this->clients = [];
        $this->subscriptions = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->verifyAppKey($conn)->generateSocketId($conn);
        $this->clients[$conn->resourceId] = $conn;
        
        // Send connection confirmation
        $conn->send(json_encode([
            'event' => 'connection_established',
            'message' => 'Connected to order tracking service'
        ]));
    }

    public function onMessage(ConnectionInterface $from, MessageInterface $msg)
    {
        $data = json_decode($msg->getPayload(), true);

        if (isset($data['event'])) {
            switch ($data['event']) {
                case 'subscribe_to_order':
                    if (isset($data['order_id'])) {
                        $this->subscribeToOrder($from, $data['order_id']);
                    }
                    break;
                    
                case 'unsubscribe_from_order':
                    if (isset($data['order_id'])) {
                        $this->unsubscribeFromOrder($from, $data['order_id']);
                    }
                    break;
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        // Remove client from all subscriptions
        if (isset($this->clients[$conn->resourceId])) {
            unset($this->clients[$conn->resourceId]);
        }
        
        // Remove client from all order subscriptions
        foreach ($this->subscriptions as $orderId => $subscribers) {
            if (isset($this->subscriptions[$orderId][$conn->resourceId])) {
                unset($this->subscriptions[$orderId][$conn->resourceId]);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->send(json_encode([
            'event' => 'error',
            'message' => $e->getMessage()
        ]));
        $conn->close();
    }

    protected function verifyAppKey(ConnectionInterface $connection)
    {
        $appKey = QueryParameters::create($connection->httpRequest)->get('appKey');
        if (! $app = App::findByKey($appKey)) {
            throw new UnknownAppKey($appKey);
        }
        $connection->app = $app;

        return $this;
    }

    protected function generateSocketId(ConnectionInterface $connection)
    {
        $socketId = sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
        $connection->socketId = $socketId;

        return $this;
    }

    protected function subscribeToOrder(ConnectionInterface $conn, $orderId)
    {
        // Validate order exists
        $order = Order::find($orderId);
        if (!$order) {
            $conn->send(json_encode([
                'event' => 'subscription_error',
                'message' => 'Order not found'
            ]));
            return;
        }

        // Add client to subscription list
        if (!isset($this->subscriptions[$orderId])) {
            $this->subscriptions[$orderId] = [];
        }
        
        $this->subscriptions[$orderId][$conn->resourceId] = $conn;
        
        // Send current order data
        $this->sendOrderData($conn, $orderId);
        
        // Confirm subscription
        $conn->send(json_encode([
            'event' => 'subscription_confirmed',
            'order_id' => $orderId,
            'message' => 'Subscribed to order tracking updates'
        ]));
    }

    protected function unsubscribeFromOrder(ConnectionInterface $conn, $orderId)
    {
        if (isset($this->subscriptions[$orderId][$conn->resourceId])) {
            unset($this->subscriptions[$orderId][$conn->resourceId]);
        }
        
        $conn->send(json_encode([
            'event' => 'unsubscribed',
            'order_id' => $orderId,
            'message' => 'Unsubscribed from order tracking updates'
        ]));
    }

    protected function sendOrderData(ConnectionInterface $conn, $orderId)
    {
        $order = Order::with([
            'store:id,name,latitude,longitude,address,phone',
            'delivery_man:id,f_name,l_name,phone,image,latitude,longitude',
            'delivery_history'
        ])->find($orderId);

        if ($order) {
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

            $conn->send(json_encode($trackingData));
        }
    }

    // Method to broadcast updates to all subscribers of an order
    public function broadcastOrderUpdate($orderId)
    {
        if (isset($this->subscriptions[$orderId])) {
            foreach ($this->subscriptions[$orderId] as $conn) {
                $this->sendOrderData($conn, $orderId);
            }
        }
    }

    // Method to broadcast location update to all subscribers of an order
    public function broadcastLocationUpdate($orderId, $locationData)
    {
        if (isset($this->subscriptions[$orderId])) {
            $updateData = [
                'event' => 'location_update',
                'order_id' => $orderId,
                'location' => $locationData
            ];
            
            foreach ($this->subscriptions[$orderId] as $conn) {
                $conn->send(json_encode($updateData));
            }
        }
    }
}