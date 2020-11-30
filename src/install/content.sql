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



# Dump of table admin_group
# ------------------------------------------------------------

LOCK TABLES `admin_group` WRITE;
/*!40000 ALTER TABLE `admin_group` DISABLE KEYS */;

INSERT INTO `admin_group` (`id`, `name`, `created_at`, `updated_at`)
VALUES
	(1,'Administrators','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'Support','2020-01-01 12:00:00','2020-01-01 12:00:00');

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



# Dump of table client_balance
# ------------------------------------------------------------



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
	(1,'US Dollar','USD',1,1.000000,'${{price}}','1','2020-01-01 12:00:00','2020-01-01 12:00:00');

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
	(4,'mod','branding','installed','1.0.0','{\"id\":\"branding\",\"type\":\"mod\",\"name\":\"Branding\",\"description\":\"BoxBilling branding module.\",\"homepage_url\":\"http:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/extensions.boxbilling.com\\/\",\"license\":\"http:\\/\\/www.boxbilling.com\\/license.txt\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}\n'),
	(5,'mod','redirect','installed','1.0.0','{\"id\":\"redirect\",\"type\":\"mod\",\"name\":\"Redirect\",\"description\":\"Manage redirects\",\"homepage_url\":\"https:\\/\\/github.com\\/boxbilling\\/\",\"author\":\"BoxBilling\",\"author_url\":\"http:\\/\\/www.boxbilling.com\",\"license\":\"GPL version 2 or later - http:\\/\\/www.gnu.org\\/licenses\\/old-licenses\\/gpl-2.0.html\",\"version\":\"1.0.0\",\"icon_url\":null,\"download_url\":null,\"project_url\":\"https:\\/\\/extensions.boxbilling.com\\/\",\"minimum_boxbilling_version\":null,\"maximum_boxbilling_version\":null}');

/*!40000 ALTER TABLE `extension` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table extension_meta
# ------------------------------------------------------------



# Dump of table form
# ------------------------------------------------------------



# Dump of table form_field
# ------------------------------------------------------------



# Dump of table forum
# ------------------------------------------------------------

LOCK TABLES `forum` WRITE;
/*!40000 ALTER TABLE `forum` DISABLE KEYS */;

INSERT INTO `forum` (`id`, `category`, `title`, `description`, `slug`, `status`, `priority`, `created_at`, `updated_at`)
VALUES
	(1,'General','Discussions Rules','Please read our forum rules before posting to our forums','forum-rules','active',1,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `forum` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table forum_topic
# ------------------------------------------------------------



# Dump of table forum_topic_message
# ------------------------------------------------------------



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
	(1,2,0,'How to contact support','Registered clients can contact our support team:\n------------------------------------------------------------\n\n* Login to clients area\n* Select **Support** menu item\n* Click **Submit new ticket**\n* Fill the form and press *Submit*\n\nGuests can contact our support team:\n------------------------------------------------------------\n\n* Use our contact form.\n* Fill the form and click *Submit*\n','how-to-contact-support','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,2,0,'How to place new order','To place new order, follow these steps:\n------------------------------------------------------------\n\n* Select our services at *Order* page.\n* Depending on selected product, you might need to provide additional information to complete order request.\n* Click \"Continue\" and your product/service is now in shopping cart.\n* If you have promo code, you can apply it and get discount.\n* Click on \"Checkout\" button to proceed with checkout process\n    * If you are already logged in, uou will be automaticaly redirected to new invoice\n    * If you are registerd client, you can provide your login details\n    * If you have never purchased any service from us, fill up client sign up form, and continue checkout\n* Choose payment method to pay for invoice. List of all avalable payment methods will be listed below invoice details.\n* Choose payment method\n* You will be redirected to payment gateway page\n* After successfull payment, You will be redirected back to invoice page.\n* Depending on selected services your order will be reviewed and activated by our staff members.\n* After you receive confirmation email about order activation you are able to manage your services.\n','how-to-place-new-order','active','2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,2,0,'Example article','Example article heading\n------------------------------------------------------------\n\nCursus, parturient porta dis sit? Habitasse non, sociis porttitor, sagittis dapibus scelerisque? Pid, porttitor integer, montes. Hac, in? Arcu nunc integer nascetur dis nisi. In, sed a amet? Adipiscing odio mauris mauris, porta, integer, adipiscing habitasse, elementum phasellus, turpis in? Quis magna placerat eu, cursus urna mattis egestas, a ac massa turpis mus et odio pid in, urna dapibus ridiculus in turpis cursus ac a urna magna purus etiam ac nisi porttitor! Auctor est? In adipiscing, hac platea augue vut, hac est cum sagittis! Montes nascetur pulvinar tristique porta platea? Magnis vel etiam nisi augue auctor sit pulvinar! Aliquet rhoncus, elit porta? Magnis pulvinar eu turpis purus sociis a augue? Sit, nascetur! Mattis nisi, penatibus ac ac natoque auctor turpis.\n\nExample article heading\n------------------------------------------------------------\n\nUt diam cursus, elit pulvinar, habitasse purus? Enim. Urna? Velit arcu, rhoncus sociis sed, et, ultrices nascetur lacus vut purus tempor a. Vel? Sagittis integer scelerisque, dapibus lectus mid, magnis, augue duis velit etiam tortor! Eros, a et phasellus est ultricies integer elementum in, tempor sed parturient. Dictumst rhoncus, ut sed sagittis facilisis? In, proin? Urna augue in sociis enim dignissim! Velit magna tincidunt ac. Nunc, vel auctor porta enim integer. Phasellus amet eu. Tristique lundium arcu! In? Massa penatibus arcu, rhoncus augue ut pid pulvinar, porttitor, porta, et! A sit odio, proin natoque ultrices cras cras magna porttitor! Ultrices sed magna in! Porttitor nunc, tincidunt nec, amet integer aenean. Tincidunt, placerat nec dolor parturient et ac pulvinar a.\n','example-article-slug','active','2020-01-01 12:00:00','2020-01-01 12:00:00');

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
	(1,'Custom','Custom',NULL,1,1,1,0,NULL),
	(2,'PayPal','PayPalEmail',NULL,0,1,1,0,NULL),
	(3,'AlertPay','AlertPay',NULL,0,1,1,0,NULL);

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
	(3,1,'BoxBilling is customizable','* You can create your own simple or advanced hooks on BoxBilling events. For example, send notification via sms when new client signs up.\n* Create custom theme for your client interface\n','boxbilling-is-customizable','active',NULL,NULL,NULL,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `post` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product
# ------------------------------------------------------------

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;

INSERT INTO `product` (`id`, `product_category_id`, `product_payment_id`, `form_id`, `title`, `slug`, `description`, `unit`, `active`, `status`, `hidden`, `is_addon`, `setup`, `addons`, `icon_url`, `allow_quantity_select`, `stock_control`, `quantity_in_stock`, `plugin`, `plugin_config`, `upgrades`, `priority`, `config`, `created_at`, `updated_at`, `type`)
VALUES
	(1,1,NULL,NULL,'Domains registration and transfer','domain-checker',NULL,'product',1,'enabled',0,0,'after_payment',NULL,NULL,0,0,0,NULL,NULL,NULL,1,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00','domain');

/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product_category
# ------------------------------------------------------------

LOCK TABLES `product_category` WRITE;
/*!40000 ALTER TABLE `product_category` DISABLE KEYS */;

INSERT INTO `product_category` (`id`, `title`, `description`, `icon_url`, `created_at`, `updated_at`)
VALUES
	(1,'Default category',NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `product_category` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table product_payment
# ------------------------------------------------------------



# Dump of table promo
# ------------------------------------------------------------



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



# Dump of table service_hosting_server
# ------------------------------------------------------------



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
	(1,'last_patch','17',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(2,'company_name','Company Name',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(3,'company_email','company@email.com',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(4,'company_signature','BoxBilling.com - Client Management, Invoice and Support Software',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(5,'company_logo','/bb-themes/boxbilling/assets/images/logo.png',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(6,'company_address_1','Demo address line 1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(7,'company_address_2','Demo address line 2',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(8,'company_address_3','Demo address line 3',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(9,'company_tel','+123 456 12345',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(10,'company_tos','Sit ridiculus nascetur porta purus tortor, augue natoque, pulvinar integer nisi mattis dignissim mus, elementum nascetur, augue etiam. Mus mus tortor? A mauris habitasse dictumst, scelerisque, dis nec pulvinar magnis velit, integer, nisi, aliquet, elit phasellus? Parturient odio purus tristique porttitor augue diam pulvinar magna ac lacus in. Augue tincidunt sociis ultrices parturient aliquet dapibus sit. Pulvinar mauris platea in amet penatibus augue ut non ridiculus, nunc lundium. Duis dapibus a mid proin pellentesque lundium vut mauris egestas dolor nec? Diam eu duis sociis. Dapibus porta! Proin, turpis nascetur et. Aenean tristique eu in elit dolor, montes sit nec, magna amet montes, hac diam ac, pellentesque duis sociis, est placerat? Montes ac, nunc aliquet ridiculus nisi? Dignissim. Et aliquet sed.\n\nAuctor mid, mauris placerat? Scelerisque amet a a facilisis porttitor aenean dolor, placerat dapibus, odio parturient scelerisque? In dis arcu nec mid ac in adipiscing ultricies, pulvinar purus dis. Nisi dis massa magnis, porta amet, scelerisque turpis etiam scelerisque porttitor ac dictumst, cras, enim? Placerat enim pulvinar turpis a cum! Aliquam? Urna ut facilisis diam diam lorem mattis ut, ac pid, sed pellentesque. Egestas nunc, lacus, tempor amet? Lacus, nunc dictumst, ac porttitor magna, nisi, montes scelerisque? Cum, rhoncus. Pid adipiscing porta dictumst porta amet dignissim purus, aliquet dolor non sagittis porta urna? Tortor egestas, ultricies elementum, placerat velit magnis lacus? Augue nunc? Ac et cras ut? Ac odio tortor lectus. Mattis adipiscing urna, scelerisque nec aenean adipiscing mid.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(11,'company_privacy_policy','Ac dapibus. Rhoncus integer sit aliquam a! Natoque? Lacus porttitor rhoncus, aliquam porttitor in risus turpis adipiscing! Integer, mus mattis sed enim ac velit proin est et ut, amet eros! Hac augue et vel ac sit duis facilisis purus tincidunt, porttitor eu a penatibus rhoncus platea et mauris rhoncus magnis rhoncus, montes? Et porttitor, urna, dolor, dapibus elementum porttitor aliquam.\n\nCras risus? Turpis, mus tincidunt vel dolor lectus pulvinar aliquam nascetur parturient nunc proin aenean tortor, augue aenean ac penatibus vut arcu. Augue, aenean dapibus in nec. In tempor turpis dictumst cursus, nec eros, elit non, ut integer magna. Augue placerat magnis facilisis platea ridiculus tincidunt et ut porttitor! Cursus odio, aliquet purus tristique vel tempor urna, vut enim.\n\nPorta habitasse scelerisque elementum adipiscing elit pulvinar? Cursus! Turpis! Massa ac elementum a, facilisis eu, sed ac porta massa sociis nascetur rhoncus sed, scelerisque habitasse aliquam? Velit adipiscing turpis, risus ut duis non integer rhoncus, placerat eu adipiscing, hac? Integer cursus porttitor rhoncus turpis lundium nisi, velit? Arcu tincidunt turpis, nunc integer turpis! Ridiculus enim natoque in, eros odio.\n\nScelerisque tempor dolor magnis natoque cras nascetur lorem, augue habitasse ac ut mid rhoncus? Montes tristique arcu, nisi integer? Augue? Adipiscing tempor parturient elementum nunc? Amet mid aliquam penatibus. Aliquam proin, parturient vel parturient dictumst? A porttitor rhoncus, a sit egestas massa tincidunt! Nunc purus. Hac ac! Enim placerat augue cursus augue sociis cum cras, pulvinar placerat nec platea.\n\nPenatibus et duis, urna. Massa cum porttitor elit porta, natoque etiam et turpis placerat lacus etiam scelerisque nunc, egestas, urna non tincidunt cursus odio urna tempor dictumst dignissim habitasse. Mus non et, nisi purus, pulvinar natoque in vel nascetur. Porttitor phasellus sed aenean eu quis? Nec vel, dignissim magna placerat turpis, ridiculus cum est auctor, sagittis, sit scelerisque duis.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(12,'company_note','Amet auctor, sed massa lacus phasellus turpis urna mauris dictumst, dapibus turpis? Sociis amet, mid aliquam, sagittis, risus, eros porta mid placerat eros in? Elementum porta ac pulvinar porttitor adipiscing, tristique porta pid dolor elementum? Eros, pulvinar amet auctor, urna enim amet magnis ultrices etiam? Dictumst ultrices velit eu tortor aliquet, rhoncus! Magnis porttitor. Vel parturient, ac, nascetur magnis tincidunt.\n\nQuis, pid. Lacus lorem scelerisque tortor phasellus, duis adipiscing nec mid mus purus placerat nunc porttitor placerat, risus odio pulvinar penatibus tincidunt, proin. Est tincidunt aliquam vel, ut scelerisque. Enim lorem magna tempor, auctor elit? Magnis lorem ut cursus, nunc nascetur! Est et odio nunc odio adipiscing amet nunc, ridiculus magnis egestas proin, montes nunc tristique tortor, ridiculus magna.\n',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(13,'invoice_series','BOX',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(14,'invoice_due_days','5',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(15,'invoice_auto_approval','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(16,'invoice_issue_days_before_expire','14',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(17,'theme','boxbilling',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(18,'issue_invoice_days_before_expire','7',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(19,'invoice_refund_logic','credit_note',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(20,'invoice_cn_series','CN-',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(21,'invoice_cn_starting_number','1',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(22,'nameserver_1',NULL,0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(23,'nameserver_2',NULL,0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(24,'nameserver_3',NULL,0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(25,'nameserver_4',NULL,0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(26,'funds_min_amount','10',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00'),
	(27,'funds_max_amount','200',0,NULL,NULL,'2020-01-01 12:00:00','2020-01-01 12:00:00');

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
	(1,'General','info@yourdomain.com',24,0,'It is always a pleasure to help.\nHave a Nice Day !','2020-01-01 12:00:00','2020-01-01 12:00:00');

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



# Dump of table support_ticket_message
# ------------------------------------------------------------



# Dump of table support_ticket_note
# ------------------------------------------------------------



# Dump of table tax
# ------------------------------------------------------------



# Dump of table tld
# ------------------------------------------------------------

LOCK TABLES `tld` WRITE;
/*!40000 ALTER TABLE `tld` DISABLE KEYS */;

INSERT INTO `tld` (`id`, `tld_registrar_id`, `tld`, `price_registration`, `price_renew`, `price_transfer`, `allow_register`, `allow_transfer`, `active`, `min_years`, `created_at`, `updated_at`)
VALUES
	(1,1,'.com',11.99,11.99,11.99,1,1,1,1,'2020-01-01 12:00:00','2020-01-01 12:00:00');

/*!40000 ALTER TABLE `tld` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table tld_registrar
# ------------------------------------------------------------

LOCK TABLES `tld_registrar` WRITE;
/*!40000 ALTER TABLE `tld_registrar` DISABLE KEYS */;

INSERT INTO `tld_registrar` (`id`, `name`, `registrar`, `test_mode`, `config`)
VALUES
	(1,'Custom','Custom',0,NULL),
	(2,'Reseller Club','Resellerclub',0,NULL),
	(3,'Internet.bs','Internetbs',0,NULL);

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
