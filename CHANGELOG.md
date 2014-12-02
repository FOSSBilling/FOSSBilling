Release 4.12 (2014-12-02)
------------------------------------------------------------------------------

* Client password hashing updated (php password_* functions are being used)
* Added mass delete functionality in admin area
* Ability to set promotional code for client groups
* Ability for administrator to edit order config
* ResellerClub adapter updated
* Pagination fixed
* Bug fixes

Release 4.11.3 (2014-11-03)
------------------------------------------------------------------------------

* BoxBilling going open source
* Removed Doctrine in favor of RedBean and PDO
* URL changes. Now urls are index.php?_url=/slug instead of index.php/slug
* Bug fixes
* Requires PHP >5.3
* Changed modules logic

Release 3.3.20 (2013-03-20)
------------------------------------------------------------------------------

* Service license unable to order if not configured issue fix
* Select first payment option for cart checkout as default
* IDR currency rate update fix
* PayPal IPN fix. Was not able to add amount to balance if currency was not USD

Release 2.12.5 (2012-12-05)
------------------------------------------------------------------------------

* Fixes issue when domain order is activated for 2 years instead of 1
* Approved invoice issue fix on Windows servers
* Show transactions list for client
* Enable multisite support by determining hostname and config file
* Do not show payment gateway select box if cart total is 0

Release 2.11.28 (2012-11-28)
------------------------------------------------------------------------------

* All modules settings moved to global settings page
* Ability to edit countries list in system settings page
* YouHosting API support. Now you can sell YouHosting services with BoxBilling
* BoxBilling theme can now have settings page. Settings are globally available in client area theme
* New theme filters:
   * asset_url - {{ 'style.css' | asset_url | stylesheet_tag }}
   * img_tag - {{ company.logo_url | img_tag }}
   * script_tag - {{ 'jquery.min.js' | asset_url | script_tag }}
   * stylesheet_tag - {{ 'style.css' | asset_url | stylesheet_tag }}
   * money - {{ '40' | money }}
   * money_without_currency - {{ '40' | money_without_currency }}
   * money_convert - {{ '40' | money_convert }}
   * money_convert_without_currency - {{ '40' | money_convert_without_currency }}
* Updating Twig to 1.10.3
* Cron option: Enable web based cron. This method requires that site has visitors and special theme tag support
* Major admin area theme update. Using fluid layout. Added breadcrumbs
* News module updates    
* Ability to change payment gateway in invoice payment page
* Immediately redirect to payment gateway after checkout if payment gateway was selected
* Defautl theme update: Show client login/signup form before checkout button in shopping cart
* Search can be made by order meta keys   
* Added new database table *client_order_meta* to store custom order data
* new statistic method to get number of sales by country
* new statistic method to get number of clients in country
* Do not use ajax when loading statistics in admin dashboard    
* Show Last month statistics in dashboard    
* Render mod_page_login.phtml instead of mod_client_login.phtml template if template exists
* Allow create any template mod_client_*.phtml to be accessed by client only
* Added redirects module. Usefull to redirect deprecated URLs to new ones
* Simplified payment gateway logic. Now it is a simple file, no need to extend any class.
* New event hooks:  
    * onBeforeAdminExtensionConfigSave
    * onAfterAdminExtensionConfigSave
    * onBeforeProductAddedToCart
    * onAfterProductAddedToCart
* Removed hostinger branding
* Option to enable/disable ability for client to change his email
* Ability to require email confirmation in welcome email
* Ability to define required fields for client registration form    
* Ability to disable new client registrations
* Invoices are not generated for 0 amount orders
* Performance and multiple bug fixes

Release 2.9.14 (2012-09-14)
------------------------------------------------------------------------------

* Added new field for service license - checked_at
* Support tickets canned responses are grouped
* Support settings option: auto responder and delay message 
* Preview ticket notes in tickets listing page
* Ticket page updates in admin area
* Ability to generate email templates for enabled extensions before first event occurrence
* Ability to define which IPs are allowed to login to admin area
* Redesigned settings page. All configurable extensions are listed in this page.
* Simplified payment gateway adapters transaction processing logic. 
  Now you can use API inside payment gateway method **processTransaction**
  Requires new parameter (new_transaction_process_logic) to be added to settings table to enable this option
