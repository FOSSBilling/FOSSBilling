<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * main.
 */
class BBPatch_25 extends BBPatchAbstract
{
    public function patch()
    {
        // Change table data storage engine
        $query = 'ALTER TABLE `activity_admin_history` ENGINE = INNODB;
            ALTER TABLE `activity_client_email` ENGINE = INNODB;
            ALTER TABLE `activity_client_history` ENGINE = INNODB;
            ALTER TABLE `activity_system` ENGINE = INNODB;
            ALTER TABLE `admin` ENGINE = INNODB;
            ALTER TABLE `admin_group` ENGINE = INNODB;
            ALTER TABLE `api_request` ENGINE = INNODB;
            ALTER TABLE `cart` ENGINE = INNODB;
            ALTER TABLE `cart_product` ENGINE = INNODB;
            ALTER TABLE `client` ENGINE = INNODB;
            ALTER TABLE `client_balance` ENGINE = INNODB;
            ALTER TABLE `client_group` ENGINE = INNODB;
            ALTER TABLE `client_order` ENGINE = INNODB;
            ALTER TABLE `client_order_meta` ENGINE = INNODB;
            ALTER TABLE `client_order_status` ENGINE = INNODB;
            ALTER TABLE `client_password_reset` ENGINE = INNODB;
            ALTER TABLE `currency` ENGINE = INNODB;
            ALTER TABLE `custom_pages` ENGINE = INNODB;
            ALTER TABLE `email_template` ENGINE = INNODB;
            ALTER TABLE `extension` ENGINE = INNODB;
            ALTER TABLE `extension_meta` ENGINE = INNODB;
            ALTER TABLE `form` ENGINE = INNODB;
            ALTER TABLE `form_field` ENGINE = INNODB;
            ALTER TABLE `forum` ENGINE = INNODB;
            ALTER TABLE `forum_topic` ENGINE = INNODB;
            ALTER TABLE `forum_topic_message` ENGINE = INNODB;
            ALTER TABLE `invoice` ENGINE = INNODB;
            ALTER TABLE `invoice_item` ENGINE = INNODB;
            ALTER TABLE `kb_article` ENGINE = INNODB;
            ALTER TABLE `kb_article_category` ENGINE = INNODB;
            ALTER TABLE `mod_email_queue` ENGINE = INNODB;
            ALTER TABLE `mod_massmailer` ENGINE = INNODB;
            ALTER TABLE `pay_gateway` ENGINE = INNODB;
            ALTER TABLE `post` ENGINE = INNODB;
            ALTER TABLE `product` ENGINE = INNODB;
            ALTER TABLE `product_category` ENGINE = INNODB;
            ALTER TABLE `product_payment` ENGINE = INNODB;
            ALTER TABLE `promo` ENGINE = INNODB;
            ALTER TABLE `queue` ENGINE = INNODB;
            ALTER TABLE `queue_message` ENGINE = INNODB;
            ALTER TABLE `service_custom` ENGINE = INNODB;
            ALTER TABLE `service_domain` ENGINE = INNODB;
            ALTER TABLE `service_downloadable` ENGINE = INNODB;
            ALTER TABLE `service_hosting` ENGINE = INNODB;
            ALTER TABLE `service_hosting_hp` ENGINE = INNODB;
            ALTER TABLE `service_hosting_server` ENGINE = INNODB;
            ALTER TABLE `service_license` ENGINE = INNODB;
            ALTER TABLE `service_membership` ENGINE = INNODB;
            ALTER TABLE `service_solusvm` ENGINE = INNODB;
            ALTER TABLE `session` ENGINE = INNODB;
            ALTER TABLE `setting` ENGINE = INNODB;
            ALTER TABLE `subscription` ENGINE = INNODB;
            ALTER TABLE `support_helpdesk` ENGINE = INNODB;
            ALTER TABLE `support_p_ticket` ENGINE = INNODB;
            ALTER TABLE `support_p_ticket_message` ENGINE = INNODB;
            ALTER TABLE `support_pr` ENGINE = INNODB;
            ALTER TABLE `support_pr_category` ENGINE = INNODB;
            ALTER TABLE `support_ticket` ENGINE = INNODB;
            ALTER TABLE `support_ticket_message` ENGINE = INNODB;
            ALTER TABLE `support_ticket_note` ENGINE = INNODB;
            ALTER TABLE `tax` ENGINE = INNODB;
            ALTER TABLE `tld` ENGINE = INNODB;
            ALTER TABLE `tld_registrar` ENGINE = INNODB;
            ALTER TABLE `transaction` ENGINE = INNODB;
        ';

        $this->execSql($query);
    }
}

