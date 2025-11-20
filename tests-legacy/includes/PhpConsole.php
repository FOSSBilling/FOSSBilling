<?php

/**
 * @see http://code.google.com/p/php-console
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @version 1.1
 *
 * @desc Sending messages to Google Chrome console
 *
 * You need to install Google Chrome extension:
 * https://chrome.google.com/extensions/detail/nfhmhhlpfleoednkpnnnkolmclajemef
 *
 * All class properties and methods are static because it's required to let
 * them work on script shutdown when FATAL error occurs.
 */
class PhpConsole
{
    public static $ignoreRepeatedEvents = false;
    public static $callOldErrorHandler = true;
    public static $callOldExceptionsHandler = true;

    /**
     * @var PhpConsole
     */
    protected static $instance;

    protected $handledMessagesHashes = [];
    protected $sourceBasePath;

    protected function __construct($handleErrors, $handleExceptions, $sourceBasePath)
    {
        if ($handleErrors) {
            $this->initErrorsHandler();
        }
        if ($handleExceptions) {
            $this->initExceptionsHandler();
        }
        if ($sourceBasePath) {
            $this->sourceBasePath = realpath($sourceBasePath);
        }
        $this->initClient();
    }

    public static function start($handleErrors = true, $handleExceptions = true, $sourceBasePath = null): void
    {
        if (self::$instance) {
            exit('PhpConsole already started');
        }
        self::$instance = new PhpConsole($handleErrors, $handleExceptions, $sourceBasePath);
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            exit('PhpConsole not started');
        }

