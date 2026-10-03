<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch116 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // One transaction covers both the row repairs and the patch-level
        // bookkeeping below: without it, rows committed before a failed
        // setPatchLevel() would be decoded a second time on retry, corrupting
        // values whose true content is a literal entity (`&amp;amp;` would end
        // up as `&`). The loop's own setPatchLevel(116) afterwards is a
        // harmless idempotent rewrite of the same value.
        $pdo = $patcher->getPdo();
        $pdo->beginTransaction();

        try {
            $repaired = $this->decodeLegacyServiceEscapedEntities($patcher);
            $patcher->setPatchLevel(116);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        if ($repaired['invoices'] > 0 || $repaired['notifications'] > 0) {
            $patcher->logUpdate('info', 'Decoded legacy HTML entities in stored data', $repaired);
        }
    }

    /**
     * Repairs rows written while service-layer code HTML-escaped values before
     * storing them (see issue #4305): invoice seller snapshots and staff
     * notification notes.
     *
     * Runs exactly once as patch116, tracked by last_patch like every other
     * data migration - deliberately not unconditionally, so rows written raw
     * under the fixed code (which may legitimately contain entity-like text)
     * are never scanned. At upgrade time every row still predates the fix, and
     * the only systematic writer on these columns escaped, so matching the
     * five htmlspecialchars(ENT_QUOTES) entities selects exactly the legacy
     * rows; one decode pass mirrors the single erroneous encode. Company
     * settings need no repair: they were always stored raw.
     *
     * @return array{invoices: int, notifications: int} rows rewritten per table
     */
    private function decodeLegacyServiceEscapedEntities(Patcher $patcher): array
    {
        $invoiceColumns = [
            'seller_company',
            'seller_company_vat',
            'seller_company_number',
            'seller_address',
            'seller_phone',
            'seller_email',
        ];
        // Matches any of the five htmlspecialchars(ENT_QUOTES) entities. None
        // of these characters is a LIKE wildcard, so no ESCAPE clause needed.
        $entityPatterns = ['%&amp;%', '%&lt;%', '%&gt;%', '%&quot;%', '%&#039;%'];
        $matchesColumn = static fn (string $column): string => implode(' OR ', array_map(static fn (string $pattern): string => "{$column} LIKE '{$pattern}'", $entityPatterns));

        $conditions = array_map($matchesColumn, $invoiceColumns);
        $rows = $patcher->fetchAll(
            'SELECT id, ' . implode(', ', $invoiceColumns) . ' FROM invoice WHERE ' . implode(' OR ', $conditions)
        );
        $repairedInvoices = 0;
        foreach ($rows as $row) {
            $decoded = [];
            foreach ($invoiceColumns as $column) {
                $value = $row[$column] ?? null;
                if (!is_string($value)) {
                    continue;
                }
                $fixed = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($fixed !== $value) {
                    $decoded[$column] = $fixed;
                }
            }
            if ($decoded === []) {
                continue;
            }
            $params = ['id' => $row['id']];
            $sets = [];
            foreach ($decoded as $column => $value) {
                $sets[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $patcher->executeSql('UPDATE invoice SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
            ++$repairedInvoices;
        }

        $notes = $patcher->fetchAll(
            "SELECT id, meta_value FROM extension_meta WHERE extension = 'mod_notification' AND meta_key = 'message' AND (" . $matchesColumn('meta_value') . ')'
        );
        $repairedNotes = 0;
        foreach ($notes as $note) {
            $value = $note['meta_value'] ?? null;
            if (!is_string($value)) {
                continue;
            }
            $fixed = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($fixed === $value) {
                continue;
            }
            $patcher->executeSql('UPDATE extension_meta SET meta_value = :meta_value WHERE id = :id', [
                'meta_value' => $fixed,
                'id' => $note['id'],
            ]);
            ++$repairedNotes;
        }

        return ['invoices' => $repairedInvoices, 'notifications' => $repairedNotes];
    }
}