/**
 * 4.22 betas.
 */
class BBPatch_24 extends BBPatchAbstract
{
    public function patch()
    {
        // Initializing meta description support for announcements
        $q = 'ALTER TABLE `post` ADD `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL  AFTER `title`';
        $this->execSql($q);

        // Clearing leftovers from deleted payment adapters
        $q = "DELETE FROM `pay_gateway` WHERE `gateway` = 'AlertPay' OR `gateway` = 'WebToPay' OR `gateway` = 'Payza' OR `gateway` = 'SinglePay'";
        $this->execSql($q);

        // Setting up the new custom pages extension
        $q = 'CREATE TABLE IF NOT EXISTS `custom_pages` (`id` int(11) NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL, `description` varchar(555) NOT NULL, `keywords` varchar(555) NOT NULL, `content` text NOT NULL, `slug` varchar(255) NOT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $this->execSql($q);
    }
}

class BBPatch_23 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE mod_email_queue CHANGE `from` sender varchar(255) ;';
        $this->execSql($q);

        $q = 'ALTER TABLE mod_email_queue CHANGE `to` recipient varchar(255);';
        $this->execSql($q);

        $q = 'ALTER TABLE queue CHANGE `mod` module varchar(255);';
        $this->execSql($q);
    }
}

class BBPatch_22 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `client_balance` CHANGE  `rel_id`  `rel_id` VARCHAR(20) NULL DEFAULT NULL;';
        $this->execSql($q);
    }
}

/**
 * Version 4.14.
 */
