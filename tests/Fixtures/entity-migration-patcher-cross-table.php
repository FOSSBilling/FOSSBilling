<?php

declare(strict_types=1);

final class EntityMigrationPatcherCrossTableFixture
{
    private function patch(): void
    {
        // $this->executeSql('ALTER TABLE `invoice` ADD COLUMN `replacement_id` bigint(20)');
        $this->executeSql('ALTER TABLE `invoice` ADD INDEX `replacement_id_idx` (`replacement_id`)');
        $this->executeSql('ALTER TABLE `credit_note` ADD COLUMN `replacement_id` bigint(20)');
        $this->executeSql('CREATE TABLE `new_entity` (`replacement_id` bigint(20) DEFAULT NULL);');
    }

    private function executeSql(string $sql): void
    {
    }
}
