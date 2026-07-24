<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('downloadable file migration follows the client balance gateway repair', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 89);

    expect($patches)->toHaveKey(90)
        ->and($patches[90][1])->toBe('patch90')
        ->and($patches)->toHaveKey(91)
        ->and($patches[91][1])->toBe('patch91')
        ->and($patches)->toHaveKey(92)
        ->and($patches[92][1])->toBe('patch92');
});

test('fresh installs start at the latest patch level', function (): void {
    $content = file_get_contents(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
    expect($content)->toBeString();

    preg_match("/\\(1,'last_patch','(\\d+)'/", $content, $matches);

    expect((int) ($matches[1] ?? 0))->toBe((new UpdatePatcher())->latestPatchLevel());
});

test('client balance gateway patch restores one-time payments', function (): void {
    $statement = Mockery::mock(PDOStatement::class);
    $statement->expects('execute')
        ->with(['gateway' => 'ClientBalance'])
        ->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('UPDATE pay_gateway SET allow_single = 1 WHERE gateway = :gateway AND allow_single = 0')
        ->andReturn($statement);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch91'))->invoke($patcher);
});

test('legacy email patch restores untouched 0.7.2 defaults without replacing customizations', function (): void {
    $filesystem = new Filesystem();
    $template = $filesystem->readFile(Path::join(PATH_TESTS, 'Fixtures', 'Email', 'mod_invoice_created_0.7.2.html.twig'));

    preg_match('#{%\s*block subject\s*%}(.*?){%\s*endblock\s*%}#s', $template, $subjectMatches);
    preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $template, $contentMatches);

    $subject = $subjectMatches[1] ?? '';
    $legacyContent = $contentMatches[1] ?? '';
    expect(hash('sha256', $legacyContent))->toBe('3b9677641c2eb3e8b34abae05596593a66d7014cd5ad40220c1a1ea8614e5b43');

    $selectStatement = Mockery::mock(PDOStatement::class);
    $selectStatement->expects('execute')->with([])->andReturnTrue();
    $selectStatement->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'action_code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent],
        ['id' => 2, 'action_code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent . '<p>Custom footer</p>'],
    ]);

    $updateStatement = Mockery::mock(PDOStatement::class);
    $updateStatement->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params['id'])->toBe(1)
            ->and($params['content'])->not->toMatch('/\|\s*link\b/')
            ->and($params['content'])->not->toMatch('/\|\s*money\b/');

        return true;
    }))->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT id, action_code, subject, content FROM email_template WHERE is_overridden = 1 AND is_custom = 0')
        ->andReturn($selectStatement);
    $pdo->expects('prepare')
        ->with('UPDATE email_template SET is_overridden = 0, subject = :subject, content = :content WHERE id = :id')
        ->andReturn($updateStatement);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch90'))->invoke($patcher);
});