class BBPatch_21 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'CREATE TABLE IF NOT EXISTS `mod_email_queue` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `to` varchar(255) NOT NULL,
          `from` varchar(255) NOT NULL,
          `subject` varchar(255) NOT NULL,
          `content` text NOT NULL,
          `to_name` varchar(255) DEFAULT NULL,
          `from_name` varchar(255) DEFAULT NULL,
          `client_id` int(11) DEFAULT NULL,
          `admin_id` int(11) DEFAULT NULL,
          `priority` int(11) DEFAULT NULL,
          `tries` int(11) NOT NULL,
          `status` varchar(20) NOT NULL,
          `created_at` datetime NOT NULL,
          `updated_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';
        $this->execSql($q);
    }
}
/**
 * Version 4.12.
 */
class BBPatch_20 extends BBPatchAbstract
{
    public function patch()
    {
        $tables = [
            'activity_admin_history' => ['created_at', 'updated_at'],
            'activity_client_email' => ['created_at', 'updated_at'],
            'activity_client_history' => ['created_at', 'updated_at'],
            'activity_system' => ['created_at', 'updated_at'],
            'admin' => ['created_at', 'updated_at'],
            'admin_group' => ['created_at', 'updated_at'],
            'api_request' => ['created_at'],
            'cart' => ['created_at', 'updated_at'],
            'client' => ['created_at', 'updated_at'],
            'client_balance' => ['created_at', 'updated_at'],
            'client_group' => ['created_at', 'updated_at'],
            'client_order' => ['expires_at', 'activated_at', 'suspended_at', 'unsuspended_at', 'canceled_at', 'created_at', 'updated_at'],
            'client_order_meta' => ['created_at', 'updated_at'],
            'client_order_status' => ['created_at', 'updated_at'],
            'client_password_reset' => ['created_at', 'updated_at'],
            'currency' => ['created_at', 'updated_at'],
            'extension_meta' => ['created_at', 'updated_at'],
            'form' => ['created_at', 'updated_at'],
            'form_field' => ['created_at', 'updated_at'],
            'forum' => ['created_at', 'updated_at'],
            'forum_topic' => ['created_at', 'updated_at'],
            'forum_topic_message' => ['created_at', 'updated_at'],
            'invoice' => ['due_at', 'reminded_at', 'paid_at', 'created_at', 'updated_at'],
            'invoice_item' => ['created_at', 'updated_at'],
            'kb_article' => ['created_at', 'updated_at'],
            'kb_article_category' => ['created_at', 'updated_at'],
            'mod_email_queue' => ['created_at', 'updated_at'],
            'mod_massmailer' => ['created_at', 'updated_at'],
            'post' => ['publish_at', 'published_at', 'expires_at', 'created_at', 'updated_at'],
            'product' => ['created_at', 'updated_at'],
            'product_category' => ['created_at', 'updated_at'],
            'promo' => ['start_at', 'end_at', 'created_at', 'updated_at'],
            'queue' => ['created_at', 'updated_at'],
            'queue_message' => ['execute_at', 'created_at', 'updated_at'],
            'service_custom' => ['created_at', 'updated_at'],
            'service_domain' => ['synced_at', 'registered_at', 'expires_at', 'created_at', 'updated_at'],
            'service_downloadable' => ['created_at', 'updated_at'],
            'service_hosting' => ['created_at', 'updated_at'],
            'service_hosting_hp' => ['created_at', 'updated_at'],
            'service_hosting_server' => ['created_at', 'updated_at'],
            'service_license' => ['checked_at', 'pinged_at', 'created_at', 'updated_at'],
            'service_membership' => ['created_at', 'updated_at'],
            'service_solusvm' => ['created_at', 'updated_at'],
            'setting' => ['created_at', 'updated_at'],
            'subscription' => ['created_at', 'updated_at'],
            'support_helpdesk' => ['created_at', 'updated_at'],
            'support_p_ticket' => ['created_at', 'updated_at'],
            'support_p_ticket_message' => ['created_at', 'updated_at'],
            'support_pr' => ['created_at', 'updated_at'],
            'support_pr_category' => ['created_at', 'updated_at'],
            'support_ticket' => ['created_at', 'updated_at'],
            'support_ticket_message' => ['created_at', 'updated_at'],
            'support_ticket_note' => ['created_at', 'updated_at'],
            'tax' => ['created_at', 'updated_at'],
            'tld' => ['created_at', 'updated_at'],
            'transaction' => ['created_at', 'updated_at'],
        ];

        foreach ($tables as $table => $fields) {
            foreach ($fields as $field) {
                try {
                    $this->execSql("ALTER TABLE $table MODIFY $field datetime");
                } catch (Exception $e) {
                    error_log(sprintf('Error changing table %s field %s type: %s ', $table, $field, $e->getMessage()));
                }
            }
        }
    }
}

/**
 * Version 4.12.
 */
class BBPatch_19 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE `client` MODIFY  `birthday` date;';
        $this->execSql($q);
    }
}

/**
 * Version 4.12.
 */
class BBPatch_18 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE `promo` ADD  `client_groups` TEXT NULL AFTER  `products`;';
        $this->execSql($q);
    }
}

/**
 * Version X.XX.X.
 */
class BBPatch_17 extends BBPatchAbstract
{
    public function patch()
    {
        $q = "CREATE TABLE IF NOT EXISTS `session` (
              `id` varchar(32) NOT NULL DEFAULT '',
              `modified_at` int(11) DEFAULT NULL,
              `content` text,
              UNIQUE KEY `unique_id` (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        $this->execSql($q);
    }
}

/**
 * Version X.XX.X.
 */
class BBPatch_16 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'CREATE TABLE IF NOT EXISTS `form` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) DEFAULT NULL,
              `style` text,
              `created_at` varchar(35) DEFAULT NULL,
              `updated_at` varchar(35) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $this->execSql($q);

        $q = 'CREATE TABLE IF NOT EXISTS `form_field` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `form_id` bigint(20) DEFAULT NULL,
              `name` varchar(255) DEFAULT NULL,
              `label` varchar(255) DEFAULT NULL,
              `hide_label` tinyint(1) DEFAULT NULL,
              `description` varchar(255) DEFAULT NULL,
              `type` varchar(255) DEFAULT NULL,
              `default_value` varchar(255) DEFAULT NULL,
              `required` tinyint(1) DEFAULT NULL,
              `hidden` tinyint(1) DEFAULT NULL,
              `readonly` tinyint(1) DEFAULT NULL,
              `is_unique` tinyint(1) DEFAULT NULL,
              `prefix` varchar(255) DEFAULT NULL,
              `suffix` varchar(255) DEFAULT NULL,
              `options` text,
              `show_initial` varchar(255) DEFAULT NULL,
              `show_middle` varchar(255) DEFAULT NULL,
              `show_prefix` varchar(255) DEFAULT NULL,
              `show_suffix` varchar(255) DEFAULT NULL,
              `text_size` int(11) DEFAULT NULL,
              `created_at` varchar(35) DEFAULT NULL,
              `updated_at` varchar(35) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `form_id_idx` (`form_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $this->execSql($q);

        $q = 'ALTER TABLE `product` ADD  `form_id` INT NULL AFTER  `product_payment_id`;';
        $this->execSql($q);

        $q = 'ALTER TABLE `client_order` ADD  `form_id` INT NULL AFTER  `product_id`;';
        $this->execSql($q);
    }
}
/**
 * Version 2.12.5.
 */
