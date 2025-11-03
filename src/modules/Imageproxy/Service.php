<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Imageproxy;

use FOSSBilling\Environment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Image Proxy Service.
 *
 * Automatically proxies remote images in support tickets to prevent
 * IP/UA leakage when staff or clients view tickets.
 */
class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * Set dependency injection container.
     *
     * @param \Pimple\Container $di Dependency injection container
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * Get dependency injection container.
     *
     * @return \Pimple\Container|null Dependency injection container
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Event handler: Called after a client opens a new ticket.
     * Proxifies all images in the newly created ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterClientOpenTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyTicketMessages($event->getParameters()['id']);
    }

    /**
     * Event handler: Called after an admin replies to a ticket.
     * Proxifies all images in ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterAdminReplyTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyTicketMessages($event->getParameters()['id']);
    }

    /**
     * Event handler: Called after a client replies to a ticket.
     * Proxifies all images in ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterClientReplyTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyTicketMessages($event->getParameters()['id']);
    }

    /**
     * Event handler: Called after an admin opens a public ticket.
     * Proxifies all images in the newly created public ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterAdminPublicTicketOpen(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyPublicTicketMessages($event->getParameters()['id']);
    }

    /**
     * Event handler: Called after an admin replies to a public ticket.
     * Proxifies all images in public ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterAdminPublicTicketReply(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyPublicTicketMessages($event->getParameters()['id']);
    }

    /**
     * Event handler: Called after a guest replies to a public ticket.
     * Proxifies all images in public ticket messages.
     *
     * @param \Box_Event $event Event object containing ticket ID in parameters
     */
    public static function onAfterGuestPublicTicketReply(\Box_Event $event): void
    {
        $di = $event->getDi();
        $service = $di['mod_service']('imageproxy');
        $service->proxifyPublicTicketMessages($event->getParameters()['id']);
    }

    /**
     * Proxifies all remote images in messages for a given ticket.
     * Only updates messages that contain remote images.
     *
     * @param int $ticketId The ID of the ticket
     */
    public function proxifyTicketMessages(int $ticketId): void
    {
        $messages = $this->di['db']->find('SupportTicketMessage', 'support_ticket_id = ?', [$ticketId]);
        foreach ($messages as $msg) {
            $original = $msg->content;
            $proxified = $this->proxifyImages($original);
            if ($proxified !== $original) {
                $msg->content = $proxified;
                $this->di['db']->store($msg);
            }
        }
    }

    /**
     * Proxifies all remote images in messages for a given public ticket.
     * Only updates messages that contain remote images.
     *
     * @param int $ticketId The ID of the public ticket
     */
    public function proxifyPublicTicketMessages(int $ticketId): void
    {
        $messages = $this->di['db']->find('SupportPTicketMessage', 'support_p_ticket_id = ?', [$ticketId]);
        foreach ($messages as $msg) {
            $original = $msg->content;
            $proxified = $this->proxifyImages($original);
            if ($proxified !== $original) {
                $msg->content = $proxified;
                $this->di['db']->store($msg);
            }
        }
    }

    /**
     * Replaces remote image URLs in content with proxied URLs.
     * Supports both HTML <img> tags and Markdown image syntax.
     *
     * @param string $content The content to process
     *
     * @return string The content with proxified image URLs
     */
    public function proxifyImages(string $content): string
    {
        // HTML <img> tags - replace src attributes containing http(s) URLs
        $content = preg_replace_callback(
            '~<img[^>]+src=["\'](https?://[^"\']+)["\'][^>]*>~i',
            fn ($m) => $this->replaceWithProxy($m[0], $m[1]),
            $content
        );

        // Markdown images - replace URLs in ![alt](url) syntax
        $content = preg_replace_callback(
            '~!\[[^\]]*\]\((https?://[^)]+)\)~i',
            fn ($m) => str_replace($m[1], $this->makeProxyUrl($m[1]), $m[0]),
            $content
        );

        return $content;
    }

    /**
     * Generates a proxied URL for an external image.
     * Uses base64url encoding to safely pass the URL as a parameter.
     * Uses client area URL so images work in both admin and client views.
     *
     * @param string $url The original image URL
     *
     * @return string The proxied URL or original if not http(s)
     */
    protected function makeProxyUrl(string $url): string
    {
        // Only proxy http and https URLs
        if (!preg_match('~^https?://~i', $url)) {
            return $url;
        }

        // Base64url encode (URL-safe base64 without padding)
        $encoded = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        // Use client area URL (accessible by both clients and admins)
        return $this->di['url']->link('imageproxy/image', ['u' => $encoded]);
    }

    /**
     * Replaces the URL in a full HTML tag with the proxied URL.
     *
     * @param string $fullTag The complete HTML tag
     * @param string $url     The URL to replace
     *
     * @return string The tag with the proxied URL
     */
    protected function replaceWithProxy(string $fullTag, string $url): string
    {
        return str_replace($url, $this->makeProxyUrl($url), $fullTag);
    }

    /**
     * Fetches an external image via HTTP and validates it.
     * Uses Symfony HTTP Client for the request.
     *
     * @param string $url The URL of the image to fetch
     *
     * @return array{content_type: string, body: string} Array with content_type and body
     *
     * @throws \FOSSBilling\InformationException If the URL is invalid, image is too large, or fetch fails
     */
    public function fetchExternalImage(string $url): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new \FOSSBilling\InformationException('Unsupported protocol');
        }

        // Get configuration settings
        $config = $this->di['mod_config']('imageproxy');
        $maxSizeMB = $config['max_size_mb'] ?? 5;
        $timeoutSeconds = $config['timeout_seconds'] ?? 5;
        $maxDurationSeconds = $config['max_duration_seconds'] ?? 10;

        $maxBytes = $maxSizeMB * 1024 * 1024;

        try {
            $httpClient = \Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);
            $response = $httpClient->request('GET', $url, [
                'timeout' => $timeoutSeconds,
                'max_duration' => $maxDurationSeconds,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Referer' => 'https://www.google.com/',
                    'DNT' => '1',
                ],
            ]);

            // Check content type before downloading the entire body
            $contentType = $response->getHeaders()['content-type'][0] ?? 'application/octet-stream';
            if (!preg_match('~^image/(png|jpe?g|gif|webp|svg\+xml)~i', $contentType)) {
                throw new \FOSSBilling\InformationException('Refusing non-image content');
            }

            $body = $response->getContent();
            if (strlen($body) > $maxBytes) {
                throw new \FOSSBilling\InformationException('Image too large (max ' . $maxSizeMB . ' MB)');
            }

            return ['content_type' => $contentType, 'body' => $body];
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface |
                 \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
                     throw new \FOSSBilling\InformationException('Unable to fetch image: ' . $e->getMessage());
                 }
    }

    /**
     * Serves a proxied image using Symfony Response.
     * Called directly from Controller (not through API layer).
     * Follows the pattern used by Servicedownloadable for binary file serving.
     *
     * @param string $encodedUrl Base64url-encoded image URL
     *
     * @return void Outputs image and exits (no return in normal execution)
     *
     * @throws \FOSSBilling\InformationException If URL is invalid or image cannot be fetched
     */
    public function serveProxiedImage(string $encodedUrl): void
    {
        if (empty($encodedUrl)) {
            throw new \FOSSBilling\InformationException('Missing image URL parameter');
        }

        // Decode the base64url-encoded URL
        $url = base64_decode(strtr($encodedUrl, '-_', '+/'));
        if (!$url) {
            throw new \FOSSBilling\InformationException('Invalid image URL');
        }

        // Clean up common URL issues (escaped ampersands, etc.)
        $url = str_replace('\\&', '&', $url);  // Remove backslashes before ampersands
        $url = str_replace('\\"', '"', $url);  // Remove escaped quotes

        // Fetch the external image
        $image = $this->fetchExternalImage($url);

        // Send the image using Symfony Response (unless in testing environment)
        if (!Environment::isTesting()) {
            $response = new Response($image['body']);
            $response->headers->set('Content-Type', $image['content_type']);
            $response->headers->set('Content-Length', (string) strlen($image['body']));
            $response->headers->set('Cache-Control', 'private, max-age=300');
            $response->send();
            exit;  // Important: exit after sending binary response
        }
    }

    /**
     * Migrates all existing ticket messages to use proxified image URLs.
     * Scans both regular tickets (support_ticket_message) and public tickets (support_p_ticket_message).
     * This is a one-time operation to retroactively apply image proxy to existing content.
     *
     * @return array{processed: int, updated: int, images_found: int} Statistics about the migration
     */
    public function migrateExistingTickets(): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'images_found' => 0,
        ];

        // Process regular ticket messages
        $messages = $this->di['db']->find('SupportTicketMessage');
        foreach ($messages as $msg) {
            $stats['processed']++;
            $original = $msg->content;
            $proxified = $this->proxifyImages($original);

            if ($proxified !== $original) {
                $stats['images_found']++;
                $msg->content = $proxified;
                $msg->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($msg);
                $stats['updated']++;
            }
        }

        // Process public ticket messages
        $publicMessages = $this->di['db']->find('SupportPTicketMessage');
        foreach ($publicMessages as $msg) {
            $stats['processed']++;
            $original = $msg->content;
            $proxified = $this->proxifyImages($original);

            if ($proxified !== $original) {
                $stats['images_found']++;
                $msg->content = $proxified;
                $msg->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($msg);
                $stats['updated']++;
            }
        }

        $this->di['logger']->info('Migrated existing tickets: %d messages processed, %d updated', $stats['processed'], $stats['updated']);

        return $stats;
    }

    /**
     * Reverts all proxified image URLs back to their original URLs.
     * This is called during module uninstall to prevent broken images.
     *
     * @return array{processed: int, reverted: int} Statistics about the reversion
     */
    public function revertAllProxifiedUrls(): array
    {
        $stats = [
            'processed' => 0,
            'reverted' => 0,
        ];

        // Process regular ticket messages
        $messages = $this->di['db']->find('SupportTicketMessage');
        foreach ($messages as $msg) {
            $stats['processed']++;
            $original = $msg->content;
            $reverted = $this->revertProxifiedContent($original);

            if ($reverted !== $original) {
                $msg->content = $reverted;
                $msg->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($msg);
                $stats['reverted']++;
            }
        }

        // Process public ticket messages
        $publicMessages = $this->di['db']->find('SupportPTicketMessage');
        foreach ($publicMessages as $msg) {
            $stats['processed']++;
            $original = $msg->content;
            $reverted = $this->revertProxifiedContent($original);

            if ($reverted !== $original) {
                $msg->content = $reverted;
                $msg->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($msg);
                $stats['reverted']++;
            }
        }

        $this->di['logger']->info('Reverted proxified URLs: %d messages processed, %d reverted', $stats['processed'], $stats['reverted']);

        return $stats;
    }

    /**
     * Reverts proxified URLs in content back to original URLs.
     * Decodes base64url-encoded URLs from proxy parameters.
     *
     * @param string $content Content with potentially proxified URLs
     *
     * @return string Content with original URLs restored
     */
    protected function revertProxifiedContent(string $content): string
    {
        // Pattern to match our proxy URLs with base64url-encoded original URL
        // Matches: /imageproxy/image?u=<base64url> or http://domain/imageproxy/image?u=<base64url>
        $content = preg_replace_callback(
            '~(https?://[^/]+)?/imageproxy/image\?u=([A-Za-z0-9_-]+)~',
            function ($m) {
                $encoded = $m[2];
                // Decode base64url
                $originalUrl = base64_decode(strtr($encoded, '-_', '+/'));

                return $originalUrl ?: $m[0]; // Return original if decode fails
            },
            $content
        );

        return $content;
    }

    /**
     * Called when module is uninstalled.
     * Reverts all proxified URLs back to originals and cleans up configuration.
     *
     * @return void
     */
    public function uninstall(): void
    {
        // Revert all proxified URLs back to originals to prevent broken images
        $this->revertAllProxifiedUrls();

        // Clean up module configuration
        $model = $this->di['db']->findOne(
            'ExtensionMeta',
            'extension = :ext AND meta_key = :key',
            [':ext' => 'mod_imageproxy', ':key' => 'config']
        );
        if ($model instanceof \Model_ExtensionMeta) {
            $this->di['db']->trash($model);
        }

        $this->di['logger']->info('Imageproxy module uninstalled and URLs reverted');
    }
}
