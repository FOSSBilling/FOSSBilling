/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table activity_admin_history
# ------------------------------------------------------------



# Dump of table activity_client_email
# ------------------------------------------------------------



# Dump of table activity_client_history
# ------------------------------------------------------------



# Dump of table activity_system
# ------------------------------------------------------------



# Dump of table admin
# ------------------------------------------------------------

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;

INSERT INTO `admin` (`id`, `role`, `admin_group_id`, `email`, `pass`, `salt`, `name`, `signature`, `protected`, `status`, `api_token`, `permissions`, `created_at`, `updated_at`)
VALUES
	(1,'admin',1,'admin@boxbilling.com','$2y$10$/waO8c5q41HngeC2TTvnnuYyz3drDIe6jEMXyh8X6RO/YPoiC.bL.',NULL,'Demo Administrator','Sincerely Yours, Demo Administrator',1,'active','644846a924e9f4ca76f04f39b3f9c8ac',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'cron',1,'FOF2T4t2@d4mTXP4s.com','$2y$10$gp1mj9ikImkOgg/hoMFvtODEAhMoBhzHji/aF.Ujkqv0h2rzb719e',NULL,'System Cron Job','',1,'active',NULL,NULL,'2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00');

/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table admin_group
# ------------------------------------------------------------

LOCK TABLES `admin_group` WRITE;
/*!40000 ALTER TABLE `admin_group` DISABLE KEYS */;

