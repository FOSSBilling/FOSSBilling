<?php

declare(strict_types=1);
require_once __DIR__ . '/TranslationFixture.php';

class TranslationTestCase extends PHPUnit\Framework\TestCase
{
    public TranslationFixture $fixture;
}

pest()->extend(TranslationTestCase::class)->in(__DIR__)
    ->beforeEach(function (): void { $this->fixture = new TranslationFixture(); })
    ->afterEach(function (): void { $this->fixture->remove(); });
