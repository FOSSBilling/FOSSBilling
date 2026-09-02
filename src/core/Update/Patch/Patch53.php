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

class Patch53 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $pdo = $patcher->getPdo();
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $batchSize = 1000;
            $adminUpdateStmt = $pdo->prepare('UPDATE admin SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');
            $clientUpdateStmt = $pdo->prepare('UPDATE client SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');

            $lastAdminId = 0;
            do {
                $adminIds = $patcher->fetchFirstColumn("SELECT id FROM admin WHERE id > :lastId ORDER BY id ASC LIMIT {$batchSize}", [
                    'lastId' => $lastAdminId,
                ]);

                foreach ($adminIds as $adminId) {
                    $adminUpdateStmt->bindValue('api_token', \FOSSBilling\Security\Credential::generatePassword(32));
                    $adminUpdateStmt->bindValue('updated_at', $now);
                    $adminUpdateStmt->bindValue('id', (int) $adminId, \PDO::PARAM_INT);
                    $adminUpdateStmt->execute();
                }

                if (!empty($adminIds)) {
                    $lastAdminId = (int) end($adminIds);
                }
            } while (!empty($adminIds));

            $lastClientId = 0;
            do {
                $clientIds = $patcher->fetchFirstColumn("SELECT id FROM client WHERE id > :lastId ORDER BY id ASC LIMIT {$batchSize}", [
                    'lastId' => $lastClientId,
                ]);

                foreach ($clientIds as $clientId) {
                    $clientUpdateStmt->bindValue('api_token', \FOSSBilling\Security\Credential::generatePassword(32));
                    $clientUpdateStmt->bindValue('updated_at', $now);
                    $clientUpdateStmt->bindValue('id', (int) $clientId, \PDO::PARAM_INT);
                    $clientUpdateStmt->execute();
                }

                if (!empty($clientIds)) {
                    $lastClientId = (int) end($clientIds);
                }
            } while (!empty($clientIds));

            $patcher->executeSql('DELETE FROM session');

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }
}
