<?php

declare(strict_types=1);

namespace FOSSBilling\Doctrine;

/**
 * An immutable JSON document retaining its original bytes, including legacy invalid data.
 * Decoding is explicit so loading an unrelated entity field never destroys or repairs data.
 */
final readonly class JsonDocument
{
    private function __construct(private string $json)
    {
    }

    public static function fromJson(string $json): self
    {
        return new self($json);
    }

    public static function fromData(array|\stdClass $data): self
    {
        return new self(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function json(): string
    {
        return $this->json;
    }

    /** Strict access preserves JSON object/list distinctions. */
    public function value(): mixed
    {
        return json_decode($this->json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Matches the existing associative config readers' null/fallback behavior.
     * The raw document remains available for inspection and repair.
     *
     * @return array<mixed>|null
     */
    public function arrayData(): ?array
    {
        try {
            $value = json_decode($this->json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($value) ? $value : null;
    }
}
