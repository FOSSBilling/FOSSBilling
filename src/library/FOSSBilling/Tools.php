<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;

class Tools
{
    private readonly Filesystem $filesystem;
    protected ?\Pimple\Container $di = null;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Return site url.
     */
    public function url($link = null): string
    {
        $link = trim((string) $link, '/');

        return SYSTEM_URL . $link;
    }

    /**
     * Generates a password of a set length and complexity.
     *
     * @param int      $length         the length of the password to generate
     * @param bool|int $includeSpecial If special characters should be included. If 4 is passed, that's considered to be true (added for backwards compatibility).
     *
     * @throws InformationException if it failed to generate a password meeting the requirements within 100 iterations
     */
    public function generatePassword(int $length = 8, bool|int $includeSpecial = false): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialCharacters = '!@#$%&?()+-_';

        // Backwards compatibility with previous behavior
        if (is_int($includeSpecial)) {
            $includeSpecial = $includeSpecial === 4;
        }

        $charSet = $characters . strtoupper($characters) . $numbers;
        if ($includeSpecial) {
            $charSet .= $specialCharacters;
        }

        $charSetLength = strlen($charSet);

        // Ensure minimum length for all required character types
        $minRequiredLength = $includeSpecial ? 4 : 3;
        if ($length < $minRequiredLength) {
            throw new InformationException('Password length must be at least ' . $minRequiredLength . ' characters to meet complexity requirements');
        }

