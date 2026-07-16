<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;

function legacyInvoiceCreatedTemplate(): string
{
    $content = <<<'TWIG'
        <!DOCTYPE html>
        <html>
        <head>
        	<meta charset="utf-8">
        	<style type="text/css">
        		body {
        			font-family: Arial, sans-serif;
        			font-size: 14px;
        			color: #333333;
        		}

        		h1 {
        			font-size: 24px;
        			font-weight: bold;
        			margin: 0 0 20px;
        		}

        		p {
        			margin: 0 0 10px;
        		}

        		strong {
        			font-weight: bold;
        		}

        		.signature {
        			font-style: italic;
        			color: #999999;
        			margin-top: 20px;
        			border-top: 1px solid #cccccc;
        			padding-top: 10px;
        		}
        	</style>
        </head>
        <body>
        	<h1>Invoice created</h1>
        	<p>Hello {{ c.first_name }} {{ c.last_name }},</p>
        	<p>This is to notify that invoice {{ invoice.id }} was generated on {{ invoice.created_at|format_date }}.</p>
            <ul>
                <li><strong>Amount Due:</strong> {{ invoice.total | money(invoice.currency) }}</li>
                <li><strong>Due Date:</strong>  {{ invoice.due_at|format_date }}</li>
            </ul>

            <p>You may view and pay the invoice <a href="{{'invoice'|link}}/{{invoice.hash}}" target="_blank">here.</a>
            <p>You may also <a href="{{'login'|link({'email' : c.email }) }}" target="_blank">login</a> or <a href="{{'client/profile'|link}}" target="_blank">edit your profile.</a>

        	<p class="signature">{{ guest.system_company.signature }}</p>
        </body>
        </html>
        TWIG;

    return "\n{$content}\n";
}

test('patch level includes the legacy email template repair', function (): void {
    expect((new UpdatePatcher())->latestPatchLevel())->toBe(90);
});

test('legacy email patch restores untouched 0.7.2 defaults without replacing customizations', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE email_template (id INTEGER PRIMARY KEY, action_code TEXT, subject TEXT, content TEXT, is_custom INTEGER, is_overridden INTEGER)');

    $subject = '[{{ guest.system_company.name }}] Invoice Created';
    $legacyContent = legacyInvoiceCreatedTemplate();
    expect(hash('sha256', $legacyContent))->toBe('3b9677641c2eb3e8b34abae05596593a66d7014cd5ad40220c1a1ea8614e5b43');

    $insert = $pdo->prepare('INSERT INTO email_template (id, action_code, subject, content, is_custom, is_overridden) VALUES (:id, :code, :subject, :content, 0, 1)');
    $insert->execute(['id' => 1, 'code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent]);
    $insert->execute(['id' => 2, 'code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent . '<p>Custom footer</p>']);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch90'))->invoke($patcher);

    $templates = $pdo->query('SELECT id, content, is_overridden FROM email_template ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    expect($templates[0]['is_overridden'])->toBe(0)
        ->and($templates[0]['content'])->not->toContain('|link')
        ->and($templates[0]['content'])->not->toContain('| money')
        ->and($templates[1]['is_overridden'])->toBe(1)
        ->and($templates[1]['content'])->toEndWith('<p>Custom footer</p>');
});
