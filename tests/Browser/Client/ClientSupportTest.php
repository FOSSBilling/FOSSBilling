<?php

declare(strict_types=1);

use function Tests\Helpers\apiRequest;
use function Tests\Helpers\browserBaseUrl;
use function Tests\Helpers\createTestClient;

it('opens, replies to, and closes a support ticket', function (): void {
    $client = createTestClient();
    $suffix = uniqid('', true);
    $subject = "Browser support lifecycle {$suffix}";
    $initialMessage = "Initial support request from browser {$suffix}.";
    $replyMessage = "Follow-up support reply from browser {$suffix}.";

    $page = visit(browserBaseUrl() . '/login');
    $page->type('input[name="email"]', $client['email']);
    $page->type('input[name="password"]', $client['password']);
    $page->click('button[type="submit"]');
    $page->assertPathIs('/');

    $page->navigate('/support');
    $page->assertSee('Support Tickets');

    $page->click('[data-bs-target="#open-ticket-modal"]');
    $page->wait(1);

    $page->script("
        const textarea = document.querySelector('#open-ticket-modal textarea[name=\"content\"]');
        if (textarea && textarea.editor) {
            textarea.editor.setData('{$initialMessage}');
            textarea.value = '{$initialMessage}';
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
            textarea.dispatchEvent(new Event('change', {bubbles: true}));
        }
    ");

    $page->clear('#open-ticket-modal input[name="subject"]');
    $page->type('#open-ticket-modal input[name="subject"]', $subject);

    $page->click('#ticket-submit button[type="submit"]');

    $page->wait(3);

    $page->assertSee($subject);

    $page->script("
        const replyTextarea = document.querySelector('#ticket-reply-text');
        if (replyTextarea && replyTextarea.editor) {
            replyTextarea.editor.setData('{$replyMessage}');
            replyTextarea.value = '{$replyMessage}';
            replyTextarea.dispatchEvent(new Event('input', {bubbles: true}));
            replyTextarea.dispatchEvent(new Event('change', {bubbles: true}));
        }
    ");

    $page->click('#ticket-reply-form button[type="submit"]');
    $page->wait(2);

    $page->assertSee($replyMessage);

    $page->click('button');
    $page->wait(1);

    $cookieString = $page->script('document.cookie');
    preg_match('/csrf_token=([^;]+)/', $cookieString, $matches);
    $csrfToken = $matches[1] ?? '';

    $currentUrl = $page->url();
    preg_match('#/support/ticket/(\d+)#', $currentUrl, $urlMatches);
    $ticketId = $urlMatches[1] ?? null;

    if ($ticketId) {
        $result = apiRequest('GET', browserBaseUrl() . '/api/client/support/ticket_get', ['id' => $ticketId], $csrfToken);

        expect($result['status'])->toBe(200);
        expect($result['body']['error'])->toBeNull();
        expect($result['body']['result']['status'])->toBe('closed');
        expect($result['body']['result']['subject'])->toBe($subject);
        expect($result['body']['result']['messages'])->toHaveCount(2);
    }
});
