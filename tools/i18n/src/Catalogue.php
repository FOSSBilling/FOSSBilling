<?php

declare(strict_types=1);

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;

final class Catalogue
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value);
    }

    /** Read templates or canonical GNU msgcat output, never evaluate PO content. */
    public static function read(string $path): array
    {
        $entries = [];
        $entry = [];
        $field = null;
        foreach (self::lines($path) as $line) {
            if (trim($line) === '') {
                if (array_key_exists('msgid', $entry)) {
                    $entries[] = $entry;
                }
                $entry = [];
                $field = null;
            } elseif (str_starts_with($line, '#')) {
                continue;
            } elseif (str_starts_with($line, '"')) {
                if ($field === null) {
                    throw new \RuntimeException("$path: unexpected PO continuation");
                }
                $entry[$field] .= self::decode($line);
            } elseif (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(".*")$/D', $line, $match)) {
                $field = $match[1];
                $entry[$field] = self::decode($match[2]);
            } else {
                throw new \RuntimeException("$path: unexpected PO line: $line");
            }
        }
        if (array_key_exists('msgid', $entry)) {
            $entries[] = $entry;
        }

        return $entries;
    }

    private static function decode(string $quoted): string
    {
        if (!str_ends_with($quoted, '"')) {
            throw new \RuntimeException('Unterminated PO string');
        }

        return stripcslashes(substr($quoted, 1, -1));
    }

    /** @return array<string, ?string> */
    public static function entries(string $path): array
    {
        $entries = [];
        foreach (self::read($path) as $entry) {
            if ($entry['msgid'] !== '') {
                $entries[$entry['msgid']] = $entry['msgid_plural'] ?? null;
            }
        }

        return $entries;
    }

    public static function normalized(string $path): string
    {
        $lines = self::lines($path);
        if (end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", array_filter($lines, static fn ($line) => !str_starts_with($line, '"POT-Creation-Date:')));
    }

    private static function lines(string $path): array
    {
        $content = (new Filesystem())->readFile($path);

        return explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    }

    public static function write(array $occurrences, string $path, string $project = '4', bool $strict = false): int
    {
        $merged = [];
        foreach ($occurrences as [$msgid, $plural, $ref]) {
            if (!array_key_exists($msgid, $merged)) {
                $merged[$msgid] = ['refs' => [], 'plural' => null];
            }
            $entry = &$merged[$msgid];
            $entry['refs'][] = $ref;
            if ($plural !== null) {
                if ($entry['plural'] !== null && $entry['plural'] !== $plural) {
                    $message = "conflicting plurals for '$msgid': '{$entry['plural']}' vs '$plural'";
                    if ($strict) {
                        throw new \RuntimeException($message);
                    }
                    fwrite(STDERR, "warning: $message (keeping first)\n");
                } else {
                    $entry['plural'] = $plural;
                }
            }
            unset($entry);
        }
        ksort($merged, SORT_STRING);
        $header = <<<'POT'
            # en_US translation of FOSSBilling Application
            # Copyright 2022-2026 FOSSBilling
            #
            #, fuzzy
            msgid ""
            msgstr ""
            "Project-Id-Version: %s\n"
            "POT-Creation-Date: %s\n"
            "PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\n"
            "Last-Translator: FOSSBilling TM <info@fossbilling.org>\n"
            "Language-Team: FOSSBilling TM <info@fossbilling.org>\n"
            "MIME-Version: 1.0\n"
            "Content-Type: text/plain; charset=UTF-8\n"
            "Content-Transfer-Encoding: 8bit\n"
            "Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n"
            "X-Generator: FOSSBilling i18n\n"

            POT;
        $output = sprintf($header, self::escape($project), gmdate('Y-m-d H:i+0000'));
        foreach ($merged as $msgid => $entry) {
            $refs = array_values(array_unique($entry['refs']));
            usort($refs, static function ($a, $b) {
                $key = static function ($ref) {
                    $at = strrpos($ref, ':');

                    return $at !== false && ctype_digit(substr($ref, $at + 1)) ? [substr($ref, 0, $at), (int) substr($ref, $at + 1)] : [$ref, 0];
                };

                return $key($a) <=> $key($b);
            });
            $output .= "\n#: " . implode(' ', $refs) . "\nmsgid \"" . self::escape((string) $msgid) . "\"\n";
            if ($entry['plural'] !== null) {
                $output .= 'msgid_plural "' . self::escape($entry['plural']) . "\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";
            } else {
                $output .= "msgstr \"\"\n";
            }
        }
        (new Filesystem())->dumpFile($path, $output);

        return count($merged);
    }

    public static function diff(string $oldPath, string $newPath): string
    {
        $old = self::entries($oldPath);
        $new = self::entries($newPath);
        $added = array_keys(array_diff_key($new, $old));
        $removed = array_keys(array_diff_key($old, $new));
        sort($added, SORT_STRING);
        sort($removed, SORT_STRING);
        $lookup = [];
        foreach ($removed as $msgid) {
            $lookup[mb_convert_case((string) $msgid, MB_CASE_FOLD, 'UTF-8')][] = $msgid;
        }
        $casing = [];
        $realAdded = [];
        foreach ($added as $msgid) {
            $key = mb_convert_case((string) $msgid, MB_CASE_FOLD, 'UTF-8');
            if (!empty($lookup[$key])) {
                $casing[] = [array_shift($lookup[$key]), $msgid];
            } else {
                $realAdded[] = $msgid;
            }
        }
        $realRemoved = array_values(array_diff($removed, array_column($casing, 0)));
        $output = sprintf("Added: %d (new: %d, casing-only: %d)\nRemoved: %d (gone: %d, casing-only: %d)\n", count($added), count($realAdded), count($casing), count($removed), count($realRemoved), count($casing));
        if ($casing) {
            $output .= "\nCasing-only changes (invalidate existing translations!):\n";
            foreach ($casing as [$a, $b]) {
                $output .= "  - '$a' -> '$b'\n";
            }
        }
        if ($realAdded) {
            $output .= "\nNew msgids needing translation:\n";
            foreach ($realAdded as $msgid) {
                $output .= "  + '$msgid'\n";
            }
        }
        if ($realRemoved) {
            $output .= "\nDropped msgids:\n";
            foreach ($realRemoved as $msgid) {
                $output .= "  - '$msgid'\n";
            }
        }
        $changed = [];
        foreach (array_intersect_key($old, $new) as $msgid => $plural) {
            if ($plural !== $new[$msgid]) {
                $changed[] = [$msgid, $msgid];
            }
        }
        foreach ($casing as [$a, $b]) {
            if ($old[$a] !== $new[$b]) {
                $changed[] = [$a, $b];
            }
        }
        if ($changed) {
            $output .= "\nChanged plural forms:\n";
            foreach ($changed as [$a, $b]) {
                $output .= "  ~ '$a'" . ($a === $b ? '' : " -> '$b'") . ': ' . var_export($old[$a], true) . ' -> ' . var_export($new[$b], true) . "\n";
            }
        }

        return $output;
    }
}