class BBPatch_15 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'CREATE TABLE IF NOT EXISTS `client_order_meta` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `client_order_id` bigint(20) DEFAULT NULL,
            `name` varchar(255) DEFAULT NULL,
            `value` text,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `client_order_id_idx` (`client_order_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';

        $this->execSql($q);

        $q = 'ALTER TABLE `invoice` ADD  `gateway_id` INT NULL AFTER  `buyer_email`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `post` ADD  `image` VARCHAR( 255 ) NULL AFTER  `status`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `post` ADD  `section` VARCHAR( 255 ) NULL AFTER  `image`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `post` ADD  `publish_at` VARCHAR( 255 ) NULL AFTER `section`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `post` ADD  `published_at` VARCHAR( 255 ) NULL AFTER `publish_at`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `post` ADD  `expires_at` VARCHAR( 255 ) NULL AFTER `published_at`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client` ADD  `email_approved` BOOLEAN NULL AFTER  `status`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client_order` ADD  `form_id` INTEGER NULL AFTER  `product_id`;';
        $this->execSql($q);
    }
}

/**
 * Version 2.9.14.
 */
class BBPatch_14 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `service_license` ADD  `checked_at` VARCHAR( 35 ) DEFAULT NULL AFTER  `plugin`;';
        $this->execSql($q);
    }
}

/**
 * Version 2.9.10.
 */
class BBPatch_13 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `forum` ADD  `category` VARCHAR( 255 ) NOT NULL AFTER  `id`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `forum_topic_message` ADD  `points` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `ip`;';
        $this->execSql($q);

        $q = "UPDATE forum SET category = 'General purpose' WHERE category IS NULL OR category = '';";
        $this->execSql($q);
    }
}

/**
 * Version 2.8.9.
 */
class BBPatch_12 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `admin` ADD  `permissions` TEXT NULL DEFAULT NULL AFTER  `api_token`;';
        $this->execSql($q);
    }
}

/**
 * Version 2.7.29.
 */
class BBPatch_11 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `client_order` ADD  `referred_by` VARCHAR( 255 ) NULL AFTER  `config`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client` ADD  `company_vat` VARCHAR( 255 ) NULL AFTER  `company`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client` ADD  `company_number` VARCHAR( 255 ) NULL AFTER  `company_vat`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client` ADD  `type` VARCHAR( 255 ) NULL AFTER  `tax_exempt`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `seller_company_vat` VARCHAR( 255 ) NULL AFTER  `seller_company`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `seller_company_number` VARCHAR( 255 ) NULL AFTER  `seller_company_vat`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `buyer_phone_cc` VARCHAR( 255 ) NULL AFTER  `buyer_phone`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `buyer_company_vat` VARCHAR( 255 ) NULL AFTER  `buyer_company`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `buyer_company_number` VARCHAR( 255 ) NULL AFTER  `buyer_company_vat`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `text_1` TEXT NULL DEFAULT NULL AFTER  `notes`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `text_2` TEXT NULL DEFAULT NULL AFTER  `text_1`;';
        $this->execSql($q);

        try {
            $this->di['api_admin']->extension_activate(['id' => 'queue', 'type' => 'mod']);
        } catch (Exception $e) {
            error_log('Error enabling queue extension ' . $e->getMessage());
        }
    }
}

/**
 * Version 2.7.2.
 */
