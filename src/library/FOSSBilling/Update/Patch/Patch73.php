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

use FOSSBilling\System\Config;
use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch73 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Merge guest/public support tickets into the regular support ticket tables.
        // See https://github.com/FOSSBilling/FOSSBilling/pull/3799
        if (!$patcher->tableHasColumn('support_ticket', 'access_hash')) {
            $patcher->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `access_hash` VARCHAR(255) DEFAULT NULL AFTER `client_id`;');
        }

        if (!$patcher->tableHasColumn('support_ticket', 'author_name')) {
            $patcher->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `author_name` VARCHAR(255) DEFAULT NULL AFTER `access_hash`;');
        }

        if (!$patcher->tableHasColumn('support_ticket', 'author_email')) {
            $patcher->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `author_email` VARCHAR(255) DEFAULT NULL AFTER `author_name`;');
        }

        if (!$patcher->tableHasIndex('support_ticket', 'access_hash_idx')) {
            $patcher->executeSql('ALTER TABLE `support_ticket` ADD INDEX `access_hash_idx` (`access_hash`);');
        }

        $patcher->executeSql("
            DELETE FROM email_template
            WHERE action_code IN (
                'mod_staff_pticket_close',
                'mod_staff_pticket_open',
                'mod_staff_pticket_reply',
                'mod_support_pticket_open',
                'mod_support_pticket_staff_close',
                'mod_support_pticket_staff_open',
                'mod_support_pticket_staff_reply'
            )
        ");

        // Remove obsolete guest/public ticket email templates from extracted update archives.
        $patcher->executeFileActions([
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_close.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_reply.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_close.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_reply.html.twig') => 'unlink',
        ]);

        $row = $patcher->fetchOne(
            "SELECT meta_value FROM extension_meta WHERE extension = 'mod_support' AND meta_key = 'config'",
        );

        if (is_string($row) && $row !== '') {
            $configJson = $patcher->di['crypt']->decrypt($row, Config::getProperty('info.salt'));
            if (is_string($configJson)) {
                $config = json_decode($configJson, true);
                if (is_array($config) && isset($config['disable_public_tickets']) && !isset($config['disable_guest_tickets'])) {
                    $config['disable_guest_tickets'] = $config['disable_public_tickets'];
                    unset($config['disable_public_tickets']);
                    $encrypted = $patcher->di['crypt']->encrypt(json_encode($config, JSON_THROW_ON_ERROR), Config::getProperty('info.salt'));
                    $patcher->executeSql(
                        "UPDATE extension_meta SET meta_value = :config WHERE extension = 'mod_support' AND meta_key = 'config'",
                        ['config' => $encrypted],
                    );
                }
            }
        }

        if (!$patcher->tableExists('support_p_ticket')) {
            return;
        }

        // Move legacy support_p_ticket rows into support_ticket, preserving guest access hashes.
        $defaultHelpdeskId = $patcher->fetchOne('SELECT id FROM support_helpdesk ORDER BY id ASC LIMIT 1');
        if (!$defaultHelpdeskId) {
            $now = date('Y-m-d H:i:s');
            $patcher->executeSql(
                'INSERT INTO support_helpdesk (name, close_after, can_reopen, created_at, updated_at) VALUES (:name, :close_after, :can_reopen, :created_at, :updated_at)',
                [
                    'name' => 'General',
                    'close_after' => 24,
                    'can_reopen' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $defaultHelpdeskId = $patcher->fetchOne('SELECT id FROM support_helpdesk ORDER BY id ASC LIMIT 1');
        }

        $publicTickets = $patcher->fetchAll('SELECT * FROM support_p_ticket ORDER BY id ASC');
        foreach ($publicTickets as $publicTicket) {
            $patcher->executeSql(
                'INSERT INTO support_ticket (support_helpdesk_id, client_id, access_hash, author_name, author_email, subject, status, created_at, updated_at)
                 VALUES (:support_helpdesk_id, NULL, :access_hash, :author_name, :author_email, :subject, :status, :created_at, :updated_at)',
                [
                    'support_helpdesk_id' => $defaultHelpdeskId,
                    'access_hash' => $publicTicket['hash'],
                    'author_name' => $publicTicket['author_name'],
                    'author_email' => $publicTicket['author_email'],
                    'subject' => $publicTicket['subject'],
                    'status' => $publicTicket['status'],
                    'created_at' => $publicTicket['created_at'],
                    'updated_at' => $publicTicket['updated_at'],
                ]
            );

            $ticketId = (int) $patcher->getPdo()->lastInsertId();
            $messages = $patcher->tableExists('support_p_ticket_message')
                ? $patcher->fetchAll('SELECT * FROM support_p_ticket_message WHERE support_p_ticket_id = :id ORDER BY id ASC', [
                    'id' => $publicTicket['id'],
                ]) : [];

            foreach ($messages as $message) {
                $patcher->executeSql(
                    'INSERT INTO support_ticket_message (support_ticket_id, admin_id, content, ip, created_at, updated_at)
                     VALUES (:support_ticket_id, :admin_id, :content, :ip, :created_at, :updated_at)',
                    [
                        'support_ticket_id' => $ticketId,
                        'admin_id' => $message['admin_id'],
                        'content' => $message['content'],
                        'ip' => $message['ip'],
                        'created_at' => $message['created_at'],
                        'updated_at' => $message['updated_at'],
                    ]
                );
            }
        }

        if ($patcher->tableExists('support_p_ticket_message')) {
            $patcher->executeSql('DROP TABLE `support_p_ticket_message`;');
        }

        $patcher->executeSql('DROP TABLE `support_p_ticket`;');
    }
}
