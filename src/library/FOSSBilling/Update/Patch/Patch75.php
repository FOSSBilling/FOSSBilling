<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch75 implements PatchInterface
{
    public function getVersion(): int
    {
        return 75;
    }

    public function apply(Patcher $patcher): void
    {
        // Drop the legacy `client.document_type` and `client.document_nr` columns.
        // Existing values are copied into the first free `custom_N` slot on each client.
        if (!$patcher->tableHasColumn('client', 'document_nr')) {
            return;
        }

        $rows = $patcher->fetchAll(
            "SELECT id, document_nr FROM `client` WHERE `document_nr` IS NOT NULL AND `document_nr` <> ''"
        );

        $customSlots = ['custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5', 'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10'];

        foreach ($rows as $row) {
            $clientId = (int) $row['id'];
            $documentNr = (string) $row['document_nr'];

            $existing = $patcher->fetchAll(
                'SELECT custom_1, custom_2, custom_3, custom_4, custom_5, custom_6, custom_7, custom_8, custom_9, custom_10 FROM client WHERE id = :id',
                ['id' => $clientId]
            );
            $clientRow = $existing[0] ?? [];

            $targetSlot = null;
            foreach ($customSlots as $slot) {
                if (($clientRow[$slot] ?? null) === null || $clientRow[$slot] === '') {
                    $targetSlot = $slot;

                    break;
                }
            }

            if ($targetSlot !== null) {
                $patcher->executeSql(
                    sprintf('UPDATE `client` SET `%s` = :value WHERE id = :id', $targetSlot),
                    ['value' => $documentNr, 'id' => $clientId]
                );
            } else {
                $patcher->logUpdate('warning', 'patch75: client #{client_id} has no free custom field slot; the document number could not be migrated.', ['client_id' => $clientId]);
            }
        }

        $patcher->executeSql('ALTER TABLE `client` DROP COLUMN `document_type`, DROP COLUMN `document_nr`;');
    }
}
