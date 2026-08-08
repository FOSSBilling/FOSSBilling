<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension;

use FOSSBilling\InformationException;
use Symfony\Component\Filesystem\Path;

/**
 * An extension's extension.json.
 *
 * This describes the bundle itself. Anything the extension needs at runtime
 * belongs in its composer.json, and the settings an administrator fills in
 * belong in the extension class, so that neither is duplicated here.
 */
final readonly class Manifest
{
    public const string FILENAME = 'extension.json';

    /**
     * The extension API version this release of FOSSBilling implements.
     *
     * Extensions declare the major version they were built against. A bump
     * means a change extensions cannot be expected to absorb silently.
     */
    public const int API_VERSION = 1;

    /**
     * Field keys a settings entry may declare. `name` and `type` are
     * required; everything else is optional. `options` is consulted for
     * `select`/`multiselect`/`radio` fields; `secret` renders a masked
     * input; `required_when` is a map of {other field name => required
     * value} evaluated against the gateway's current settings and enabled
     * state (see the admin settings form and its `compute_field_required`
     * counterpart).
     */
    private const array SETTINGS_FIELD_KEYS = [
        'name', 'type', 'label', 'description', 'placeholder', 'required', 'required_when', 'secret', 'options',
    ];

    public function __construct(
        public string $id,
        public ExtensionType $type,
        public string $name,
        public string $version,
        public int $api,
        public ?string $description = null,
        /** @var array{file: string, width?: string, height?: string}|null */
        public ?array $logo = null,
        /** @var list<array<string, mixed>> */
        public array $settings = [],
        /** Whether this extension's rendered output may be embedded in an iframe. */
        public bool $embeddable = false,
    ) {
    }

    /**
     * @throws InformationException if the file is missing, malformed, or describes a different extension
     */
    public static function fromDirectory(string $directory, string $expectedId, ExtensionType $expectedType): self
    {
        $file = Path::join($directory, self::FILENAME);

        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new InformationException('The extension ":id" has no :file.', [':id' => $expectedId, ':file' => self::FILENAME]);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InformationException('The :file of extension ":id" is not valid JSON: :error', [':file' => self::FILENAME, ':id' => $expectedId, ':error' => $e->getMessage()]);
        }

        if (!is_array($data)) {
            throw new InformationException('The :file of extension ":id" must describe an object.', [':file' => self::FILENAME, ':id' => $expectedId]);
        }

        foreach (['id', 'type', 'name', 'version', 'api'] as $key) {
            if (!isset($data[$key])) {
                throw new InformationException('The :file of extension ":id" is missing ":key".', [':file' => self::FILENAME, ':id' => $expectedId, ':key' => $key]);
            }
        }

        // The directory an extension lives in is what core trusts, so a manifest
        // disagreeing with it means the bundle was assembled or renamed wrongly.
        if ($data['id'] !== $expectedId) {
            throw new InformationException('The extension in ":dir" declares the ID ":declared".', [':dir' => $expectedId, ':declared' => (string) $data['id']]);
        }

        $type = ExtensionType::tryFrom((string) $data['type']);
        if ($type !== $expectedType) {
            throw new InformationException('The extension ":id" declares the type ":declared" but is installed as ":expected".', [':id' => $expectedId, ':declared' => (string) $data['type'], ':expected' => $expectedType->value]);
        }

        $api = (int) $data['api'];
        if ($api !== self::API_VERSION) {
            throw new InformationException('The extension ":id" targets extension API version :declared, but this version of FOSSBilling provides :provided.', [':id' => $expectedId, ':declared' => $api, ':provided' => self::API_VERSION]);
        }

        $logo = self::readLogo($data, $expectedId);
        $settings = self::readSettings($data, $expectedId);

        return new self(
            id: (string) $data['id'],
            type: $type,
            name: (string) $data['name'],
            version: (string) $data['version'],
            api: $api,
            description: isset($data['description']) ? (string) $data['description'] : null,
            logo: $logo,
            settings: $settings,
            embeddable: isset($data['embeddable']) && (bool) $data['embeddable'],
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{file: string, width?: string, height?: string}|null
     *
     * @throws InformationException
     */
    private static function readLogo(array $data, string $expectedId): ?array
    {
        if (!isset($data['logo'])) {
            return null;
        }

        if (!is_array($data['logo']) || !isset($data['logo']['file'])) {
            throw new InformationException('The :file of extension ":id" has a malformed "logo": it must be an object with a "file" key.', [':file' => self::FILENAME, ':id' => $expectedId]);
        }

        $logo = ['file' => (string) $data['logo']['file']];
        if (isset($data['logo']['width'])) {
            $logo['width'] = (string) $data['logo']['width'];
        }
        if (isset($data['logo']['height'])) {
            $logo['height'] = (string) $data['logo']['height'];
        }

        return $logo;
    }

    /**
     * Validate the settings schema when the manifest loads, with a useful
     * error, rather than deferring to `new $class($config)` inside a
     * try/catch (the old `ServicePayGateway::validateGatewayConfig()`).
     *
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     *
     * @throws InformationException
     */
    private static function readSettings(array $data, string $expectedId): array
    {
        if (!isset($data['settings'])) {
            return [];
        }

        if (!is_array($data['settings'])) {
            throw new InformationException('The :file of extension ":id" has a malformed "settings": it must be a list of field objects.', [':file' => self::FILENAME, ':id' => $expectedId]);
        }

        $seen = [];
        $fields = [];
        foreach ($data['settings'] as $index => $field) {
            if (!is_array($field)) {
                throw new InformationException('The :file of extension ":id" has a malformed settings field at position :index: it must be an object.', [':file' => self::FILENAME, ':id' => $expectedId, ':index' => $index]);
            }

            foreach (['name', 'type'] as $required) {
                if (!isset($field[$required]) || $field[$required] === '') {
                    throw new InformationException('The :file of extension ":id" has a settings field at position :index missing ":key".', [':file' => self::FILENAME, ':id' => $expectedId, ':index' => $index, ':key' => $required]);
                }
            }

            $unknown = array_diff(array_keys($field), self::SETTINGS_FIELD_KEYS);
            if ($unknown !== []) {
                throw new InformationException('The :file of extension ":id" has a settings field ":name" with unknown key(s): :keys.', [':file' => self::FILENAME, ':id' => $expectedId, ':name' => (string) $field['name'], ':keys' => implode(', ', $unknown)]);
            }

            $name = (string) $field['name'];
            if (isset($seen[$name])) {
                throw new InformationException('The :file of extension ":id" declares the settings field ":name" more than once.', [':file' => self::FILENAME, ':id' => $expectedId, ':name' => $name]);
            }
            $seen[$name] = true;

            $fields[] = $field;
        }

        return $fields;
    }
}
