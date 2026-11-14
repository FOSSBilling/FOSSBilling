<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Controller;

use Symfony\Component\HttpFoundation\Response;

class Client implements \FOSSBilling\Core\Container\InjectionAwareInterface
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

    public function register(\FOSSBilling\Core\Http\App &$app): void
    {
        $app->get('/support', 'get_tickets', [], static::class);
        $app->get('/support/ticket-file/:id', 'get_ticket_file', ['id' => '[a-f0-9]{32}'], static::class);
        $app->get('/support/feedback/:token', 'get_feedback', ['token' => '[a-f0-9]{64}'], static::class);
        $app->get('/support/ticket/:id', 'get_ticket', [], static::class);
        $app->get('/support/contact-us', 'get_contact_us', [], static::class);
        $app->get('/support/contact-us/conversation/:hash', 'get_ticket_redirect', ['hash' => '[a-z0-9]+'], static::class);

        if ($this->di['mod']('support')->getService()->kbEnabled()) {
            $app->get('/support/kb', 'get_kb_index', [], static::class);
            $app->get('/support/kb/:category', 'get_kb_category', ['category' => '[a-z0-9-]+'], static::class);
            $app->get('/support/kb/:category/:slug', 'get_kb_article', ['category' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'], static::class);
        }
    }

    public function get_tickets(\FOSSBilling\Core\Http\App $app): string
    {
        $this->di['is_client_logged'];

        return $app->render('mod_support_tickets');
    }

    public function get_ticket(\FOSSBilling\Core\Http\App $app, $id): string|Response
    {
        $id = (string) $id;
        if (ctype_digit($id) && strlen($id) < 30) {
            $this->di['is_client_logged'];
            if (($merged = \Box\Mod\Support\TicketMerge::targetForClient($this->di, (int) $id, (int) $this->di['loggedin_client']->getId())) !== null) {
                return $app->redirect('support/ticket/' . $merged);
            }
            $ticket = $this->di['api_client']->support_ticket_get(['id' => $id]);
        } else {
            $ticket = $this->di['api_guest']->support_ticket_get(['hash' => $id]);
        }

        return $app->render('mod_support_ticket', ['ticket' => $ticket]);
    }

    public function get_contact_us(\FOSSBilling\Core\Http\App $app): string|Response
    {
        if ($this->di['auth']->isClientLoggedIn()) {
            return $app->redirect('support');
        }

        return $app->render('mod_support_contact_us');
    }

    /**
     * A redirect to keep old guest ticket links working.
     *
     * /support/contact-us/conversation/:hash -> /support/ticket/:hash
     */
    public function get_ticket_redirect(\FOSSBilling\Core\Http\App $app, $hash): Response
    {
        return $app->redirect('support/ticket/' . $hash);
    }

    public function get_feedback(\FOSSBilling\Core\Http\App $app, string $token): string
    {
        try {
            $page = \Box\Mod\Support\TicketFeedback::page($this->di, $token);
        } catch (\FOSSBilling\Core\Exception\InformationException) {
            $page = ['ticket' => [], 'staff' => [], 'state' => 'invalid'];
        }

        return $app->render('mod_support_feedback', ['feedback' => $page, 'feedback_token' => (string) $token]);
    }

    public function get_ticket_file(\FOSSBilling\Core\Http\App $app, string $id): Response
    {
        $this->di['is_client_logged'];
        $client = $this->di['loggedin_client'];

        $file = \Box\Mod\Support\TicketFiles::find($this->di, $id);
        if ($file === null || (int) $file['client_id'] !== (int) $client->getId()) {
            throw new \FOSSBilling\Core\Exception\InformationException('File not found.', [], 404);
        }

        return $this->serveTicketFile($file);
    }

    private function serveTicketFile(array $file): Response
    {
        $served = \Box\Mod\Support\TicketFiles::serve($file);
        $response = $served['disposition'] === 'inline'
            ? $this->di['response_factory']->file($served['name'], $served['content_type'], $served['path'])
            : $this->di['response_factory']->download($served['name'], $served['path']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /*
    * Support Knowledge Base.
    */
    public function get_kb_index(\FOSSBilling\Core\Http\App $app): string
    {
        return $app->render('mod_support_kb_index');
    }

    public function get_kb_category(\FOSSBilling\Core\Http\App $app, $category): string
    {
        $api = $this->di['api_guest'];
        $data = ['slug' => $category];
        $model = $api->support_kb_category_get($data);

        return $app->render('mod_support_kb_category', ['category' => $model]);
    }

    public function get_kb_article(\FOSSBilling\Core\Http\App $app, $category, $slug): string
    {
        $api = $this->di['api_guest'];
        $data = ['slug' => $slug];
        $article = $api->support_kb_article_get($data);

        return $app->render('mod_support_kb_article', ['article' => $article]);
    }
}