class BBPatch_10 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `client` ADD  `auth_type` VARCHAR( 255 ) NULL AFTER  `role`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice_item` CHANGE  `rel_id`  `rel_id` TEXT NULL DEFAULT NULL;';
        $this->execSql($q);
    }
}
/**
 * Version 2.6.22.
 */
class BBPatch_9 extends BBPatchAbstract
{
    public function patch()
    {
        $q = "UPDATE currency SET format = REPLACE(format, '%price%' , '{{price}}' ) WHERE 1;";
        $this->execSql($q);
    }
}

/**
 * Version 2.6.17.
 */
class BBPatch_8 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `client` ADD  `salt` VARCHAR(255) NULL AFTER  `pass`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `admin` ADD  `salt` VARCHAR(255) NULL AFTER  `pass`;';
        $this->execSql($q);
    }
}

/**
 * Version 2.6.16.
 */
class BBPatch_7 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `email_template` ADD  `description` TEXT NULL AFTER  `content`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_domain` ADD  `synced_at` VARCHAR( 255 ) NULL AFTER  `details`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_license` ADD  `pinged_at` VARCHAR( 255 ) NULL AFTER  `plugin`;';
        $this->execSql($q);

        $q = "UPDATE email_template SET category = 'DEPRECATED - NOT USED ANY MORE' WHERE 1;";
        $this->execSql($q);

        // Migrate email options to module options table
        try {
            $api = $this->di['api_admin'];
            $config = [
                'ext' => 'mod_email',
                'mailer' => $api->system_param(['key' => 'mailer']),
                'smtp_host' => $api->system_param(['key' => 'smtp_host']),
                'smtp_port' => $api->system_param(['key' => 'smtp_port']),
                'smtp_username' => $api->system_param(['key' => 'smtp_username']),
                'smtp_password' => $api->system_param(['key' => 'smtp_password']),
                'smtp_security' => $api->system_param(['key' => 'smtp_security']),
                'sendgrid_username' => $api->system_param(['key' => 'sendgrid_username']),
                'sendgrid_password' => $api->system_param(['key' => 'sendgrid_password']),
            ];
            $api->extension_config_save($config);
        } catch (Exception $e) {
            error_log('Error migrating email settings ' . $e->getMessage());
        }
    }
}

/**
 * Version 2.6.12.
 */
class BBPatch_6 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `transaction` CHANGE  `amount`  `amount` VARCHAR( 255 ) NULL DEFAULT NULL;';
        $this->execSql($q);

        $q = "ALTER TABLE  `currency` ADD  `price_format` VARCHAR( 50 ) NOT NULL DEFAULT  '1' AFTER  `format`;";
        $this->execSql($q);

        $q = 'ALTER TABLE  `email_template` ADD  `vars` TEXT NOT NULL AFTER  `content`;';
        $this->execSql($q);

        // Activate spamchecker extension and migrate options
        try {
            $api = $this->di['api_admin'];
            $api->extension_activate(['id' => 'spamchecker', 'type' => 'mod']);

            $enabled = $api->system_param(['key' => 'captcha_enabled']);
            $pubkey = $api->system_param(['key' => 'captcha_recaptcha_publickey']);
            $privkey = $api->system_param(['key' => 'captcha_recaptcha_privatekey']);
            $config = [
                'ext' => 'mod_spamchecker',
                'captcha_enabled' => $enabled,
                'captcha_recaptcha_publickey' => $pubkey,
                'captcha_recaptcha_privatekey' => $privkey,
                'sfs' => false,
            ];
            $api->extension_config_save($config);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
}

/**
 * Version 2.5.31.
 */
class BBPatch_5 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `extension_meta` ADD  `client_id` INT NULL DEFAULT NULL AFTER  `id`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `product` ADD  `plugin_config` TEXT NULL DEFAULT NULL AFTER  `plugin`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `plugin_config` TEXT NULL DEFAULT NULL AFTER  `plugin`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `invoice` ADD  `currency_rate` DECIMAL( 13, 6 ) NULL DEFAULT NULL AFTER  `currency`;';
        $this->execSql($q);

        $q = "ALTER TABLE  `product_payment` ADD `w_price` decimal(18,2) DEFAULT '0.00' AFTER  `once_setup_price`;";
        $this->execSql($q);

        $q = "ALTER TABLE  `product_payment` ADD `w_setup_price` decimal(18,2) DEFAULT '0.00' AFTER  `tria_price`;";
        $this->execSql($q);

        $q = "ALTER TABLE  `product_payment` ADD `w_enabled` tinyint(1) DEFAULT '1' AFTER  `tria_setup_price`;";
        $this->execSql($q);

        $q = 'UPDATE `product_payment` SET `w_enabled` = 0;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f1` TEXT NULL AFTER  `config`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f2` TEXT NULL AFTER  `f1`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f3` TEXT NULL AFTER  `f2`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f4` TEXT NULL AFTER  `f3`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f5` TEXT NULL AFTER  `f4`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f6` TEXT NULL AFTER  `f5`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f7` TEXT NULL AFTER  `f6`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f8` TEXT NULL AFTER  `f7`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f9` TEXT NULL AFTER  `f8`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_custom` ADD  `f10` TEXT NULL AFTER  `f9`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `extension_meta` CHANGE  `rel_id`  `rel_id` VARCHAR( 255 ) NULL DEFAULT NULL;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client_order` ADD  `invoice_option` VARCHAR( 255 ) NULL AFTER  `group_master`;';
        $this->execSql($q);

        $q = "UPDATE `client_order` SET `invoice_option` = 'issue-invoice' WHERE 1;";
        $this->execSql($q);
    }
}