        // Deterministically build password with one from each required category, then fill the rest
        $password = '';
        $password .= $characters[random_int(0, strlen($characters) - 1)];
        $password .= strtoupper($characters)[random_int(0, strlen($characters) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        if ($includeSpecial) {
            $password .= $specialCharacters[random_int(0, strlen($specialCharacters) - 1)];
        }

        // Fill remaining length with random characters from the full set
        for ($i = strlen($password); $i < $length; ++$i) {
            $password .= $charSet[random_int(0, $charSetLength - 1)];
        }

        // Shuffle to avoid predictable positions for required characters
        return str_shuffle($password);
    }

    public function slug($str): string
    {
        $str = strtolower(trim((string) $str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', '-', (string) $str);

        return trim((string) $str, '-');
    }

    public function to_camel_case($str, $capitalize_first_char = false): ?string
    {
        if ($capitalize_first_char) {
            $str[0] = strtoupper((string) $str[0]);
        }
        $func = fn ($c): string => strtoupper((string) $c[1]);

        return preg_replace_callback('/-([a-z])/', $func, (string) $str);
    }

    public function from_camel_case($str): ?string
    {
        $str[0] = strtolower((string) $str[0]);
        $func = fn ($c): string => '-' . strtolower((string) $c[1]);

        return preg_replace_callback('/([A-Z])/', $func, (string) $str);
    }

    /**
     * @return mixed[]
     */
    public function sortByOneKey(array $array, $key, $asc = true): array
    {
        $result = [];

        $values = [];
        foreach ($array as $id => $value) {
            $values[$id] = $value[$key] ?? '';
        }

        if ($asc) {
            asort($values);
        } else {
            arsort($values);
        }

        foreach ($values as $key => $value) {
            $result[$key] = $array[$key];
        }

        return $result;
    }

    public function getTable($type)
    {
        $class = 'Model_' . ucfirst((string) $type) . 'Table';
        $file = Path::join(PATH_LIBRARY, 'Model', "{$type}Table.php");
        if (!$this->filesystem->exists($file)) {
            throw new Exception('Service class :class was not found in :path', [':class' => $class, ':path' => $file]);
        }
        require_once $file;

        return new $class();
    }

    /**
     * @return mixed[]
     */
    public function getPairsForTableByIds($table, $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $count = self::safeCount($ids);
        $slots = $count ? implode(',', array_fill(0, $count, '?')) : ''; // same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM ' . $table . ' WHERE id in (' . $slots . ')', $ids);

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    /**
     * Checks if a given email address is valid.
     * In a production environment, this will both check that the email address matches RFC standards as well as validating the domain.
     * In a testing / development environment it will only check the RFC standards.
     */
    public function validateAndSanitizeEmail(string $email, bool $throw = true, bool $checkDNS = true)
    {
        $email = htmlspecialchars($email);

        $validator = new EmailValidator();
        if (Environment::isProduction() && $checkDNS) {
            $validations = new MultipleValidationWithAnd([
                new RFCValidation(),
                new DNSCheckValidation(),
            ]);
        } else {
            $validations = new RFCValidation();
        }

        if (!$validator->isValid($email, $validations)) {
            if ($throw) {
                $friendlyName = ucfirst(__trans('Email address'));

                throw new InformationException(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
            }

            return false;
        }

        return $email;
    }

    /**
     * Safely count a value that may or may not be countable.
     */
    public static function safeCount(mixed $value): int
    {
        return is_countable($value) ? count($value) : 0;
    }

    /**
     * Normalizes mixed input into a boolean value.
     */
    public static function normalizeBoolean(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $default;
        }

        return $default;
    }

    public static function isHTTPS(): bool
    {
        $protocol = $_SERVER['HTTPS'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? '';

        // $_SERVER['HTTPS'] will be set to `on` to indicate HTTPS and the others to will be set to `https`, so either one means we are connected via HTTPS.
        return strcasecmp((string) $protocol, 'on') === 0 || strcasecmp((string) $protocol, 'https') === 0;
    }

    /**
     * Tries to fetch a list of possible interfaces (IPs) to bind to when making requests.
     * Attempts to make external requests for each interface & only works with IPv4.
     */
    public static function listHttpInterfaces(): array
    {
        // Fetch a list of IP addresses for local interfaces
        $validatedIps = [];

        try {
            $ips = gethostbynamel(gethostname());
        } catch (\Exception) {
            $ips = [];
        }

        if (!$ips) {
            return [];
        }

        // For each of the found IPs, attempt a generic network request. If the request produces no errors, consider it valid
        foreach ($ips as $ip) {
            try {
                self::getExternalIP(true, $ip);
                $validatedIps[] = $ip;
            } catch (\Exception) {
            }
        }

        return $validatedIps;
    }

    /**
     * Validates an interface value for HTTP client binding.
     * Accepts IP addresses and hostname/interface names like "eth0".
     */
    public static function isValidHttpInterface(string $interface): bool
    {
        return (bool) filter_var($interface, FILTER_VALIDATE_IP)
            || preg_match('/^[a-zA-Z0-9._-]*[a-zA-Z._-][a-zA-Z0-9._-]*$/', $interface) === 1;
    }

    /**
     * Returns the currently configured default network interface.
     * If a custom interface IP address, hostname, or interface name is entered, it is validated before being used.
     * If we are using an interface IP address that was selected from a given list, we will validate that the IP address is still in the list of known IP address interfaces.
     *
     * @return string|int either the IP address of the interface to use (string) or 0 if there's none set / the set one is invalid
     */
    public static function getDefaultInterface(): string|int
    {
        $customInterface = Config::getProperty('custom_interface_ip', '');
        if (!empty($customInterface) && self::isValidHttpInterface($customInterface)) {
            return $customInterface;
        }

        $interface = Config::getProperty('interface_ip', '0');

        try {
            $knownInterfaces = gethostbynamel(gethostname());
        } catch (\Exception) {
            $knownInterfaces = [];
        }

        if ($interface && $interface !== '0' && in_array($interface, $knownInterfaces)) {
            return $interface;
        }

        return 0;
    }

    /**
     * Returns the public IP address of the current FOSSBilling instance.
     * Will try multiple services in order if they time out.
     * Try order: ipify.org, ifconfig.io, ip.hestiacp.com.
     *
     * @param bool    $throw if the function should throw an exception on an error
     * @param ?string $bind  overrides the default network interface bind. When `null` (default), the configured default (BIND_TO) is used.
     *
     * @return ?string `null` if there was an error, otherwise an IP address will be returned
     */
    public static function getExternalIP(bool $throw = true, ?string $bind = null): ?string
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        $bind ??= BIND_TO;
        $client = HttpClient::create(['bindto' => $bind]);

        foreach ($services as $service) {
            try {
                $response = $client->request('GET', $service, [
                    'timeout' => 2,
                ]);

                $ip = filter_var($response->getContent(), FILTER_VALIDATE_IP);
                if ($ip) {
                    return $ip;
                }
            } catch (\Exception $e) {
                error_log(sprintf(
                    'Error fetching external IP from "%s" (%s): %s',
                    $service,
                    $e::class,
                    $e->getMessage()
                ));
            }
        }

        if ($throw) {
            throw new \Exception('Unable to determine external IP address from any service.');
        }

        return null;
    }

    /**
     * Converts bytes to a human-readable format (B, KB, MB, GB and TB).
     */
    public static function humanReadableBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        return round($bytes / 1099511627776, 2) . ' TB';
    }

    /**
     * Sanitize user content to prevent XSS attacks.
     * Uses Symfony's HTML Sanitizer component for robust protection.
     *
     * @param string $content   The content to sanitize. If empty, returns an empty string.
     * @param bool   $allowHtml Whether to allow safe HTML tags (default: true for rich content)
     *
     * @return string Sanitized content safe for output
     */
    public static function sanitizeContent(string $content = '', bool $allowHtml = true): string
    {
        if (empty($content)) {
            return '';
        }

        // Remove null bytes
        $content = str_replace("\0", '', $content);

        if (!$allowHtml) {
            // Strip all HTML tags for plain text
            return trim(htmlspecialchars(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8', false));
        }

        // Use Symfony's HTML Sanitizer
        $config = (new \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('a', ['href', 'title'])
            ->allowElement('code')
            ->allowElement('pre')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel']);

        $sanitizer = new \Symfony\Component\HtmlSanitizer\HtmlSanitizer($config);

        return trim($sanitizer->sanitize($content));
    }

    public static function validatePhoneCC(string|int $countryCode): int
    {
        if (!is_numeric($countryCode) || $countryCode <= 0 || $countryCode > 999) {
            throw new InformationException('The provided phone country code does not appear to be valid.');
        }

        return intval($countryCode);
    }

    public static function validatePhoneNumber(string $number): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $number);
        if (strlen((string) $digitsOnly) < 1 || strlen((string) $digitsOnly) > 12) {
            throw new InformationException('The provided phone number does not appear to be valid.');
        }

        if (str_starts_with($number, '+')) {
            throw new InformationException('Please use the separate field for the phone country code.');
        }

        return $number;
    }

    public static function createSessionRestoreToken(string $sessionId): string
    {
        $expiry = time() + 3600;
        $payload = $sessionId . '|' . $expiry;
        $signature = hash_hmac('sha256', $payload, (string) Config::getProperty('info.salt'));

        return base64_encode($payload . '|' . $signature);
    }

    public static function validateSessionRestoreToken(string $token): ?string
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$sessionId, $expiry, $signature] = $parts;

        if (time() > (int) $expiry) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $sessionId . '|' . $expiry, (string) Config::getProperty('info.salt'));
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        return $sessionId;
    }
}
