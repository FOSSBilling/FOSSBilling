<?php

declare(strict_types=1);

use function Tests\Helpers\browserBaseUrl;

it('creates a new client account and automatically logs in', function (): void {
    $suffix = uniqid('', true);
    $client = [
        'first_name' => 'Browser',
        'last_name' => 'Signup',
        'email' => "browser-signup-{$suffix}@example.com",
        'password' => 'BrowserClient1!',
    ];

    $page = visit(browserBaseUrl() . '/signup');
    $page->assertSee('Create a new account');

    $page->type('input[name="first_name"]', $client['first_name']);
    $page->type('input[name="last_name"]', $client['last_name']);
    $page->type('input[name="email"]', $client['email']);
    $page->type('input[name="password"]', $client['password']);
    $page->type('input[name="password_confirm"]', $client['password']);

    $page->script('
        const form = document.querySelector("form[action*=client/create]");
        const fields = form.querySelectorAll("input[required], textarea[required], select[required]");
        fields.forEach(f => {
            if (!f.value && f.type !== "checkbox" && f.type !== "radio" && f.type !== "hidden" && f.name !== "first_name" && f.name !== "last_name" && f.name !== "email" && f.name !== "password" && f.name !== "password_confirm") {
                f.value = "Test value";
            }
        });
        const checkboxes = form.querySelectorAll("input[type=checkbox][required]");
        checkboxes.forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event("change", {bubbles: true})); });
        const selects = form.querySelectorAll("select[required]");
        selects.forEach(sel => {
            if (!sel.value) {
                const opt = sel.querySelector("option[value]:not([value=])");
                if (opt) { sel.value = opt.value; sel.dispatchEvent(new Event("change", {bubbles: true})); }
            }
        });
    ');

    $page->click('form[action*="client/create"] button[type="submit"]');
    $page->assertPathIs('/');

    $page->assertSee($client['email']);
});
