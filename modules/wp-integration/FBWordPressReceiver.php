<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

class FBWordPressReceiver
{
    public static function listen()
    {
        try {
            $connection = new AMQPStreamConnection('192.168.129.101', 5672, 'hamza', 'student1', 'myvhost');
            $channel = $connection->channel();

            $channel->queue_declare('wordpress_to_fossbilling', false, true, false, false);

            $callback = function ($msg) {
                $data = json_decode($msg->body, true);
                self::processMessage($data);
            };

            $channel->basic_consume('wordpress_to_fossbilling', '', false, true, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log('FOSSBilling WordPress Integration: RabbitMQ listening error - ' . $e->getMessage());
        }
    }

    private static function processMessage($data)
    {
        $di = include '/var/www/fossbilling/di.php';
        $clientService = $di['mod_service']('client');

        switch ($data['type']) {
            case 'add_customer':
                self::addCustomerToFOSSBilling($clientService, $data['data']);
                break;
            case 'update_customer':
                self::updateCustomerInFOSSBilling($clientService, $data['data']);
                break;
            case 'delete_customer':
                self::deleteCustomerFromFOSSBilling($clientService, $data['data']);
                break;
            default:
                error_log('FOSSBilling WordPress Integration: Unknown message type - ' . $data['type']);
        }
    }

    private static function addCustomerToFOSSBilling($clientService, $customerData)
    {
        try {
            $clientService->create($customerData);
        } catch (\Exception $e) {
            error_log('FOSSBilling WordPress Integration: Error creating customer - ' . $e->getMessage());
        }
    }

    private static function updateCustomerInFOSSBilling($clientService, $customerData)
    {
        try {
            $client = $clientService->findOneByEmail($customerData['email']);
            if ($client) {
                $clientService->update($client['id'], $customerData);
            }
        } catch (\Exception $e) {
            error_log('FOSSBilling WordPress Integration: Error updating customer - ' . $e->getMessage());
        }
    }

    private static function deleteCustomerFromFOSSBilling($clientService, $customerData)
    {
        try {
            $client = $clientService->findOneByEmail($customerData['email']);
            if ($client) {
                $clientService->delete($client['id']);
            }
        } catch (\Exception $e) {
            error_log('FOSSBilling WordPress Integration: Error deleting customer - ' . $e->getMessage());
        }
    }
}
?>