* Option to skip calling event hook in order suspend and cancel actions

Release 2.9.10 (2012-09-10)
------------------------------------------------------------------------------

* Search clients by company name in clients listing page 
* Ability to define staff member permissions
* New parameter in bb-config.php file BB_LOCALE_TIME_FORMAT - to define localized time format
* Add: New filter to display translatable date with time: bb_datetime
* Update: Search clients by company name in clients listing page
* Fix: Use PDO::MYSQL_ATTR_USE_BUFFERED_QUERY for PDO connection
* Fix: Order created from admin area do not match clients currency
* Update: If order total after discount is 0 and product activation is set to after payment, activate order after checkout
* Fix: Show total amount in print invoice page
* Update: onAfterAdminClientPasswordChange receives password parameter
* If order total after discount is 0 and product activation is set to after payment, activate order after checkout
* Simplified installer app. Now works smoothly with nginx
* Admin navigation is displayed according to staff member permissions
* Currency converter in currency management page
* No country detected flag fix
* Print invoice total issue fix
* Do not show help desks grouping in admin area support tickets listing
* Forums has new attribute - category 
* Forum messages can now be rated with points
* RedBean update to 3.2.3

Release 2.8.2 (2012-08-02)
------------------------------------------------------------------------------

* Email template subject not updating fix
* Ability to import existing hosting accounts without activation from admin area
* Ability to automatically setup EU VAT rules from admin area
* Invoice not marked as paid if client balance is equal to amount of invoice
* Admin area client listing update. Shows clients country.
* Added Queue manager. Gives ability to execute long running tasks in background
* Added Mass mailer tool to send email to filtered list of clients.
* Added Centova cast module to sell shout cast servers.
* Added file editor to edit any file on BoxBilling.
* Show Admin API key in admin profile
* New fields in client profile: referred_by, company_vat, company_number, type
* New fields for invoice: seller_company_vat, seller_company_number, buyer_phone_cc, buyer_company_vat, buyer_company_number, text_1, text_2
* Added Tanzania to country list
* Transfer code is not mandatory in domain order form
* Country flag is visible in admin area clients listing page
* Added country translations to default locale pot

Release 2.7.4 (2012-07-04)
------------------------------------------------------------------------------

* Update script issue fix when did not update from 2.4.30
* Payza payment gateway
* Bug fix when shopping cart did not generate subscribable invoices
* 2Checkout gateway updates
* Does not list hidden products by default in guest/product/get_list call
* Ability to remove order with addons.

Release 2.7.2 (2012-07-02)
------------------------------------------------------------------------------

* Pass hosting account password to email template as variable
* Whm server manager updates
* Set default currency if clients currency is not defined when preparing invoice for client
* Ability to call custom event hook on invoice item activation. New invoice item type - hook_call
* Parameter **execute** in admin/invoice/mark_as_paid now can activate related services immediately
* Order renewal logic parameter. Can select how renewal date is calculated
* added new field in client database to define authorization type
* admin/client/create password is not mandatory
* admin/client/get can now find client by email  
* Invoices duplicate listing issue fix
* Added Akismet checking for forum posts in spamChecker module
* Support for php-gettext if gettext extension is not installed on server.
* AliPay payment gateway updates
* Added Thank You page. Clients can be redirected after payment instead of invoice. 

Release 2.6.22 (2012-06-22)
------------------------------------------------------------------------------

* Invoice tax calculation bug fix
* Whm/Cpanel invalid body response issue updates
* Fixed order event hooks
* Can not create currency issue fix. Change currency price format from %price% to {{price}}. Solving an for servers where mod_security is enabled. 
* Fix for issue: Email templates are cached and does not auto refresh if BB_DEBUG mode is false
* Add message to notification center after client makes post to forum topic. Notification center extension must be enabled.

Release 2.6.17 (2012-06-17)
------------------------------------------------------------------------------