        return self::$instance;
    }

    protected function handle(PhpConsoleEvent $event)
    {
        if (self::$ignoreRepeatedEvents) {
            $eventHash = md5($event->message . $event->file . $event->line);
            if (in_array($eventHash, $this->handledMessagesHashes)) {
                return;
            } else {
                $this->handledMessagesHashes[] = $eventHash;
            }
        }
        $this->sendEventToClient($event);
    }

    public function __destruct()
    {
        self::flushMessagesBuffer();
    }

    /***************************************************************
    CLIENT
     **************************************************************/

    final public const clientProtocolCookie = 'phpcslc';
    final public const serverProtocolCookie = 'phpcsls';
    final public const serverProtocol = 4;
    final public const messagesCookiePrefix = 'phpcsl_';
    final public const cookiesLimit = 50;
    final public const cookieSizeLimit = 4000;
    final public const messageLengthLimit = 2500;

    protected static $isEnabledOnClient;
    protected static $isDisabled;
    protected static $messagesBuffer = [];
    protected static $bufferLength = 0;
    protected static $messagesSent = 0;
    protected static $cookiesSent = 0;
    protected static $index = 0;

    protected function initClient()
    {
        if (self::$isEnabledOnClient === null) {
            self::setEnabledOnServer();
            self::$isEnabledOnClient = self::isEnabledOnClient();
            if (self::$isEnabledOnClient) {
                ob_start();
            }
        }
    }

    protected static function isEnabledOnClient(): bool
    {
        return isset($_COOKIE[self::clientProtocolCookie]) && $_COOKIE[self::clientProtocolCookie] == self::serverProtocol;
    }

    protected static function setEnabledOnServer()
    {
        if (!isset($_COOKIE[self::serverProtocolCookie]) || $_COOKIE[self::serverProtocolCookie] != self::serverProtocol) {
            self::setCookie(self::serverProtocolCookie, self::serverProtocol);
        }
    }

    protected function sendEventToClient(PhpConsoleEvent $event)
    {
        if (!self::$isEnabledOnClient || self::$isDisabled) {
            return;
        }
        $message = [];
        $message['type'] = str_starts_with((string) $event->tags, 'error,') ? 'error' : 'debug';
        $message['subject'] = $event->type;
        $message['text'] = substr((string) $event->message, 0, self::messageLengthLimit);

        if ($event->file) {
            $message['source'] = ($this->sourceBasePath ? preg_replace('!^' . preg_quote((string) $this->sourceBasePath, '!') . '!', '', (string) $event->file) : $event->file) . ($event->line ? ':' . $event->line : '');
        }
        if ($event->trace) {
            $traceArray = $this->convertTraceToArray($event->trace, $event->file, $event->line);
            if ($traceArray) {
                $message['trace'] = $traceArray;
            }
        }

        self::pushMessageToBuffer($message);

        if (strpos((string) $event->tags, ',fatal')) {
            self::flushMessagesBuffer();
        }
    }

    /**
     * @return mixed[]
     */
    protected function convertTraceToArray($traceData, $eventFile = null, $eventLine = null): array
    {
        $trace = [];
        foreach ($traceData as $call) {
            if ((isset($call['class']) && $call['class'] == self::class) || (!$trace && isset($call['file']) && $call['file'] == $eventFile && $call['line'] == $eventLine)) {
                $trace = [];

                continue;
            }
            $args = [];
            if (isset($call['args'])) {
                foreach ($call['args'] as $arg) {
                    if (is_object($arg)) {
                        $args[] = $arg::class;
                    } elseif (is_array($arg)) {
                        $args[] = 'Array';
                    } else {
                        $arg = var_export($arg, 1);
                        $args[] = strlen((string) $arg) > 12 ? substr((string) $arg, 0, 8) . '...\'' : $arg;
                    }
                }
            }
            if (isset($call['file']) && $this->sourceBasePath) {
                $call['file'] = preg_replace('!^' . preg_quote((string) $this->sourceBasePath, '!') . '!', '', $call['file']);
            }
            $trace[] = (isset($call['file']) ? ($call['file'] . ':' . $call['line']) : '[internal call]') . ' - ' . (isset($call['class']) ? $call['class'] . $call['type'] : '') . $call['function'] . '(' . implode(', ', $args) . ')';
        }
        $trace = array_reverse($trace);
        foreach ($trace as $i => &$call) {
            $call = '#' . ($i + 1) . ' ' . $call;
        }

        return $trace;
    }

    protected static function pushMessageToBuffer($message)
    {
        $encodedMessageLength = strlen(rawurlencode(json_encode($message)));
        if (self::$bufferLength + $encodedMessageLength > self::cookieSizeLimit) {
            self::flushMessagesBuffer();
        }
        self::$messagesBuffer[] = $message;
        self::$bufferLength += $encodedMessageLength;
    }

    protected static function getNextIndex()
    {
        return substr(number_format(microtime(1), 3, '', ''), -6) + self::$index++;
    }

    public static function flushMessagesBuffer(): void
    {
        if (self::$messagesBuffer) {
            self::sendMessages(self::$messagesBuffer);
            self::$bufferLength = 0;
            self::$messagesSent += count(self::$messagesBuffer);
            self::$messagesBuffer = [];
            ++self::$cookiesSent;
            if (self::$cookiesSent == self::cookiesLimit) {
                self::$isDisabled = true;
                $message = ['type' => 'error', 'subject' => 'PHP CONSOLE', 'text' => 'MESSAGES LIMIT EXCEEDED BECAUSE OF COOKIES STORAGE LIMIT. TOTAL MESSAGES SENT: ' . self::$messagesSent, 'source' => __FILE__, 'notify' => 3];
                self::sendMessages([$message]);
            }
        }
    }

    protected static function setCookie($name, $value)
    {
        if (headers_sent($file, $line)) {
            exit('PhpConsole ERROR: setcookie() failed because haders are sent (' . $file . ':' . $line . '). Try to use ob_start()');
        }
        setcookie($name, (string) $value, ['expires' => null, 'path' => '/']);
    }

    protected static function sendMessages($messages)
    {
        self::setCookie(self::messagesCookiePrefix . self::getNextIndex(), json_encode($messages));
    }

    /***************************************************************
    ERRORS
     **************************************************************/

    protected $codesTags = [E_ERROR => 'fatal', E_WARNING => 'warning', E_PARSE => 'fatal', E_NOTICE => 'notice', E_CORE_ERROR => 'fatal', E_CORE_WARNING => 'warning', E_COMPILE_ERROR => 'fatal', E_COMPILE_WARNING => 'warning', E_USER_ERROR => 'fatal', E_USER_WARNING => 'warning', E_USER_NOTICE => 'notice', E_STRICT => 'warning'];
    protected $codesNames = [E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE', E_STRICT => 'E_STRICT'];
    protected $notCompitableCodes = ['E_RECOVERABLE_ERROR' => 'warning', 'E_DEPRECATED' => 'warning'];
    protected $oldErrorHandler;

    protected function initErrorsHandler()
    {
        ini_set('display_errors', false);
        ini_set('html_errors', false);
        ini_set('ignore_repeated_errors', self::$ignoreRepeatedEvents);
        ini_set('ignore_repeated_source', self::$ignoreRepeatedEvents);

        foreach ($this->notCompitableCodes as $code => $tag) {
            if (defined($code)) {
                $this->codesTags[constant($code)] = $tag;
                $this->codesNames[constant($code)] = $code;
            }
        }

        $this->oldErrorHandler = set_error_handler($this->handleError(...));
        register_shutdown_function([$this, 'checkFatalError']);
    }

    public function checkFatalError(): void
    {
        $error = error_get_last();
        if ($error) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    public function handleError($code = null, $message = null, $file = null, $line = null): void
    {
        if (error_reporting() == 0) { // if error has been supressed with an @
            return;
        }
        if (!$code) {
            $code = E_USER_ERROR;
        }

        $event = new PhpConsoleEvent();
        $event->tags = 'error,' . ($this->codesTags[$code] ?? 'warning');
        $event->message = $message;
        $event->type = $this->codesNames[$code] ?? $code;
        $event->file = $file;
        $event->line = $line;
        $event->trace = debug_backtrace();

        $this->handle($event);

        if (self::$callOldErrorHandler && $this->oldErrorHandler) {
            call_user_func_array($this->oldErrorHandler, [$code, $message, $file, $line]);
        }
    }

    /***************************************************************
    EXCEPTIONS
     **************************************************************/

    protected $oldExceptionsHandler;

    protected function initExceptionsHandler()
    {
        $this->oldExceptionsHandler = set_exception_handler($this->handleException(...));
    }

    public function handleException(Throwable $exception): void
    {
        $event = new PhpConsoleEvent();
        $event->message = $exception->getMessage();
        $event->tags = 'error,fatal,exception,' . $exception::class;
        $event->type = $exception::class;
        $event->file = $exception->getFile();
        $event->line = $exception->getLine();
        $event->trace = $exception->getTrace();

        $this->handle($event);

        // TODO: check if need to throw
        if (self::$callOldExceptionsHandler && $this->oldExceptionsHandler) {
            call_user_func($this->oldExceptionsHandler, $exception);
        }
    }

    /***************************************************************
    DEBUG
     **************************************************************/

    public static function debug($message, $tags = 'debug'): void
    {
        if (self::$instance) {
            $event = new PhpConsoleEvent();
            $event->message = $message;
            $event->tags = $tags;
            $event->type = $tags;
            self::$instance->handle($event);
        }
    }
}

class PhpConsoleEvent
{
    public $message;
    public $type;
    public $tags;
    public $trace;
    public $file;
    public $line;
}

function debug($message, $tags = 'debug'): void
{
    PhpConsole::debug($message, $tags);
}
