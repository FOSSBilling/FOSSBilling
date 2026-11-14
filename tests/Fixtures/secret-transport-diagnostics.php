<?php

declare(strict_types=1);

define('DEBUG', true);
define('PATH_ROOT', dirname(__DIR__, 2) . '/src');
define('PATH_MODS', PATH_ROOT . '/modules');
define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
require PATH_ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Container.php';
putenv('APP_ENV=prod');

$config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
$config->setProxyDir(sys_get_temp_dir());
$config->setProxyNamespace('SecretTransportRegressionProxies');
$em = new Doctrine\ORM\EntityManager(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
(new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
    $em->getClassMetadata(Box\Mod\Email\Entity\EmailTemplate::class),
    $em->getClassMetadata(Box\Mod\Email\Entity\QueuedEmail::class),
]);
$code = 'mod_client_password_reset_request';
$template = (new Box\Mod\Email\Entity\EmailTemplate($code))->setIsCustom(true)->setEnabled(true)->setSubject('Reset {{ hash }}')->setContent('Reset {{ hash }}');
$em->persist($template);
$em->flush();
$di = Tests\Helpers\container();
$di['em'] = $em;
$extension = new class($argv[1]) {
    public function __construct(public string $mode)
    {
    }

    public function isExtensionActive(...$args): bool
    {
        if ($this->mode === 'setup') {
            throw new FOSSBilling\Core\Exception\InformationException('Extension setup failed');
        }

        return false;
    }
};
$system = new class($argv[1]) {
    public function __construct(private string $mode)
    {
    }

    public function renderEmailTplString(string $source, array $vars, ...$args): string
    {
        return str_replace('{{ hash }}', $vars['hash'], $source);
    }

    public function renderEmailSubjectString(string $source, array $vars, ...$args): string
    {
        return $this->renderEmailTplString($source, $vars);
    }

    public function getParamValue(...$args): string
    {
        return $this->mode === 'sender' ? '' : 'sender@example.com';
    }
};
$module = new class($argv[1]) {
    public function __construct(public string $mode)
    {
    }

    public function getConfig(): array
    {
        return $this->mode === 'dsn' ? ['mailer' => 'custom', 'custom_dsn' => 'unavailable://localhost'] : ['mailer' => 'invalid'];
    }
};
$di['mod'] = $di->protect(fn () => $module);
$di['mod_service'] = $di->protect(fn (string $name): object => strtolower($name) === 'extension' ? $extension : $system);
$email = new Box\Mod\Email\Service();
$email->setDi($di);
$token = str_repeat('abcdef12', 8);
$result = null;
$error = null;
$exceptionLog = null;

try {
    $attachment = ['name' => 'secret.txt', 'content' => 'private-attachment-marker'];
    $sendTemplate = static fn (string $hash) => $email->sendTemplate(['code' => $code, 'to' => 'client@example.com', 'hash' => $hash, 'throw_exceptions' => $argv[3] === '1', 'attachment' => $attachment]);
    $result = $argv[2] === 'template'
        ? $sendTemplate($token)
        : $email->sendMail('client@example.com', 'sender@example.com', 'Reset ' . $token, 'Reset ' . $token, throw_exceptions: $argv[3] === '1', attachment: $attachment, skipHistory: true);
} catch (FOSSBilling\Core\Exception\BaseException $e) {
    $error = $e->getMessage();
    $formatter = new Monolog\Formatter\LineFormatter(null, null, true, true, true);
    $exceptionLog = $formatter->format(new Monolog\LogRecord(new DateTimeImmutable(), 'email', Monolog\Level::Error, 'Secret delivery failed', ['exception' => $e]));
}
$secretQueueCount = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue');
$secretLogs = json_encode($di['logger']->calls, JSON_THROW_ON_ERROR);
$ignoreArgsAfterSecret = ini_get('zend.exception_ignore_args');
// The protection must unwind before ordinary diagnostics and queued retries.
$extension->mode = 'unknown';
$module->mode = 'unknown';
$ordinary = $email->sendMail('client@example.com', 'sender@example.com', 'Ordinary', 'ordinary-body-control', send_now: true);
echo json_encode([
    'result' => $result, 'error' => $error, 'exception_log' => $exceptionLog, 'secret_queue_count' => $secretQueueCount,
    'secret_logs' => $secretLogs, 'ignore_args_after_secret' => $ignoreArgsAfterSecret, 'ordinary_accepted' => $ordinary,
    'ordinary_queue_count' => $em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'),
    'ordinary_logs' => json_encode($di['logger']->calls, JSON_THROW_ON_ERROR),
], JSON_THROW_ON_ERROR);
