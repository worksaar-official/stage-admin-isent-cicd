<?php

namespace App\WebSockets\Handler;

use App\Models\DeliveryMan;
use App\Models\DeliveryHistory;
use App\Models\Order;
use Ratchet\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use BeyondCode\LaravelWebSockets\Apps\App;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use BeyondCode\LaravelWebSockets\QueryParameters;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\UnknownAppKey;


class DMLocationSocketHandler implements MessageComponentInterface
{

    function onMessage(ConnectionInterface $from, MessageInterface $msg)
    {
        $data = json_decode($msg->getPayload(), true);

        // Check if the message contains the necessary data for recording
        if (
            isset($data['token'], $data['longitude'], $data['latitude'], $data['location'])
        ) {
            $dm = DeliveryMan::where(['auth_token' => $data['token']])->first();

            if ($dm) {
                // Update delivery man's current location
                $dm->update([
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude']
                ]);

                // Record delivery history
                $deliveryHistory = DeliveryHistory::updateOrCreate(['delivery_man_id' => $dm['id']], [
                    'longitude' => $data['longitude'],
                    'latitude' => $data['latitude'],
                    'time' => now(),
                    'location' => $data['location'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Send a response back to the client indicating successful recording
                $from->send(json_encode(['message' => 'location recorded']));

                // Broadcast update to order tracking subscribers
                $this->broadcastLocationUpdate($dm, $data);
            }
        }
    }


    function onOpen(ConnectionInterface $conn)
    {
        $this->verifyAppKey($conn)->generateSocketId($conn);

    }

    function onClose(ConnectionInterface $conn)
    {
        // TODO: Implement onClose() method.
    }

    function onError(ConnectionInterface $conn, \Exception $e)
    {
        // TODO: Implement onError() method.
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

    /**
     * Broadcast location update to order tracking subscribers
     *
     * @param DeliveryMan $deliveryMan
     * @param array $locationData
     * @return void
     */
    protected function broadcastLocationUpdate($deliveryMan, $locationData)
    {
        // Find orders assigned to this delivery man that are in progress
        $orders = Order::where('delivery_man_id', $deliveryMan->id)
            ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
            ->get();

        // For each order, we would broadcast the update
        // In a real implementation, you would send this data to the OrderTrackingSocketHandler
        foreach ($orders as $order) {
            // This is where you would implement the actual broadcasting
            // For now, we'll just log it
            \Log::info("Broadcasting location update for order {$order->id}", [
                'delivery_man' => $deliveryMan->id,
                'latitude' => $locationData['latitude'],
                'longitude' => $locationData['longitude'],
                'location' => $locationData['location']
            ]);
        }
    }
}