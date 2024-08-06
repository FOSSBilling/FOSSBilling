<?php

class FBWordPressIntegration extends \Box\Mod\Module
{
    public function init()
    {
        $this->registerHooks();
    }

    protected function registerHooks()
    {
        // Register hooks for client actions
        $this->di['hooks']->on('after_client_signup', [$this, 'onAfterClientSignup']);
        $this->di['hooks']->on('after_client_update', [$this, 'onAfterClientUpdate']);
        $this->di['hooks']->on('after_client_delete', [$this, 'onAfterClientDelete']);
    }

    public function onAfterClientSignup(\Box\Event $event)
    {
        $params = $event->getParameters();
        $client = $this->di['db']->load('Client', $params['id']);
        FBWordPressSender::sendMessage([
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
        ], 'customer_added');
    }

    public function onAfterClientUpdate(\Box\Event $event)
    {
        $params = $event->getParameters();
        $client = $this->di['db']->load('Client', $params['id']);
        FBWordPressSender::sendMessage([
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
        ], 'customer_updated');
    }

    public function onAfterClientDelete(\Box\Event $event)
    {
        $params = $event->getParameters();
        FBWordPressSender::sendMessage([
            'id' => $params['id'],
        ], 'customer_deleted');
    }

    public static function getConfig()
    {
        return [
            'id'           => 'wp_integration',
            'type'         => 'mod',
            'name'         => 'WordPress Integration',
            'description'  => 'Integrates FOSSBilling with WordPress using RabbitMQ',
            'icon_url'     => 'icon.png',
            'homepage_url' => '',
            'author'       => 'Hamza',
            'author_url'   => '',
            'license'      => '',
            'version'      => '1.0',
        ];
    }
}
?>