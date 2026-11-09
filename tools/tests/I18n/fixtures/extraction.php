<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

return [
    'qualified, unqualified and adapter exception constructors' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                throw new \FOSSBilling\Core\Exception\BaseException('Cart not found');
                throw new BaseException('Cache configuration is invalid.');
                $e = new FOSSBilling\Core\Exception\BaseException('Page :url not found', [':url' => $url], 404);
                $response = $app->show404($e);
                return new \Payment_Exception('Custom payments must be approved.', [], 7002);
                $country ??= throw new IncompleteRecord('Unknown country ISO code');
                throw new \RuntimeException('Unable to encrypt data.');
                throw new \LogicException('License is not active', 1006);
                throw new InvalidArgumentException('Invalid email address');
                throw new Server_Exception('Invalid server manager.');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Cart not found', null, 'Case.php:2', 'core'],
            ['Cache configuration is invalid.', null, 'Case.php:3', 'core'],
            ['Page :url not found', null, 'Case.php:4', 'core'],
            ['Custom payments must be approved.', null, 'Case.php:6', 'core'],
            ['Unknown country ISO code', null, 'Case.php:7', 'core'],
            ['Unable to encrypt data.', null, 'Case.php:8', 'core'],
            ['License is not active', null, 'Case.php:9', 'core'],
            ['Invalid email address', null, 'Case.php:10', 'core'],
            ['Invalid server manager.', null, 'Case.php:11', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'translation calls inside array values' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                return ['display_name' => \__trans('Delete email history')];
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Delete email history', null, 'Case.php:2', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'parent constructor messages' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                class LoginRequired extends \RuntimeException {
                    public function __construct() {
                        parent::__construct('Client is not logged in');
                    }
                }
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Client is not logged in', null, 'Case.php:4', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'literal ternary branches in constructor messages' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                throw new \RuntimeException($area === 'admin' ? 'Admin area' : 'Client area');
                throw new \RuntimeException(($failed ? 'Failure' : 'Success'));
                throw new \RuntimeException(($t = <<<HTML
                apples, oranges? no
                HTML) ? 'Present' : 'Absent');
                throw new \RuntimeException('admin' === $area ? 'Admin area' : 'Client area');
                throw new \RuntimeException(($a ?? $b) ? 'Available' : 'Unavailable');
                throw new \RuntimeException(match ($code) { 1 => true, default => false } ? 'First error' : 'Other error');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Admin area', null, 'Case.php:2', 'core'],
            ['Client area', null, 'Case.php:2', 'core'],
            ['Failure', null, 'Case.php:3', 'core'],
            ['Success', null, 'Case.php:3', 'core'],
            ['Present', null, 'Case.php:4', 'core'],
            ['Absent', null, 'Case.php:4', 'core'],
            ['Admin area', null, 'Case.php:7', 'core'],
            ['Client area', null, 'Case.php:7', 'core'],
            ['Available', null, 'Case.php:8', 'core'],
            ['Unavailable', null, 'Case.php:8', 'core'],
            ['First error', null, 'Case.php:9', 'core'],
            ['Other error', null, 'Case.php:9', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'dynamic strings and non-message arguments are skipped' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                throw new \RuntimeException($message ?? 'Unknown error');
                throw new \RuntimeException($ok ? getMessage() : 'Fallback');
                new Widget($code, $on ? 'Enabled' : 'Disabled');
                new Widget(fn ($x) => $x ? 'Enabled' : 'Disabled');
                throw new \RuntimeException('No items for ID: ' . $id);
                throw new \RuntimeException("Hello $name");
                throw new \RuntimeException($message);
                $s = 'throw new \RuntimeException(\'fake\')';
                // renew the thing
                $renew = 1;
                SOURCE_WRAP,
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => null,
    ],
    'non-translation constructors and anonymous classes are skipped' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                throw new AuthenticationRequiredException('client');
                $d = new \DateTime('now');
                $d = new \datetime('now');
                $r = new Response('<html>hi</html>');
                $d = new \DOMDocument('1.0', 'UTF-8');
                $r = new \ReflectionClass('Acme\\User');
                $o = new class('not a message') extends Foo {};
                SOURCE_WRAP,
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => null,
    ],
    'PHP boundaries exclude HTML and JavaScript' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php $a = 1; // comment ?>
                <p>new Widget('Preview')</p>
                <?php $a = 2; # comment ?>
                <button onclick="new Widget('Preview')">don't</button>
                <script>var l = <?= __trans('Save changes') ?>;
                var d = new Date('2026-01-01');</script>
                <?php throw new \RuntimeException('Real message');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Save changes', null, 'Case.php:5', 'core'],
            ['Real message', null, 'Case.php:7', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'HTML strings inside PHP do not end PHP mode' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                $x = '<script>';
                throw new \RuntimeException('Kept');
                $y = '</script>';
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Kept', null, 'Case.php:3', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'closing tags inside PHP strings' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <script><?php $x = 'a ?> b'; $y = __trans('Real'); ?></script>
                <?php throw new \RuntimeException('Kept');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Real', null, 'Case.php:1', 'core'],
            ['Kept', null, 'Case.php:2', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'closing tags inside heredocs' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <script><?php $t = <<<HTML
                <p>a ?> b</p>
                HTML;
                $y = __trans('Real'); ?></script>
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Real', null, 'Case.php:4', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'a PHP block after an HTML fragment' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <button onclick="new Widget('Preview')">
                <?php throw new \RuntimeException('Kept');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Kept', null, 'Case.php:2', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'comments and nested arguments' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                __trans('A', ['from' => 'other', 'nested' => bar(from: 'other')], /* from: 'wrong' */ from: 'example');
                __trans('B', values: [':x' => fn ($a, $b) => [$a, $b]], from: 'example');
                __trans('C', values: ['text' => "from: 'other', )"], from: 'example');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['A', null, 'Case.php:2', 'example'],
            ['B', null, 'Case.php:3', 'example'],
            ['C', null, 'Case.php:4', 'example'],
        ],
        'module' => null,
        'error' => null,
    ],
    'exception aliases and inherited signatures' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                namespace Example;
                use FOSSBilling\Core\Exception\{InformationException as Notice, BaseException as Base};
                new Notice('Alias', null, 0, false, 'example');
                new Inherited('Inherited', null, 0, false, 'example');
                class PaymentFailure extends \Payment_Exception {
                    public function __construct() { parent::__construct('Payment parent', null, 0, 'example'); }
                }
                class ServerFailure extends \Server_Exception {
                    public function __construct() { parent::__construct('Server parent', null, 0, 'example'); }
                }
                class BaseFailure extends Base {
                    public function __construct() { parent::__construct('Base parent', null, 0, false, 'example'); }
                }
                new PaymentFailure();
                class Renamed extends Notice {
                    public function __construct($text, $from = null) { parent::__construct($text, from: $from); }
                }
                new Renamed('Own signature', 'example');
                SOURCE_WRAP,
            'Inherited.php' => <<<'SOURCE_WRAP'
                <?php
                namespace Example;
                use FOSSBilling\Core\Exception\InformationException as Information;
                class Inherited extends Information {}
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Alias', null, 'Case.php:4', 'example'],
            ['Inherited', null, 'Case.php:5', 'example'],
            ['Payment parent', null, 'Case.php:7', 'example'],
            ['Server parent', null, 'Case.php:10', 'example'],
            ['Base parent', null, 'Case.php:13', 'example'],
            ['Own signature', null, 'Case.php:19', 'example'],
        ],
        'module' => null,
        'error' => null,
    ],
    'named and positional exception sources' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                new InformationException('Named', from: 'example');
                new BaseException('Positional', [], 42, true, 'example');
                new Payment_Exception('Payment', null, 42, 'example');
                new Registrar_Exception('Registrar', 42, from: 'example');
                new server_exception('Server', [], 42, 'example');
                new CustomException(from: 'other', message: 'Custom');
                new Unrelated('Unrelated', [], 0, true, 'not-a-source-parameter');
                class Child extends BaseException {
                    public function __construct() { parent::__construct('Parent', null, 42, true, 'example'); }
                }
                new InformationException($failed ? 'Failed' : 'Success', from: 'example');
                new InformationException('admin' === $area ? 'Admin' : 'Client', from: 'example');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Named', null, 'Case.php:2', 'example'],
            ['Positional', null, 'Case.php:3', 'example'],
            ['Payment', null, 'Case.php:4', 'example'],
            ['Registrar', null, 'Case.php:5', 'example'],
            ['Server', null, 'Case.php:6', 'example'],
            ['Custom', null, 'Case.php:7', 'other'],
            ['Unrelated', null, 'Case.php:8', 'core'],
            ['Parent', null, 'Case.php:10', 'example'],
            ['Failed', null, 'Case.php:12', 'example'],
            ['Success', null, 'Case.php:12', 'example'],
            ['Admin', null, 'Case.php:13', 'example'],
            ['Client', null, 'Case.php:13', 'example'],
        ],
        'module' => null,
        'error' => null,
    ],
    'only executable Twig filters' => [
        'files' => [
            'modules/Example/page.twig' => <<<'SOURCE_WRAP'

                <p>'Plain text'|trans(from: source)</p>
                {% verbatim %}{{ 'Example'|trans(from: source) }}{% endverbatim %}
                {{ "Status: #{'Ready'|trans}" }}
                {{ "Nested: #{'Hello'|trans(from: 'other')}" }}
                {{ "Escaped \#{'Inert'|trans(from: source)}" }}
                {{ "Text containing 'Fake'|trans(from: source)" }}
                {{ 'Visible'|trans }}
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Ready', null, 'modules/Example/page.twig:4', 'example'],
            ['Hello', null, 'modules/Example/page.twig:5', 'other'],
            ['Visible', null, 'modules/Example/page.twig:8', 'example'],
        ],
        'module' => null,
        'error' => null,
    ],
    'named, positional and reordered PHP arguments' => [
        'files' => [
            'modules/Example/Service.php' => <<<'SOURCE_WRAP'
                <?php
                __trans('Shared');
                __trans('Named', from: 'example');
                __trans('Positional', ['from' => 'ignored', 'nested' => foo(1, 2)], 'other');
                __trans(from: 'example', values: [':n' => foo('from: other', [1, 2])], msgid: 'Reordered');
                __trans('Null', from: null);
                __trans('Explicit core', from: 'core');
                __pluralTrans(':n item', ':n items', $n, [':n' => $n], 'example');
                __pluralTrans(number: $n, from: 'other', msgidPlural: 'Many', msgid: 'One');
                __trans($dynamic, from: $source);
                __trans($dynamic, ...$arguments);
                new Unrelated(...$arguments);
                new Unrelated('Constructor message', ...$arguments);
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Shared', null, 'modules/Example/Service.php:2', 'core'],
            ['Named', null, 'modules/Example/Service.php:3', 'example'],
            ['Positional', null, 'modules/Example/Service.php:4', 'other'],
            ['Reordered', null, 'modules/Example/Service.php:5', 'example'],
            ['Null', null, 'modules/Example/Service.php:6', 'core'],
            ['Explicit core', null, 'modules/Example/Service.php:7', 'core'],
            [':n item', ':n items', 'modules/Example/Service.php:8', 'example'],
            ['One', 'Many', 'modules/Example/Service.php:9', 'other'],
            ['Constructor message', null, 'modules/Example/Service.php:13', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'standalone module defaults' => [
        'files' => [
            'Service.php' => '<?php __trans(\'Shared\'); __trans(\'Own\', from: \'example\');',
            'templates/page.twig' => '{{ \'Implicit\'|trans }}',
        ],
        'occurrences' => [
            ['Shared', null, 'Service.php:1', 'core'],
            ['Own', null, 'Service.php:1', 'example'],
            ['Implicit', null, 'templates/page.twig:1', 'example'],
        ],
        'module' => 'example',
        'error' => null,
    ],
    'source directory exclusions' => [
        'files' => [
            'Service.php' => '<?php __trans(\'Visible\');',
            'locale/input.php' => '<?php __trans(\'Ignore\');',
            'modules/Example/locale/input.php' => '<?php __trans(\'Ignore\');',
            'node_modules/input.php' => '<?php __trans(\'Ignore\');',
            'tests/input.php' => '<?php __trans(\'Ignore\');',
            'vendor/input.php' => '<?php __trans(\'Ignore\');',
        ],
        'occurrences' => [
            ['Visible', null, 'Service.php:1', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'Twig escaping and interpolation' => [
        'files' => [
            'page.twig' => <<<'SOURCE_WRAP'
                {{ 'Hello\n:name'|trans }}
                {{ "Quote's"|trans }}
                {{ '\xC3\xA9'|trans(from: 'ex\x61mple') }}
                {{ "Literal \#{label}"|trans }}
                {{ 'Single #{literal}'|trans }}
                {{ "Dynamic #{label}"|trans }}
                {{ "Dynamic \\#{label}"|trans }}
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Hello' . "\n" . ':name', null, 'page.twig:1', 'example'],
            ['Quote\'s', null, 'page.twig:2', 'example'],
            ['é', null, 'page.twig:3', 'example'],
            ['Literal #{label}', null, 'page.twig:4', 'example'],
            ['Single #{literal}', null, 'page.twig:5', 'example'],
        ],
        'module' => 'example',
        'error' => null,
    ],
    'Twig inference and overrides' => [
        'files' => [
            'modules/Example/templates/client/page.twig' => <<<'SOURCE_WRAP'

                {{ 'Inferred'|trans }}
                {{ 'Placeholders'|trans(values: {':n': nested(1, 2), 'from': 'other'}) }}
                {{ 'Override'|trans(from: 'other') }}
                {{ 'Equals syntax'|trans(from = 'other') }}
                {{ 'Positional'|trans({'from': 'not-a-source'}, 'other') }}
                {{ 'Core'|trans(from: 'core') }}
                {{ 'Null'|trans(from: null) }}
                {{ 'None'|trans(from: none) }}
                {{ 'Uppercase none'|trans(from: NONE) }}
                {{ 'Dollar $literal'|trans }}
                <input placeholder="{{ 'HTML quotes'|trans }}">
                {{ "Text containing 'Fake'|trans(from: 'other')" }}
                {# {{ 'Comment'|trans(from: 'other') }} #}
                SOURCE_WRAP,
            'themes/shared.twig' => '{{ \'Layout\'|trans }}',
        ],
        'occurrences' => [
            ['Inferred', null, 'modules/Example/templates/client/page.twig:2', 'example'],
            ['Placeholders', null, 'modules/Example/templates/client/page.twig:3', 'example'],
            ['Override', null, 'modules/Example/templates/client/page.twig:4', 'other'],
            ['Equals syntax', null, 'modules/Example/templates/client/page.twig:5', 'other'],
            ['Positional', null, 'modules/Example/templates/client/page.twig:6', 'other'],
            ['Core', null, 'modules/Example/templates/client/page.twig:7', 'core'],
            ['Null', null, 'modules/Example/templates/client/page.twig:8', 'core'],
            ['None', null, 'modules/Example/templates/client/page.twig:9', 'core'],
            ['Uppercase none', null, 'modules/Example/templates/client/page.twig:10', 'core'],
            ['Dollar $literal', null, 'modules/Example/templates/client/page.twig:11', 'example'],
            ['HTML quotes', null, 'modules/Example/templates/client/page.twig:12', 'example'],
            ['Layout', null, 'themes/shared.twig:1', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'unrelated constructors ignore from arguments' => [
        'files' => [
            'Case.php' => <<<'SOURCE_WRAP'
                <?php
                class Sender { public function __construct($message, $from) {} }
                new Sender('Variable address', from: $address);
                new Sender('Literal address', from: 'other');
                SOURCE_WRAP,
        ],
        'occurrences' => [
            ['Variable address', null, 'Case.php:3', 'core'],
            ['Literal address', null, 'Case.php:4', 'core'],
        ],
        'module' => null,
        'error' => null,
    ],
    'dynamic PHP source' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', from: $source);',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'concatenated PHP source' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', null, \'ex\' . $source);',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'interpolated PHP source' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', from: "ex$source");',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'source path traversal' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', from: \'../Other\');',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'uppercase source' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', from: \'Example\');',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'unpacked PHP arguments' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', ...$options);',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'argument unpacking',
    ],
    'duplicate PHP source' => [
        'files' => [
            'Case.php' => '<?php __trans(\'Message\', null, \'example\', from: \'other\');',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'duplicate argument',
    ],
    'dynamic Twig source' => [
        'files' => [
            'Case.twig' => '{{ \'Message\'|trans(from: source) }}',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'concatenated Twig source' => [
        'files' => [
            'Case.twig' => '{{ \'Message\'|trans(from: \'ex\' ~ source) }}',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'interpolated Twig source' => [
        'files' => [
            'Case.twig' => '{{ \'Message\'|trans(from: "#{source}") }}',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
    'empty Twig source' => [
        'files' => [
            'Case.twig' => '{{ \'Message\'|trans(from: \'\') }}',
        ],
        'occurrences' => [
        ],
        'module' => null,
        'error' => 'from must be a literal lowercase source ID or null',
    ],
];