class BBPatch_4 extends BBPatchAbstract
{
    public function patch()
    {
        $q = "INSERT INTO `email_template` (`action_code`, `category`, `enabled`, `subject`, `content`) VALUES ('support_ticket_admin_close', 'support', 1, 'Support Ticket Closed', '<p>Ticket ID: #{{ticket.id}}<br/> closed</p>\n<hr/>\n<p>{{company.signature}}</p>');";
        $this->execSql($q);

        $q = "INSERT INTO `email_template` (`action_code`, `category`, `enabled`, `subject`, `content`) VALUES ('staff_ticket_client_close', 'staff', 1, 'Support Ticket Closed', '<p>Ticket ID: #{{ticket.id}}<br/> closed</p>\n<hr/>\n<p>{{company.signature}}</p>');";
        $this->execSql($q);

        $q = 'ALTER TABLE  `client_order` ADD  `promo_recurring` tinyint(1) NULL AFTER  `promo_id`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `client_order` ADD  `promo_used` int NULL AFTER  `promo_recurring`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `promo` ADD  `once_per_client` tinyint(1) NULL AFTER  `freesetup`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `promo` ADD  `recurring` tinyint(1) AFTER  `once_per_client`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_license` ADD  `config` TEXT NULL AFTER  `versions`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `extension` ADD  `manifest` TEXT NULL AFTER  `version`;';
        $this->execSql($q);

        $q = 'CREATE TABLE `queue` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) DEFAULT NULL,
            `timeout` bigint(20) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ';
        $this->execSql($q);

        $q = 'CREATE TABLE `queue_message` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `queue_id` bigint(20) DEFAULT NULL,
            `handle` char(32) DEFAULT NULL,
            `body` longblob,
            `hash` char(32) DEFAULT NULL,
            `timeout` double(18,2) DEFAULT NULL,
            `log` text,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `queue_id_idx` (`queue_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';

        $this->execSql($q);
    }
}

class BBPatch_3 extends BBPatchAbstract
{
    public function patch()
    {
        $q = "INSERT INTO `extension` (`type`, `name`, `status`, `version`) VALUES
            ('mod', 'branding', 'installed', '1.0.0');";
        $this->execSql($q);

        $q = 'CREATE TABLE IF NOT EXISTS `extension_meta` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `extension` varchar(255) DEFAULT NULL,
              `rel_type` varchar(255) DEFAULT NULL,
              `rel_id` bigint(20) DEFAULT NULL,
              `meta_key` varchar(255) DEFAULT NULL,
              `meta_value` longtext,
              `created_at` varchar(35) DEFAULT NULL,
              `updated_at` varchar(35) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $this->execSql($q);
    }
}

class BBPatch_2 extends BBPatchAbstract
{
    public function patch()
    {
        $q = 'ALTER TABLE  `client_order` CHANGE  `group_id`  `group_id` VARCHAR( 255 ) NULL DEFAULT NULL;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `transaction` ADD  `output` TEXT NOT NULL AFTER  `ipn`';
        $this->execSql($q);

        $q = 'ALTER TABLE  `extension` CHANGE  `version`  `version` VARCHAR( 100 ) NULL DEFAULT NULL;';
        $this->execSql($q);

        $q = 'TRUNCATE TABLE `extension`;';
        $this->execSql($q);

        $q = "INSERT INTO `extension` (`id`, `type`, `name`, `status`, `version`) VALUES
            (1, 'mod', 'forum', 'installed', '1.0.0'),
            (2, 'mod', 'kb', 'installed', '1.0.0'),
            (3, 'mod', 'news', 'installed', '1.0.0');";
        $this->execSql($q);

        $q = 'ALTER TABLE  `setting` ADD `category` VARCHAR( 255 ) NULL AFTER `public`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_hosting_server` ADD  `port` VARCHAR( 20 ) NOT NULL AFTER  `accesshash`;';
        $this->execSql($q);

        $q = 'ALTER TABLE  `service_hosting_server` ADD  `config` TEXT NOT NULL AFTER  `port`;';
        $this->execSql($q);
    }
}

class BBPatch_1 extends BBPatchAbstract
{
    public function patch()
    {
        $query = "
        INSERT INTO `email_template` (`action_code`, `category`, `enabled`, `subject`, `content`) VALUES
        ('staff_ticket_client_open', 'staff', 1, 'New ticket received', '<p>Direct link: {{''support/ticket''|alink}}/{{ticket.id}}</p>\n<p>Helpdesk: {{ticket.helpdesk}}</p>\n<p>Subject: {{ticket.subject}}</p>\n{{ticket.message}}\n<p></p>\n<p>{{company.signature}}</p>'),
        ('staff_order_created', 'staff', 1, 'New order placed', '<p>New order for {{ order.title }} placed at {{company.www}}</p>\n<p>Direct link: {{''order/manage''|alink}}/{{order.id}}</p>\n<p>{{company.signature}}</p>'),
        ('staff_client_signup', 'staff', 1, 'New client signed up', '<p>New client signed up at {{company.www}}</p>\n<p>Direct link: {{''client/manage''|alink}}/{{client.id}}</p>\n<p>{{company.signature}}</p>');
        ";

        $this->execSql($query);
    }
}

abstract class BBPatchAbstract
{
    protected $pdo = null;
    private $version = 0;
    private $k = 'last_patch';