INSERT INTO `admin_group` (`id`, `name`, `created_at`, `updated_at`)
VALUES
	(1,'Administrators','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `admin_group` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table api_request
# ------------------------------------------------------------



# Dump of table cart
# ------------------------------------------------------------



# Dump of table cart_product
# ------------------------------------------------------------



# Dump of table client
# ------------------------------------------------------------

LOCK TABLES `client` WRITE;
/*!40000 ALTER TABLE `client` DISABLE KEYS */;

INSERT INTO `client` (`id`, `aid`, `client_group_id`, `role`, `auth_type`, `email`, `pass`, `salt`, `status`, `email_approved`, `tax_exempt`, `type`, `first_name`, `last_name`, `gender`, `birthday`, `phone_cc`, `phone`, `company`, `company_vat`, `company_number`, `address_1`, `address_2`, `city`, `state`, `postcode`, `country`, `document_type`, `document_nr`, `notes`, `currency`, `lang`, `ip`, `api_token`, `referred_by`, `custom_1`, `custom_2`, `custom_3`, `custom_4`, `custom_5`, `custom_6`, `custom_7`, `custom_8`, `custom_9`, `custom_10`, `created_at`, `updated_at`)
VALUES
	(1,NULL,1,'client',NULL,'client@boxbilling.com','$2y$10$/waO8c5q41HngeC2TTvnnuYyz3drDIe6jEMXyh8X6RO/YPoiC.bL.',NULL,'active',NULL,0,NULL,'Demo','Client',NULL,'1985-02-25','214','15551212','BoxBilling',NULL,NULL,'Holywood','Stairway to heaven','Holywood','LA','95012','US',NULL,NULL,'BoxBilling demo client','USD',NULL,NULL,'client_api_token',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,NULL,1,'client',NULL,'john.smith@boxbilling.com','$2y$10$/waO8c5q41HngeC2TTvnnuYyz3drDIe6jEMXyh8X6RO/YPoiC.bL.',NULL,'active',NULL,0,NULL,'John','Smith',NULL,NULL,'261','4106851180','John\'s Company Inc.',NULL,NULL,'1734 Maryland Avenue',NULL,'Baltimore','MD','21201','US',NULL,NULL,NULL,'USD',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `client` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table client_balance
# ------------------------------------------------------------

LOCK TABLES `client_balance` WRITE;
/*!40000 ALTER TABLE `client_balance` DISABLE KEYS */;

INSERT INTO `client_balance` (`id`, `client_id`, `type`, `rel_id`, `amount`, `description`, `created_at`, `updated_at`)
VALUES
	(1,1,NULL,NULL,1000.00,'Christmas Gift','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `client_balance` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table client_group
# ------------------------------------------------------------

LOCK TABLES `client_group` WRITE;
/*!40000 ALTER TABLE `client_group` DISABLE KEYS */;

INSERT INTO `client_group` (`id`, `title`, `created_at`, `updated_at`)
VALUES
	(1,'Default','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `client_group` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table client_order
# ------------------------------------------------------------



# Dump of table client_order_meta
# ------------------------------------------------------------



# Dump of table client_order_status
# ------------------------------------------------------------



# Dump of table client_password_reset
# ------------------------------------------------------------



# Dump of table currency
# ------------------------------------------------------------

LOCK TABLES `currency` WRITE;
/*!40000 ALTER TABLE `currency` DISABLE KEYS */;

INSERT INTO `currency` (`id`, `title`, `code`, `is_default`, `conversion_rate`, `format`, `price_format`, `created_at`, `updated_at`)
VALUES
	(1,'US Dollar','USD',1,1.000000,'${{price}}','1','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'Euro','EUR',0,0.600000,'€{{price}}','1','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'Pound Sterling','GBP',0,0.600000,'{{price}} ₤','1','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `currency` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table email_template
# ------------------------------------------------------------



# Dump of table extension
# ------------------------------------------------------------

LOCK TABLES `extension` WRITE;
/*!40000 ALTER TABLE `extension` DISABLE KEYS */;

INSERT INTO `extension` (`id`, `type`, `name`, `status`, `version`, `manifest`)
VALUES
	(1,'mod','forum','installed','1.0.0','{\"id\":\"forum\",\"type\":\"mod\",\"name\":\"Forum\",\"description\":\"Forum for BoxBilling\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(2,'mod','kb','installed','1.0.0','{\"id\":\"kb\",\"type\":\"mod\",\"name\":\"Knowledge base\",\"description\":\"Knowledge base module for BoxBilling\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(3,'mod','news','installed','1.0.0','{\"id\":\"news\",\"type\":\"mod\",\"name\":\"News\",\"description\":\"News module for BoxBilling\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(4,'mod','branding','installed','1.0.0','{\"id\":\"branding\",\"type\":\"mod\",\"name\":\"Branding\",\"description\":\"BoxBilling branding module. Can be deactivated by PRO license owners only.\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(5,'mod','servicemembership','installed','1.0.0','{\"id\":\"servicemembership\",\"type\":\"mod\",\"name\":\"Membership product\",\"description\":\"Sell membership products.\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(6,'mod','redirect','installed','1.0.0','{\"id\":\"redirect\",\"type\":\"mod\",\"name\":\"Redirect\",\"description\":\"Manage redirects\",\"homepage_url\":\"https:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/www.boxbilling.com\",\"license\":\"GPL version 2 or later - http:\\/\\/www.gnu.org\\/licenses\\/old-licenses\\/gpl-2.0.html\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}');


/*!40000 ALTER TABLE `extension` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table extension_meta
# ------------------------------------------------------------

LOCK TABLES `extension_meta` WRITE;
/*!40000 ALTER TABLE `extension_meta` DISABLE KEYS */;

INSERT INTO `extension_meta` (`id`, `client_id`, `extension`, `rel_type`, `rel_id`, `meta_key`, `meta_value`, `created_at`, `updated_at`)
VALUES
	(1,NULL,'mod_hook','mod','activity','listener','onAfterClientLogin','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(2,NULL,'mod_hook','mod','activity','listener','onAfterAdminLogin','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(3,NULL,'mod_hook','mod','client','listener','onAfterClientSignUp','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(4,NULL,'mod_hook','mod','extension','listener','onBeforeAdminCronRun','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(5,NULL,'mod_hook','mod','forum','listener','onAfterClientCreateForumTopic','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(6,NULL,'mod_hook','mod','forum','listener','onAfterAdminRepliedInForum','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(7,NULL,'mod_hook','mod','forum','listener','onAfterClientRepliedInForum','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(8,NULL,'mod_hook','mod','hook','listener','onAfterAdminActivateExtension','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(9,NULL,'mod_hook','mod','hook','listener','onAfterAdminDeactivateExtension','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(10,NULL,'mod_hook','mod','invoice','listener','onAfterAdminInvoicePaymentReceived','2014-09-16T06:33:40-04:00','2014-09-16T06:33:40-04:00'),
	(11,NULL,'mod_hook','mod','invoice','listener','onAfterAdminInvoiceApprove','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(12,NULL,'mod_hook','mod','invoice','listener','onAfterAdminInvoiceReminderSent','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(13,NULL,'mod_hook','mod','invoice','listener','onAfterAdminCronRun','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(14,NULL,'mod_hook','mod','invoice','listener','onEventAfterInvoiceIsDue','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(15,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderActivate','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(16,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderRenew','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(17,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderSuspend','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(18,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderUnsuspend','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(19,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderCancel','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(20,NULL,'mod_hook','mod','order','listener','onAfterAdminOrderUncancel','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(21,NULL,'mod_hook','mod','servicedomain','listener','onBeforeAdminCronRun','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(22,NULL,'mod_hook','mod','staff','listener','onAfterClientOrderCreate','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(23,NULL,'mod_hook','mod','staff','listener','onAfterClientOpenTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(24,NULL,'mod_hook','mod','staff','listener','onAfterClientReplyTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(25,NULL,'mod_hook','mod','staff','listener','onAfterClientCloseTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(26,NULL,'mod_hook','mod','staff','listener','onAfterGuestPublicTicketOpen','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(27,NULL,'mod_hook','mod','staff','listener','onAfterClientSignUp','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(28,NULL,'mod_hook','mod','staff','listener','onAfterGuestPublicTicketReply','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(29,NULL,'mod_hook','mod','staff','listener','onAfterGuestPublicTicketClose','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(30,NULL,'mod_hook','mod','support','listener','onAfterClientOpenTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(31,NULL,'mod_hook','mod','support','listener','onAfterAdminOpenTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(32,NULL,'mod_hook','mod','support','listener','onAfterAdminCloseTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(33,NULL,'mod_hook','mod','support','listener','onAfterAdminReplyTicket','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(34,NULL,'mod_hook','mod','support','listener','onAfterGuestPublicTicketOpen','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(35,NULL,'mod_hook','mod','support','listener','onAfterAdminPublicTicketOpen','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(36,NULL,'mod_hook','mod','support','listener','onAfterAdminPublicTicketReply','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(37,NULL,'mod_hook','mod','support','listener','onAfterAdminPublicTicketClose','2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(38,NULL,'mod_order',NULL,NULL,'config',NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(39,NULL,'mod_email',NULL,NULL,'config',NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(40,NULL,'mod_invoice',NULL,NULL,'config',NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00');

/*!40000 ALTER TABLE `extension_meta` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table form
# ------------------------------------------------------------

LOCK TABLES `form` WRITE;
/*!40000 ALTER TABLE `form` DISABLE KEYS */;

INSERT INTO `form` (`id`, `name`, `style`, `created_at`, `updated_at`)
VALUES
	(1,'Hosting','{\"type\":\"horizontal\", \"show_title\":0}','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `form` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table form_field
# ------------------------------------------------------------

LOCK TABLES `form_field` WRITE;
/*!40000 ALTER TABLE `form_field` DISABLE KEYS */;

INSERT INTO `form_field` (`id`, `form_id`, `name`, `label`, `hide_label`, `description`, `type`, `default_value`, `required`, `hidden`, `readonly`, `is_unique`, `prefix`, `suffix`, `options`, `show_initial`, `show_middle`, `show_prefix`, `show_suffix`, `text_size`, `created_at`, `updated_at`)
VALUES
	(1,1,'tekstas1','Tekstas1',0,'This is description','text','Thi is default value',1,0,0,NULL,'@','.00$',NULL,NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,'tekstas2','Tekstas2',0,'This is description','checkbox','{\"first checkbox\":\"0\", \"third checkbox\":\"3\"}',1,0,0,NULL,'@','.00$','{\"first checkbox\":\"0\", \"second checkbox\": \"1\", \"third checkbox\":\"3\", \"fourth checkbox\":\"4\"}',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,1,'tekstas3','Tekstas3',0,'This is description','radio','1',1,0,0,NULL,'@','.00$','{\"first radio\":\"0\", \"second radio\": \"1\", \"third radio\":\"0\"}',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,1,'tekstas5','Tekstas5',0,'This is description','select','3',1,0,0,NULL,'@','.00$','{\"first select\":1, \"second select\":\"2\",\"third\": \"3\"}',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,1,'tekstas4','Tekstas4',0,'This is description','textarea','Thi is default value',1,0,0,NULL,'@','.00$','{\"height\":\"100\", \"width\": \"300\"}',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(6,1,'tekstas6','Tekstas6',0,'This is description','textarea','Thi is default value',1,0,0,NULL,'@','.00$','{\"height\":\"50\", \"width\": \"500\"}',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `form_field` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table forum
# ------------------------------------------------------------

LOCK TABLES `forum` WRITE;
/*!40000 ALTER TABLE `forum` DISABLE KEYS */;

INSERT INTO `forum` (`id`, `category`, `title`, `description`, `slug`, `status`, `priority`, `created_at`, `updated_at`)
VALUES
	(1,'General','Discussions Rules','Rules about discussions','forum-rules','active',1,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'General','General Discussions','Discuss about everything','discuss-about-everything','active',2,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `forum` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table forum_topic
# ------------------------------------------------------------

LOCK TABLES `forum_topic` WRITE;
/*!40000 ALTER TABLE `forum_topic` DISABLE KEYS */;

INSERT INTO `forum_topic` (`id`, `forum_id`, `title`, `slug`, `status`, `sticky`, `views`, `created_at`, `updated_at`)
VALUES
	(1,1,'What about Installation','what-about-installation','active',0,2,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,'Read before posting','read-before-posting','active',0,3,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,2,'How to install BoxBilling','how-to-install-boxbilling','active',0,20,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `forum_topic` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table forum_topic_message
# ------------------------------------------------------------

LOCK TABLES `forum_topic_message` WRITE;
/*!40000 ALTER TABLE `forum_topic_message` DISABLE KEYS */;

INSERT INTO `forum_topic_message` (`id`, `forum_topic_id`, `client_id`, `admin_id`, `message`, `ip`, `points`, `created_at`, `updated_at`)
VALUES
	(1,1,1,NULL,'Its is cool',NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,1,NULL,'Some other message',NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,2,1,NULL,'Some other message',NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,3,2,NULL,'I have some question on how to install BoxBilling?',NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `forum_topic_message` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table invoice
# ------------------------------------------------------------



# Dump of table invoice_item
# ------------------------------------------------------------



# Dump of table kb_article
# ------------------------------------------------------------

LOCK TABLES `kb_article` WRITE;
/*!40000 ALTER TABLE `kb_article` DISABLE KEYS */;

INSERT INTO `kb_article` (`id`, `kb_article_category_id`, `views`, `title`, `content`, `slug`, `status`, `created_at`, `updated_at`)
VALUES
	(1,1,0,'Do you offer free trial','Yes we do\n','do-you-offer-free-trial','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,0,'Do you offer recurent payments','Yes we do\n','do-you-offer-recurent-payments','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,2,0,'How to contact support','Registered clients can contact our support team:\n------------------------------------------------------------\n\n* Login to clients area\n* Find menu item submit new ticket\n* Fill the form and press \"Submit\"\n\nGuests can contact our support team:\n------------------------------------------------------------\n\n* Use our contact form.\n* Fill the form and press \"Submit\"\n','how-to-contact-support','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,2,0,'How to place new order','To place new order, follow these steps:\n------------------------------------------------------------\n\n* Go to order form on our site order form.\n* Choose products category from select box\n* After you have chosen products category, products in that category will appear on the right side of the screen\n* Choose product/service you would like to order\n* Product configuration screen will appear. It may ask you to select billing period and addons you wish to include in order\n* After You have configured Your product, click \"Confirm\". This will add produc/service to cart.\n* You can go back to order form and select more products/services if you wish to\n* Click on \"Checkout\" button to proceed with checkout process\n    * If you are already logged in, You will be automaticaly redirected to prepared invoice\n    * If you are registerd client, You can provide your login details and press \"Login\"\n    * If you have never purchased any service, fill up client sign up form, and then click \"Sign up\"\n* Now you can choose payment method to pay for invoice. List of all avalable payment gateways will be listed below invoice details.\n* Choose payment method\n* You will be redirected to payment gateway page\n* After successfull payment, You will be redirected back to invoice page.\n* Now You can view and manage services page in services section\n','how-to-place-new-order','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,2,26,'BoxBilling live demo','Please note that there may be other users logged into the demo at the\nsame time as you, who may be editing and changing settings at the same\ntime as your testing.\n\nSome features have been intensionally disabled in this demo.\n\nBoxBilling Live demo of admin area can be accesed at:\n\n> [http://demo.boxbilling.com/bb-admin](http://demo.boxbilling.com/bb-admin/staff/login?email=admin@boxbilling.com&password=demo)\n>\n> Username: admin@boxbilling.com\n>\n> Password: demo\n\nBoxBilling Live demo of client area can be accesed at:\n> [http://demo.boxbilling.com/login](http://demo.boxbilling.com/login?email=client@boxbilling.com&password=demo)\n>\n>   Username: client@boxbilling.com\n>\n>   Password: demo\n','live-demo','active','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `kb_article` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table kb_article_category
# ------------------------------------------------------------

LOCK TABLES `kb_article_category` WRITE;
/*!40000 ALTER TABLE `kb_article_category` DISABLE KEYS */;

INSERT INTO `kb_article_category` (`id`, `title`, `description`, `slug`, `created_at`, `updated_at`)
VALUES
	(1,'Frequently asked questions','Section for common issues','faq','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'How to\'s','Section dedicated for tutorials','how-to','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `kb_article_category` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table pay_gateway
# ------------------------------------------------------------

LOCK TABLES `pay_gateway` WRITE;
/*!40000 ALTER TABLE `pay_gateway` DISABLE KEYS */;

INSERT INTO `pay_gateway` (`id`, `name`, `gateway`, `accepted_currencies`, `enabled`, `allow_single`, `allow_recurrent`, `test_mode`, `config`)
VALUES
	(1,'Custom payment gateway','Custom',NULL,1,1,1,0,'{\"single\":\"Transfer {{invoice.total}} {{invoice.currency}}\", \"recurrent\":\"Recurrent payment information\"}'),
	(2,'BankLink','Custom',NULL,1,1,1,0,'{\"single\":\"Transfer {{invoice.total}} {{invoice.currency}}\", \"recurrent\":\"Recurrent payment information\"}'),
	(3,'Authorize.net','AuthorizeNet',NULL,0,1,1,0,NULL),
	(4,'PayPal','PayPalEmail',NULL,1,1,1,0,'{\"email\":\"sales@boxbilling.com\"}'),
	(5,'WebToPay','WebToPay',NULL,0,1,1,1,NULL),
	(6,'AlertPay','AlertPay',NULL,0,1,1,0,NULL);

/*!40000 ALTER TABLE `pay_gateway` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table post
# ------------------------------------------------------------

LOCK TABLES `post` WRITE;
/*!40000 ALTER TABLE `post` DISABLE KEYS */;

INSERT INTO `post` (`id`, `admin_id`, `title`, `content`, `slug`, `status`, `image`, `section`, `publish_at`, `published_at`, `expires_at`, `created_at`, `updated_at`)
VALUES
	(1,1,'BoxBilling is the most affordable Billing Application Ever!','Just in case you weren\'t already aware, BoxBilling is the most affordable client management application ever!\n\nTo learn more about it You can always visit [www.boxbilling.com](http://www.boxbilling.com/)\n','boxbilling-is-affordable-billing-system','active',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,'Check out great features of BoxBilling','* Supports automated billing, invoicing, product provisioning\n* Automatically create accounts as soon as the payment is received, suspend when account becomes overdue, terminate when a specified amount of time passes.\n* Boxbilling is perfectly created to sell shared and reseller hosting accounts, software licenses and downloadable products.\n* Integrated helpdesk, knowledgebase, news and announcements system.\n','great-features-of-boxbilling','active',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,1,'BoxBilling is customizable','* You can create your own simple or advanced hooks on BoxBilling events. For example, send notification via sms when new client signs up.\n* Create custom theme for your client interface\n* Create plugin for any cms page\n','boxbilling-is-customizable','active',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,1,'About us','Now is the time for all good men to come to\nthe aid of their country. This is just a\nregular paragraph.\n\nThe quick brown fox jumped over the lazy\ndog\'s back.\n\n\n> This is a blockquote.\n>\n> This is the second paragraph in the blockquote.\n','about-us','active',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,1,'Unfinished news item','the text is yet to be written\n','to-do','draft',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `post` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product
# ------------------------------------------------------------

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;

INSERT INTO `product` (`id`, `product_category_id`, `product_payment_id`, `form_id`, `title`, `slug`, `description`, `unit`, `active`, `status`, `hidden`, `is_addon`, `setup`, `addons`, `icon_url`, `allow_quantity_select`, `stock_control`, `quantity_in_stock`, `plugin`, `plugin_config`, `upgrades`, `priority`, `config`, `created_at`, `updated_at`, `type`)
VALUES
	(1,4,1,1,'SSL Certificate','ssl-certificate','SSL cetificate information','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,100,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(2,4,2,NULL,'Custom product with plugin','custom-product','Default product description','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,'Plugin_Demo',NULL,NULL,90,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(3,4,3,NULL,'Samsung tv set','samsung-tv-set','Default product description','product',1,'enabled',0,0,'after_payment',NULL,NULL,1,1,10,NULL,NULL,NULL,80,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(4,4,4,NULL,'Free Addon',NULL,'Sold only as an addon','product',1,'enabled',0,1,'after_payment',NULL,NULL,0,0,0,NULL,NULL,NULL,110,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(5,4,5,NULL,'One time payment Addon',NULL,'Sold only as an addon','product',1,'enabled',0,1,'after_payment',NULL,NULL,0,0,0,NULL,NULL,NULL,110,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(6,4,6,NULL,'Recurrent payment addon',NULL,'Addon support description','product',1,'enabled',0,1,'after_payment',NULL,NULL,0,0,0,NULL,NULL,NULL,120,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','custom'),
	(7,3,7,NULL,'BoxBilling license','boxbilling-license','BoxBilling license','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,70,'{\"prefix\":\"BOX-PRO-\", \"length\":25, \"plugin\":\"Default\"}','2020-01-01 12:00:00','2020-01-01 12:00:00','license'),
	(8,3,8,NULL,'Free BoxBilling license','free-license','Free BoxBilling license','product',1,'enabled',0,0,'after_order','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,70,'{\"prefix\":\"BOX-FREE-\", \"length\":25, \"plugin\":\"Default\"}','2020-01-01 12:00:00','2020-01-01 12:00:00','license'),
	(9,1,9,NULL,'My Demo software','boxbilling-software','My Demo software description','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,60,'{\"filename\":\"test.txt\"}','2020-01-01 12:00:00','2020-01-01 12:00:00','downloadable'),
	(10,2,10,NULL,'Shared Hosting','shared-hosting','Shared hosting description','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,2,'{\"server_id\":\"1\", \"hosting_plan_id\":\"1\", \"reseller\":0,\"free_domain_periods\":[\"3M\"],\"free_domain\":\"1\",\"free_tlds\":[\".com\"]}','2020-01-01 12:00:00','2020-01-01 12:00:00','hosting'),
	(11,2,11,NULL,'Gold Hosting','reseller-hosting','Reseller hosting description','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,3,'{\"server_id\":\"1\", \"hosting_plan_id\":\"1\", \"reseller\":1}','2020-01-01 12:00:00','2020-01-01 12:00:00','hosting'),
	(12,5,NULL,NULL,'All domains','domain-checker','Choose domains','product',1,'enabled',0,0,'after_payment','[4,5,6]',NULL,0,0,0,NULL,NULL,NULL,1,'{\"all_tlds\":1}','2020-01-01 12:00:00','2020-01-01 12:00:00','domain'),
	(13,4,12,NULL,'Forum membership','forum-membership','Register for our forum membership','product',1,'enabled',0,0,'after_payment',NULL,NULL,0,0,0,NULL,NULL,NULL,10,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','membership');

/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product_category
# ------------------------------------------------------------

LOCK TABLES `product_category` WRITE;
/*!40000 ALTER TABLE `product_category` DISABLE KEYS */;

INSERT INTO `product_category` (`id`, `title`, `description`, `icon_url`, `created_at`, `updated_at`)
VALUES
	(1,'Downloadable','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris magna nisi, aliquet at condimentum ut, congue et orci.\nIn non arcu eget enim ultricies blandit. Nullam eget eros quis nunc tristique malesuada eu sit amet libero.\n',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'Hosting','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris magna nisi, aliquet at condimentum ut, congue et orci.\nIn non arcu eget enim ultricies blandit. Nullam eget eros quis nunc tristique malesuada eu sit amet libero.\n',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'Licenses','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris magna nisi, aliquet at condimentum ut, congue et orci.\nIn non arcu eget enim ultricies blandit. Nullam eget eros quis nunc tristique malesuada eu sit amet libero.\n',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,'Custom','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris magna nisi, aliquet at condimentum ut, congue et orci.\nIn non arcu eget enim ultricies blandit. Nullam eget eros quis nunc tristique malesuada eu sit amet libero.\n',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,'Domains','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris magna nisi, aliquet at condimentum ut, congue et orci.\nIn non arcu eget enim ultricies blandit. Nullam eget eros quis nunc tristique malesuada eu sit amet libero.\n',NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `product_category` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product_payment
# ------------------------------------------------------------

LOCK TABLES `product_payment` WRITE;
/*!40000 ALTER TABLE `product_payment` DISABLE KEYS */;

INSERT INTO `product_payment` (`id`, `type`, `once_price`, `once_setup_price`, `w_price`, `m_price`, `q_price`, `b_price`, `a_price`, `bia_price`, `tria_price`, `w_setup_price`, `m_setup_price`, `q_setup_price`, `b_setup_price`, `a_setup_price`, `bia_setup_price`, `tria_setup_price`, `w_enabled`, `m_enabled`, `q_enabled`, `b_enabled`, `a_enabled`, `bia_enabled`, `tria_enabled`)
VALUES
	(1,'once',10.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(2,'once',10.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(3,'once',1000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(4,'free',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(5,'once',10.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(6,'recurrent',0.00,0.00,0.00,30.00,20.00,10.00,5.00,50.00,50.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,1,1,1,1,1,1),
	(7,'recurrent',0.00,0.00,0.00,30.00,20.00,10.00,5.00,50.00,50.00,0.00,10.00,10.00,0.00,0.00,0.00,0.00,0,1,1,1,1,1,1),
	(8,'free',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(9,'once',150.00,50.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,1,1),
	(10,'recurrent',0.00,0.00,0.00,10.00,30.00,60.00,120.00,50.00,50.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,1,1,1,1,1,1),
	(11,'recurrent',0.00,0.00,0.00,10.00,30.00,60.00,120.00,50.00,50.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,1,1,1,1,1,1),
	(12,'recurrent',0.00,0.00,0.00,10.00,30.00,60.00,120.00,200.00,400.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,1,1,1,1,1,1);

/*!40000 ALTER TABLE `product_payment` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table promo
# ------------------------------------------------------------

LOCK TABLES `promo` WRITE;
/*!40000 ALTER TABLE `promo` DISABLE KEYS */;

INSERT INTO `promo` (`id`, `code`, `description`, `type`, `value`, `maxuses`, `used`, `freesetup`, `once_per_client`, `recurring`, `active`, `products`, `periods`, `start_at`, `end_at`, `created_at`, `updated_at`)
VALUES
	(1,'PERCENT',NULL,'percentage',50.00,100,0,0,0,0,1,'[7]',NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'CHRISTMAS',NULL,'percentage',100.00,100,0,1,0,0,1,'[7]',NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'FREESETUP',NULL,'absolute',0.00,100,0,1,0,0,1,'[]',NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,'NEW_YEAR',NULL,'absolute',0.00,100,0,1,0,0,1,'[]',NULL,'2020-01-01 12:00:00','2015-01-01 12:00:00','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,'ONCE_PER_CLIENT',NULL,'percentage',100.00,100,0,1,1,0,1,'[]',NULL,'2020-01-01 12:00:00','2015-01-01 12:00:00','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `promo` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table service_custom
# ------------------------------------------------------------



# Dump of table service_domain
# ------------------------------------------------------------



# Dump of table service_downloadable
# ------------------------------------------------------------



# Dump of table service_hosting
# ------------------------------------------------------------



# Dump of table service_hosting_hp
# ------------------------------------------------------------

LOCK TABLES `service_hosting_hp` WRITE;
/*!40000 ALTER TABLE `service_hosting_hp` DISABLE KEYS */;

INSERT INTO `service_hosting_hp` (`id`, `name`, `quota`, `bandwidth`, `max_ftp`, `max_sql`, `max_pop`, `max_sub`, `max_park`, `max_addon`, `config`, `created_at`, `updated_at`)
VALUES
	(1,'Silver','1024','1024',NULL,NULL,NULL,NULL,NULL,'1','{\"custom\":\"value\"}','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'Golden','2048','2048',NULL,NULL,NULL,NULL,NULL,'2','{\"custom\":\"value\"}','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'Platinum','4096','4096',NULL,NULL,NULL,NULL,NULL,'5','{\"custom\":\"value\"}','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `service_hosting_hp` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table service_hosting_server
# ------------------------------------------------------------

LOCK TABLES `service_hosting_server` WRITE;
/*!40000 ALTER TABLE `service_hosting_server` DISABLE KEYS */;

INSERT INTO `service_hosting_server` (`id`, `name`, `ip`, `hostname`, `assigned_ips`, `status_url`, `active`, `max_accounts`, `ns1`, `ns2`, `ns3`, `ns4`, `manager`, `username`, `password`, `accesshash`, `port`, `config`, `secure`, `created_at`, `updated_at`)
VALUES
	(1,'Buffalo','184.22.222.135','server1.main-hosting.com',NULL,NULL,1,1000,'ns1.ilife.lt','ns2.ilife.lt',NULL,NULL,'custom',NULL,NULL,NULL,NULL,NULL,1,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'Server2','184.22.222.139','server2.main-hosting.com',NULL,NULL,1,1000,'ns1.ilife.lt','ns2.ilife.lt',NULL,NULL,'custom',NULL,NULL,NULL,NULL,NULL,1,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `service_hosting_server` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table service_license
# ------------------------------------------------------------



# Dump of table service_membership
# ------------------------------------------------------------



# Dump of table service_solusvm
# ------------------------------------------------------------



# Dump of table setting
# ------------------------------------------------------------

LOCK TABLES `setting` WRITE;
/*!40000 ALTER TABLE `setting` DISABLE KEYS */;

INSERT INTO `setting` (`id`, `param`, `value`, `public`, `category`, `hash`, `created_at`, `updated_at`)
VALUES
	(1,'last_patch','9',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'company_name','Demo BoxBilling.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'company_email','my@mycompany.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,'company_signature','BoxBilling.com - Client Management, Invoice and Support Software',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,'company_logo','/bb-themes/boxbilling/assets/images/logo.png',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(6,'company_address_1','Demo address line 1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(7,'company_address_2','Demo address line 2',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(8,'company_address_3','Demo address line 3',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(9,'company_tel','+123 456 12345',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(10,'company_tos','Sit ridiculus nascetur porta purus tortor, augue natoque, pulvinar integer nisi mattis dignissim mus, elementum nascetur, augue etiam. Mus mus tortor? A mauris habitasse dictumst, scelerisque, dis nec pulvinar magnis velit, integer, nisi, aliquet, elit phasellus? Parturient odio purus tristique porttitor augue diam pulvinar magna ac lacus in. Augue tincidunt sociis ultrices parturient aliquet dapibus sit. Pulvinar mauris platea in amet penatibus augue ut non ridiculus, nunc lundium. Duis dapibus a mid proin pellentesque lundium vut mauris egestas dolor nec? Diam eu duis sociis. Dapibus porta! Proin, turpis nascetur et. Aenean tristique eu in elit dolor, montes sit nec, magna amet montes, hac diam ac, pellentesque duis sociis, est placerat? Montes ac, nunc aliquet ridiculus nisi? Dignissim. Et aliquet sed.\n\nAuctor mid, mauris placerat? Scelerisque amet a a facilisis porttitor aenean dolor, placerat dapibus, odio parturient scelerisque? In dis arcu nec mid ac in adipiscing ultricies, pulvinar purus dis. Nisi dis massa magnis, porta amet, scelerisque turpis etiam scelerisque porttitor ac dictumst, cras, enim? Placerat enim pulvinar turpis a cum! Aliquam? Urna ut facilisis diam diam lorem mattis ut, ac pid, sed pellentesque. Egestas nunc, lacus, tempor amet? Lacus, nunc dictumst, ac porttitor magna, nisi, montes scelerisque? Cum, rhoncus. Pid adipiscing porta dictumst porta amet dignissim purus, aliquet dolor non sagittis porta urna? Tortor egestas, ultricies elementum, placerat velit magnis lacus? Augue nunc? Ac et cras ut? Ac odio tortor lectus. Mattis adipiscing urna, scelerisque nec aenean adipiscing mid.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(11,'company_privacy_policy','Ac dapibus. Rhoncus integer sit aliquam a! Natoque? Lacus porttitor rhoncus, aliquam porttitor in risus turpis adipiscing! Integer, mus mattis sed enim ac velit proin est et ut, amet eros! Hac augue et vel ac sit duis facilisis purus tincidunt, porttitor eu a penatibus rhoncus platea et mauris rhoncus magnis rhoncus, montes? Et porttitor, urna, dolor, dapibus elementum porttitor aliquam.\n\nCras risus? Turpis, mus tincidunt vel dolor lectus pulvinar aliquam nascetur parturient nunc proin aenean tortor, augue aenean ac penatibus vut arcu. Augue, aenean dapibus in nec. In tempor turpis dictumst cursus, nec eros, elit non, ut integer magna. Augue placerat magnis facilisis platea ridiculus tincidunt et ut porttitor! Cursus odio, aliquet purus tristique vel tempor urna, vut enim.\n\nPorta habitasse scelerisque elementum adipiscing elit pulvinar? Cursus! Turpis! Massa ac elementum a, facilisis eu, sed ac porta massa sociis nascetur rhoncus sed, scelerisque habitasse aliquam? Velit adipiscing turpis, risus ut duis non integer rhoncus, placerat eu adipiscing, hac? Integer cursus porttitor rhoncus turpis lundium nisi, velit? Arcu tincidunt turpis, nunc integer turpis! Ridiculus enim natoque in, eros odio.\n\nScelerisque tempor dolor magnis natoque cras nascetur lorem, augue habitasse ac ut mid rhoncus? Montes tristique arcu, nisi integer? Augue? Adipiscing tempor parturient elementum nunc? Amet mid aliquam penatibus. Aliquam proin, parturient vel parturient dictumst? A porttitor rhoncus, a sit egestas massa tincidunt! Nunc purus. Hac ac! Enim placerat augue cursus augue sociis cum cras, pulvinar placerat nec platea.\n\nPenatibus et duis, urna. Massa cum porttitor elit porta, natoque etiam et turpis placerat lacus etiam scelerisque nunc, egestas, urna non tincidunt cursus odio urna tempor dictumst dignissim habitasse. Mus non et, nisi purus, pulvinar natoque in vel nascetur. Porttitor phasellus sed aenean eu quis? Nec vel, dignissim magna placerat turpis, ridiculus cum est auctor, sagittis, sit scelerisque duis.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(12,'company_note','Amet auctor, sed massa lacus phasellus turpis urna mauris dictumst, dapibus turpis? Sociis amet, mid aliquam, sagittis, risus, eros porta mid placerat eros in? Elementum porta ac pulvinar porttitor adipiscing, tristique porta pid dolor elementum? Eros, pulvinar amet auctor, urna enim amet magnis ultrices etiam? Dictumst ultrices velit eu tortor aliquet, rhoncus! Magnis porttitor. Vel parturient, ac, nascetur magnis tincidunt.\n\nQuis, pid. Lacus lorem scelerisque tortor phasellus, duis adipiscing nec mid mus purus placerat nunc porttitor placerat, risus odio pulvinar penatibus tincidunt, proin. Est tincidunt aliquam vel, ut scelerisque. Enim lorem magna tempor, auctor elit? Magnis lorem ut cursus, nunc nascetur! Est et odio nunc odio adipiscing amet nunc, ridiculus magnis egestas proin, montes nunc tristique tortor, ridiculus magna.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(13,'invoice_starting_number','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(14,'invoice_series','BOX',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(15,'invoice_due_days','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(16,'invoice_auto_approval','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(17,'invoice_issue_days_before_expire','14',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(18,'invoice_refund_logic','credit_note',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(19,'invoice_cn_series','CN-',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(20,'invoice_series_paid','PAID-',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(21,'issue_invoice_days_before_expire','7',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(22,'tax_enabled','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(23,'theme','boxbilling',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(24,'admin_theme','admin_default',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(25,'enable_wysiwyg','0',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(26,'nameserver_1','ns1.1freehosting.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(27,'nameserver_2','ns2.1freehosting.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(28,'nameserver_3','ns3.1freehosting.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(29,'nameserver_4','ns4.1freehosting.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(30,'funds_min_amount','10',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(31,'funds_max_amount','200',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(32,'invoice_overdue_invoked','2014-09-16T06:33:41-04:00',0,NULL,NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(33,'servicedomain_last_sync','2014-09-16T06:33:41-04:00',0,NULL,NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00'),
	(34,'last_cron_exec','2014-09-16T06:33:41-04:00',0,NULL,NULL,'2014-09-16T06:33:41-04:00','2014-09-16T06:33:41-04:00');

/*!40000 ALTER TABLE `setting` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table subscription
# ------------------------------------------------------------



# Dump of table support_helpdesk
# ------------------------------------------------------------

LOCK TABLES `support_helpdesk` WRITE;
/*!40000 ALTER TABLE `support_helpdesk` DISABLE KEYS */;

INSERT INTO `support_helpdesk` (`id`, `name`, `email`, `close_after`, `can_reopen`, `signature`, `created_at`, `updated_at`)
VALUES
	(1,'General','info@boxbilling.com',24,0,'It is always a pleasure to help.\nHave a Nice Day !','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `support_helpdesk` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table support_p_ticket
# ------------------------------------------------------------



# Dump of table support_p_ticket_message
# ------------------------------------------------------------



# Dump of table support_pr
# ------------------------------------------------------------

LOCK TABLES `support_pr` WRITE;
/*!40000 ALTER TABLE `support_pr` DISABLE KEYS */;

INSERT INTO `support_pr` (`id`, `support_pr_category_id`, `title`, `content`, `created_at`, `updated_at`)
VALUES
	(1,1,'Hello #1','Hello,\n\n\n\nThank you for using our services.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,'Hello #2','Greetings,\n\n\n\nThank you.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,2,'It was fixed','\nIt was fixed for your account. If you have any more questions or requests, please let us to know.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,2,'It was done as requested','\nIt\'s done as you have requested. Please let us to know if you have any further requests or questions.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,2,'Your website works fine','\nI have just checked your website and it works fine. Please check it from your end and let us to know if you still experience any problems.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(6,3,'Do you get any errors?','\nDo you get any errors and maybe you can copy/paste full error messages?','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(7,3,'Domain is not pointing to our server','\nYour domain is not pointing to our server. Please set our nameservers for your domain and give 24 hours until changes will apply worldwide.','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(8,3,'What is your domain and username?','\nWhat is your domain name and username?','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `support_pr` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table support_pr_category
# ------------------------------------------------------------

LOCK TABLES `support_pr_category` WRITE;
/*!40000 ALTER TABLE `support_pr_category` DISABLE KEYS */;

INSERT INTO `support_pr_category` (`id`, `title`, `created_at`, `updated_at`)
VALUES
	(1,'Greetings','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'General','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'Accounting','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `support_pr_category` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table support_ticket
# ------------------------------------------------------------

LOCK TABLES `support_ticket` WRITE;
/*!40000 ALTER TABLE `support_ticket` DISABLE KEYS */;

INSERT INTO `support_ticket` (`id`, `support_helpdesk_id`, `client_id`, `priority`, `subject`, `status`, `rel_type`, `rel_id`, `rel_task`, `rel_new_value`, `rel_status`, `created_at`, `updated_at`)
VALUES
	(1,1,1,100,'Regarding your new domain','closed',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2014-09-16T06:33:41-04:00');

/*!40000 ALTER TABLE `support_ticket` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table support_ticket_message
# ------------------------------------------------------------

LOCK TABLES `support_ticket_message` WRITE;
/*!40000 ALTER TABLE `support_ticket_message` DISABLE KEYS */;

INSERT INTO `support_ticket_message` (`id`, `support_ticket_id`, `client_id`, `admin_id`, `content`, `attachment`, `ip`, `created_at`, `updated_at`)
VALUES
	(1,1,NULL,1,'Hello,\n\nIt\'s done as you have requested. Please let us to know if you have any further requests or questions.\n\nSincerely Yours, Demo Administrator',NULL,'127.0.0.1','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `support_ticket_message` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table support_ticket_note
# ------------------------------------------------------------



# Dump of table tax
# ------------------------------------------------------------

LOCK TABLES `tax` WRITE;
/*!40000 ALTER TABLE `tax` DISABLE KEYS */;

INSERT INTO `tax` (`id`, `level`, `name`, `country`, `state`, `taxrate`, `created_at`, `updated_at`)
VALUES
	(1,NULL,'Global Tax',NULL,NULL,'5','2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `tax` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table tld
# ------------------------------------------------------------

LOCK TABLES `tld` WRITE;
/*!40000 ALTER TABLE `tld` DISABLE KEYS */;

INSERT INTO `tld` (`id`, `tld_registrar_id`, `tld`, `price_registration`, `price_renew`, `price_transfer`, `allow_register`, `allow_transfer`, `active`, `min_years`, `created_at`, `updated_at`)
VALUES
	(1,1,'.com',11.99,10.99,9.99,1,1,1,1,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,1,'.net',7.99,7.99,5.99,1,1,1,1,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `tld` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table tld_registrar
# ------------------------------------------------------------

LOCK TABLES `tld_registrar` WRITE;
/*!40000 ALTER TABLE `tld_registrar` DISABLE KEYS */;

INSERT INTO `tld_registrar` (`id`, `name`, `registrar`, `test_mode`, `config`)
VALUES
	(1,'Custom','Custom',0,NULL),
	(2,'Email','Email',0,NULL),
	(3,'Reseller Club','Resellerclub',0,NULL);

/*!40000 ALTER TABLE `tld_registrar` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table transaction
# ------------------------------------------------------------




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