* New SolusVM VPS product type. Ability to import clients and existing servers from SolusVM master server. Full control from admin and client area.
* New product type BoxBiling license. Ability to sell BoxBilling licenses from any BoxBilling installation. Enable 
* Email templates are generated on first event occurrence. Until then it can not be managed.
* Ability to filter orders by invoice option: issue invoice manually or automatically.
* Ability to preview email templates. Old email templates are deprecated.
* Ability to place new order for clients from admin area 
* Ability to change currency price format
* Deprecated bb-library/Hook bindings. Now event hooks are binded from modules Service.php class
* php mcrypt extension is now mandatory
* Email settings are now stored in encrypted format
* Added ability to reset email template to default
* Moved email sending settings from *general settings* to *email templates* menu
* New dashboard widget to show active orders count grouped by products
* Updated Twig to 1.8.2 version
* Added ability to disable/enable automatic invoice issuing for orders
* SpamChecker is a module. Google recapthca and stopforumspam.com database checks can be simply enabled or disabled as any other module
* All extensions can call other extension event hooks
* All extensions can hook into cron job event
* Added new API methods **extension_config_get** and **extension_config_save** to store encrypted extension configuration data
* Admin layout contains more blocks. Gives more freedom for extensions developers. 
* Admin area dashboard statistics links now filters data for today and yesterday
* Ability to load custom listing template file for order index page
* Check if template file exists function for guest API guest.system_template_exists({"file":"mod_index_dashboard.phtml"})
* Ability to create admin account if none exists from admin area interface instead of login form.
* Default sort order for new products
* Products grouped by type can now be ordered on *slider* type form 
* Bug fix when installed in subfolder named same as one of the modules
* Client profile management page updates - split to sections
* Adding dotTk domain registrar to official version
* Adding Interkassa payment gateway
* Email registrar bug fixes
* Internetbs registrar bug fixes
* Ability to create custom products. These products can now be enabled and disabled as an extension
* Added loader spinner to admin area login form
* Added weekly pricing option for products
* Currency rate is saved in invoice on payment event
* Google Recaptcha now uses https
* Ability to add/remove forum topic to favorites
* Ability to subscribe/unsubscribe to forum message notifications
* Adding new field to extensions meta table - client_id

Release v2.4.30 (2012-04-30)
------------------------------------------------------------------------------

* Fixed bug when admin was not able to update order creation date in admin area
* Promo code can be recurrent or applied to first order only
* Promo code validation once per client
* PayPal gateway validates non latin characters IPNs correctly
* Ability to change how many times promo code was used
* Added ability for admin to issue new invoice for order
* Setup price promo code issue fix.
* More translations made available
* Show ticket notes counter in list and ticket
* Order renewal invoice due date is same a order expiration date
* Added version param to js and css files to avoid clear cache on update
* https links updates
* https://github.com/boxbilling/BoxBilling/issues/115 fix
* Password issue fix: https://github.com/boxbilling/BoxBilling/issues/114
* Including http://redbeanphp.com/ ORM in bb-library/rb.php For extensions as a helper 
* Simplifie nginx server support. Routing includes only 2 simple rules
* Adding new core module mod_api. Api is now accessible from yourdomain.com/api/role/module/method. bb-api is deprecated and will be removed in 2.6 version
* Gravatar url uses ssl if BoxBilling is on https
* Selectable refunds logic: generate refund invoice with paid invoice numbering or generate credit note
* New invoice status: "canceled". Invoice with canceled status are not counted into income.
* Invoice mark as paid with credits fix.
* Show task status and charge icons for invoice items for approved invoices
* Ability to remove clients balance
* Adding exception to PayPal gateway for HUF currency.
* Ability to install module from extensions site
* Ability to filter invoices by paid_at date in url param
* Adding config field for license service for more possibilities
* https://github.com/boxbilling/BoxBilling/issues/113 update
* Mail issue fix when port number was not recognized
* Stats module update: Income is counted on paid_at date not created_at date
* onBeforeClientProfileUpdate Event updates. Now receives currently logged in client data.
* Invoice API returns more information
* Display currency selector only if more than one currency is available
* Hosting account password is generated on order activation action
* Removing favicon to avoid overwriting on update
* Added zip code management to invoice client credentials
* Whm/Cpanel server manager update. Do not try to exceute command again if response failed
* Translations fix
* Typo fix in order management page
* WebToPay payment adapter updates
* Admin area dashboard updates. Clickable titles.
* Adding missing email templates for closing ticket event
* Client area popup close button style fix