    public function __construct($di)
    {
        $this->di = $di;
        $this->pdo = $di['pdo'];
        $c = get_class($this);
        $this->version = (int) substr($c, strpos($c, '_') + 1);
    }

    abstract public function patch();

    public function donePatching()
    {
        $this->setParamValue($this->k, $this->version);
    }

    protected function execSql($sql)
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function isPatched()
    {
        return $this->getParamValue($this->k, 0) >= $this->version;
    }

    private function setParamValue($param, $value)
    {
        if (is_null($this->getParamValue($param))) {
            $query = 'INSERT INTO setting (param, value, public, updated_at, created_at) VALUES (:param, :value, 1, :u, :c)';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'c' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s')]);
        } else {
            $query = 'UPDATE setting SET value = :value, updated_at = :u WHERE param = :param';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'u' => date('Y-m-d H:i:s')]);
        }
    }

    private function getParamValue($param, $default = null)
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['param' => $param]);
        $r = $stmt->fetchColumn();
        if (false === $r) {
            return $default;
        }

        return $r;
    }
}

$patches = [];
foreach (get_declared_classes() as $class) {
    if (false !== strpos($class, 'BBPatch_')) {
        $patches[] = $class;
    }
}

require_once dirname(__FILE__) . '/bb-load.php';
$di = include dirname(__FILE__) . '/bb-di.php';

error_log('Executing FOSSBilling update script');
natsort($patches);
foreach ($patches as $class) {
    $p = new $class($di);
    if (!$p->isPatched()) {
        $msg = 'FOSSBilling patch #' . $p->getVersion() . ' executing...';
        error_log($msg);
        $p->patch();
        $p->donePatching();
        $msg = 'FOSSBilling patch #' . $p->getVersion() .' was executed';
        error_log($msg);
        echo $msg . PHP_EOL;
    } else {
        error_log('Skipped patch ' . $p->getVersion());
    }
}
error_log('FOSSBilling update completed');

echo 'Update completed. You are using FOSSBilling ' . Box_Version::VERSION . PHP_EOL;