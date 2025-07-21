<?php

namespace Box\Mod\Lambda;

use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public static function onAfterAdminOrderActivate(\Box_Event $event)
    {
        //I am not sure where to retrieve this, or it should come from the FOSSBilling config somewhere.
        $lambdaUrl = 'YOUR LAMBDA URL HERE';
        $params = $event->getParameters();
        $order_id = $params['id'];
        $payload = [
            'order_id' => $order_id
        ];

        try {
            $client = HttpClient::create(['bindto' => BIND_TO]);

            $response = $client->request('POST', $lambdaUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Source' => 'fossbilling'
                ],
                'json' => $payload,
                'timeout' => 30,
            ]);
        } catch (Exception $e) {
            error_log('Lambda gave an error: ' . $e->getMessage());
            throw new \FOSSBilling\Exception("An unexpected error occurred when calling Lambda for order {$order_id}: " . $e->getMessage());
        }
    }
}
