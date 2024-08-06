<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class FBWordPressSender
{
    private static function getConnection()
    {
        return new AMQPStreamConnection('192.168.129.101', 5672, 'hamza', 'student1', 'myvhost');
    }

    public static function sendMessage($data, $type)
    {
        try {
            $connection = self::getConnection();
            $channel = $connection->channel();

            $message = new AMQPMessage(json_encode([
                'type' => $type,
                'data' => $data
            ]));

            $channel->basic_publish($message, '', 'fossbilling_to_wordpress');

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log('FOSSBilling WordPress Integration: Error sending message - ' . $e->getMessage());
        }
    }
}
?>
