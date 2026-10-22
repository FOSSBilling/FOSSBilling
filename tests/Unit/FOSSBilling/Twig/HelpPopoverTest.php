<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Tests\Support\StrictTemplateRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

test('both themes can render shared supplementary help as an escaped native button', function (string $theme): void {
    $twig = new Environment(new FilesystemLoader([PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html']), ['strict_variables' => true, 'autoescape' => 'html']);
    $html = $twig->render('partial_help_popover.html.twig', ['help_label' => 'Details <test>', 'help_text' => '"><img src=x>']);
    expect($html)->toContain('type="button"', 'data-bs-toggle="popover"', 'data-bs-placement="auto"', 'aria-label="Details &lt;test&gt;"', 'aria-expanded="false"', 'data-bs-content="&quot;&gt;&lt;img src=x&gt;"', 'aria-hidden="true"')
        ->not->toContain('data-bs-html', '<img');
    expect(trim($twig->render('partial_help_popover.html.twig', ['help_label' => 'Details', 'help_text' => ''])))->toBe('');
})->with(['client', 'admin']);

test('permission help is outside field labels and leaves form names and values intact', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/Staff/templates/admin/mod_staff_group.html.twig', [
        'group' => new PermissiveStub(['id' => 7, 'name' => 'Review Group', 'protected' => false]),
        'is_super_admin' => true, 'members' => [], 'parent_pairs' => [],
        'mods' => ['example' => ['permissions' => [
            'edit' => ['type' => 'bool', 'display_name' => 'Edit', 'description' => 'Allow changes'],
            'level' => ['type' => 'select', 'display_name' => 'Level', 'description' => 'Choose level', 'options' => ['one' => 'One']],
        ]]],
    ]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//button[@data-fb-help-popover]'))->toHaveCount(3)
        ->and($xpath->query('//label//button'))->toHaveCount(0)
        ->and($xpath->query('//input[@name="permissions[example][edit]"]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="permissions[example][level]"]'))->toHaveCount(1);
});
