<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Start Core Stuff
        Schema::create('activity_admin_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->nullable(true)->index();
            $table->string('ip',45)->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('activity_client_email', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->index();
            $table->string('sender')->nullable(true)->default(null);
            $table->text('recipients')->nullable(true)->default(null);
            $table->string('subject')->nullable(true)->default(null);
            $table->text('content_html')->nullable(true)->default(null);
            $table->text('content_text')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('activity_client_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->index();
            $table->string('ip',45)->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('activity_system', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('priority')->nullable(true)->default(null);
            $table->bigInteger('client_id')->nullable(true)->index();
            $table->bigInteger('admin_id')->nullable(true)->index();
            $table->text('message')->nullable(true)->default(null);
            $table->string('ip',45)->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('admin', function (Blueprint $table) {
            $table->id();
            $table->string('role',30)->default('staff')->nullable(true);
            $table->bigInteger('admin_group_id')->nullable(true)->default(1)->index();
            $table->string('email')->nullable(true)->default(null)->unique();
            $table->string('pass')->nullable(true)->default(null);
            $table->string('salt')->nullable(true)->default(null);
            $table->string('name')->nullable(true)->default(null);
            $table->string('signature')->nullable(true)->default(null);
            $table->boolean('protected')->nullable(true)->default(0);
            $table->string('status',30)->nullable(true)->default('active');
            $table->string('api_token',128)->nullable(true)->default(null);
            $table->text('permissions')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('admin_group', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default(null)->nullable(true);
            $table->timestamps();
        });
        Schema::create('api_request', function (Blueprint $table) {
            $table->id();
            $table->string('ip',45)->nullable(true)->default(null);
            $table->text('request')->nullable(true)->default(null);
            $table->timestamps();
        });
            // Core
        Schema::create('cart', function (Blueprint $table) {
            $table->id();
            $table->string('session_id',32)->nullable(true)->default(null)->index();
            $table->bigInteger('currency_id')->nullable(true)->default(null)->index();
            $table->bigInteger('promo_id')->nullable(true)->default(null)->index();
            $table->timestamps();
        });
        Schema::create('cart_product', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cart_id')->nullable(true)->index();
            $table->bigInteger('product_id')->nullable(true)->index();
            $table->text('config')->nullable(true)->default(null);
        });
            // Client
        Schema::create('client', function (Blueprint $table) {
            $table->id();
            $table->string('aid')->nullable(true)->default(null)->index(); // Alternative id for foreign systems
            $table->bigInteger('client_group_id')->nullable(true)->default(null)->index();
            $table->string('role',30)->nullable(false)->default('client');
            $table->string('auth_type')->nullable(true)->default(null);
            $table->string('email')->nullable(true)->default(null)->unique();
            $table->string('pass')->nullable(true)->default(null);
            $table->string('salt')->nullable(true)->default(null);
            $table->string('status',30)->nullable(true)->default('active');
            $table->boolean('email_approved')->nullable(true)->default(null);
            $table->boolean('tax_exempt')->nullable(true)->default(0);
            $table->string('type',100)->nullable(true)->default(null);
            $table->string('first_name',100)->nullable(true)->default(null);
            $table->string('last_name',100)->nullable(true)->default(null);
            $table->string('gender',20)->nullable(true)->default(null);
            $table->date('birthday')->nullable(true)->default(null);
            $table->string('phone_cc',10)->nullable(true)->default(null);
            $table->string('phone',100)->nullable(true)->default(null);
            $table->string('company',100)->nullable(true)->default(null);
            $table->string('company_vat',100)->nullable(true)->default(null);
            $table->string('company_number')->nullable(true)->default(null);
            $table->string('address_1',100)->nullable(true)->default(null);
            $table->string('address_2',100)->nullable(true)->default(null);
            $table->string('city',100)->nullable(true)->default(null);
            $table->string('state',100)->nullable(true)->default(null);
            $table->string('postcode',100)->nullable(true)->default(null);
            $table->string('country',100)->nullable(true)->default(null);
            $table->string('document_type',100)->nullable(true)->default(null);
            $table->string('document_nr',20)->nullable(true)->default(null);
            $table->text('notes')->nullable(true)->default(null);
            $table->string('currency',10)->nullable(true)->default(null);
            $table->string('lang',10)->nullable(true)->default(null);
            $table->string('ip',45)->nullable(true)->default(null);
            $table->string('api_token',128)->nullable(true)->default(null);
            $table->string('referred_by')->nullable(true)->default(null);
            $table->text('custom_1')->nullable(true)->default(null);
            $table->text('custom_2')->nullable(true)->default(null);
            $table->text('custom_3')->nullable(true)->default(null);
            $table->text('custom_4')->nullable(true)->default(null);
            $table->text('custom_5')->nullable(true)->default(null);
            $table->text('custom_6')->nullable(true)->default(null);
            $table->text('custom_7')->nullable(true)->default(null);
            $table->text('custom_8')->nullable(true)->default(null);
            $table->text('custom_9')->nullable(true)->default(null);
            $table->text('custom_10')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('client_balance', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->index();
            $table->string('type',100)->nullable(true)->default(null);
            $table->string('rel_id',20)->nullable(true)->default(null);
            $table->decimal('amount',18,2)->nullable(true)->default('0.00');
            $table->text('description')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('client_group', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('client_order', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->bigInteger('product_id')->nullable(true)->default(null)->index();
            $table->bigInteger('form_id')->nullable(true)->default(null)->index();
            $table->bigInteger('promo_id')->nullable(true)->default(null)->index();
            $table->boolean('promo_recurring')->nullable(true)->default(null);
            $table->bigInteger('promo_used')->nullable(true)->default(null);
            $table->string('group_id')->nullable(true)->default(null);
            $table->boolean('group_master')->nullable(true)->default(0);
            $table->string('title')->nullable(true)->default(null);
            $table->string('currency',20)->nullable(true)->default(null);
            $table->bigInteger('unpaid_invoice_id')->nullable(true)->default(null);
            $table->bigInteger('service_id')->nullable(true)->default(null);
            $table->string('service_type',100)->nullable(true)->default(null);
            $table->string('period',20)->nullable(true)->default(null);
            $table->bigInteger('quantity')->nullable(true)->default(1);
            $table->string('unit',100)->nullable(true)->default(null);
            $table->double('price',18,2)->nullable(false)->default(null);
            $table->double('discount',18,2)->nullable(false)->default(null);
            $table->string('status',50)->nullable(true)->default(null);
            $table->string('reason')->nullable(true)->default(null);
            $table->text('notes')->nullable(true)->default(null);
            $table->text('config')->nullable(true)->default(null);
            $table->string('referred_by')->nullable(true)->default(null);
            $table->datetime('expires_at')->nullable(false)->default(null);
            $table->datetime('activated_at')->nullable(false)->default(null);
            $table->datetime('suspended_at')->nullable(false)->default(null);
            $table->datetime('unsuspended_at')->nullable(false)->default(null);
            $table->datetime('canceled_at')->nullable(false)->default(null);
            $table->timestamps();
        });
        Schema::create('client_order_meta', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_order_id')->nullable(true)->default(null)->index();
            $table->string('name')->nullable(true)->default(null);
            $table->text('value')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('client_order_status', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_order_id')->nullable(true)->default(null)->index();
            $table->string('status',20)->nullable(true)->default(null);
            $table->text('notes')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('client_password_reset', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->string('hash',100)->nullable(true)->default(null);
            $table->string('ip',45)->nullable(true)->default(null);
            $table->timestamps();
        });
            // Core
        Schema::create('currency', function (Blueprint $table) {
            $table->id();
            $table->string('title',50)->nullable(true)->default(null);
            $table->string('code',3)->nullable(true)->default(null);
            $table->boolean('is_default')->nullable(true)->default(0);
            $table->decimal('conversion_rate',13,6)->nullable(true)->default('1.000000');
            $table->string('format',30)->nullable(true)->default(null);
            $table->string('price_format',50)->nullable(true)->default('1');
            $table->timestamps();
        });
        Schema::create('email_template', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->nullable(true)->default(null)->unique();
            $table->string('category',30)->nullable(true)->default(null);
            $table->boolean('enabled')->nullable(true)->default(1);
            $table->string('subject',0)->nullable(true)->default(null);
            $table->text('content')->nullable(true)->default(null);
            $table->text('description')->nullable(true)->default(null);
            $table->text('vars')->nullable(true)->default(null);
            $table->timestamps();
        });
            // Extensions
        Schema::create('extension', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable(true)->default(null);
            $table->string('name')->nullable(true)->default(null);
            $table->string('status',100)->nullable(true)->default(null);
            $table->string('version',100)->nullable(true)->default(null);
            $table->text('manifest')->nullable(true)->default(null);
        });
        Schema::create('extension_meta', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->string('extension')->nullable(true)->default(null);
            $table->string('rel_type')->nullable(true)->default(null);
            $table->string('rel_id')->nullable(true)->default(null);
            $table->string('meta_key')->nullable(true)->default(null);
            $table->longtext('meta_value')->nullable(true)->default(null);
            $table->timestamps();
        });
            // Forms
        Schema::create('form', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(true)->default(null);
            $table->text('style')->nullable(true)->default(null);
            $table->timestamps();
        });
        Schema::create('form_field', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('form_id')->nullable(true)->default(null)->index();
            $table->string('name')->nullable(true)->default(null);
            $table->string('label')->nullable(true)->default(null);
            $table->boolean('hide_label')->nullable(true)->default(null);
            $table->string('type')->nullable(true)->default(null);
            $table->string('default_value')->nullable(true)->default(null);
            $table->boolean('required')->nullable(true)->default(null);
            $table->boolean('hidden')->nullable(true)->default(null);
            $table->boolean('readonly')->nullable(true)->default(null);
            $table->boolean('is_unique')->nullable(true)->default(null);
            $table->string('prefix')->nullable(true)->default(null);
            $table->string('suffix')->nullable(true)->default(null);
            $table->text('options')->nullable(true)->default(null);
            $table->string('show_initial')->nullable(true)->default(null);
            $table->string('show_middle')->nullable(true)->default(null);
            $table->string('show_prefix')->nullable(true)->default(null);
            $table->string('show_suffix')->nullable(true)->default(null);
            $table->integer('text_size')->nullable(true)->default(null);
            $table->timestamps();
        });
            // Forum
        Schema::create('forum', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable(true)->default(null);
            $table->text('title')->nullable(true)->default(null);
            $table->text('description')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->string('status',100)->nullable(true)->default(null);
            $table->integer('priority')->nullable(true)->default(null); // This should be Size 11
            $table->timestamps();
        });
        Schema::create('forum_topic', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('forum_id')->nullable(true)->default(null)->index();
            $table->text('title')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->string('status',100)->nullable(true)->default(null);
            $table->boolean('sticky')->nullable(true)->default('0');
            $table->integer('views')->nullable(true)->default(null); // This should be Size 11
            $table->timestamps();
        });
        Schema::create('forum_topic_message', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('forum_topic_id')->nullable(true)->default(null)->index();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->bigInteger('admin_id')->nullable(true)->default(null)->index();
            $table->text('message')->nullable(true)->default(null);
            $table->string('ip',45)->nullable(true)->default(null);
            $table->string('points')->nullable(true)->default(null);
            $table->timestamps();
        });
            // Core
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->string('serie',50)->nullable(true)->default(null);
            $table->string('nr')->nullable(true)->default(null);
            $table->string('hash')->nullable(true)->default(null)->unique();
            $table->string('currency',25)->nullable(true)->default(null);
            $table->decimal('currency_rate',13,6)->nullable(true)->default(null);
            $table->double('credit',18,2)->nullable(true)->default(null);
            $table->double('base_income',18,2)->nullable(true)->default(null);
            $table->double('base_refund',18,2)->nullable(true)->default(null);
            $table->double('refund',18,2)->nullable(true)->default(null);
            $table->text('notes')->nullable(true)->default(null);
            $table->text('text_1')->nullable(true)->default(null);
            $table->text('text_2')->nullable(true)->default(null);
            $table->string('status',50)->nullable(true)->default('unpaid');
            $table->string('seller_company')->nullable(true)->default(null);
            $table->string('seller_company_vat')->nullable(true)->default(null);
            $table->string('seller_company_number')->nullable(true)->default(null);
            $table->string('seller_address')->nullable(true)->default(null);
            $table->string('seller_phone')->nullable(true)->default(null);
            $table->string('seller_email')->nullable(true)->default(null);
            $table->string('buyer_first_name')->nullable(true)->default(null);
            $table->string('buyer_last_name')->nullable(true)->default(null);
            $table->string('buyer_company')->nullable(true)->default(null);
            $table->string('buyer_company_vat')->nullable(true)->default(null);
            $table->string('buyer_company_number')->nullable(true)->default(null);
            $table->string('buyer_address')->nullable(true)->default(null);
            $table->string('buyer_city')->nullable(true)->default(null);
            $table->string('buyer_state')->nullable(true)->default(null);
            $table->string('buyer_country')->nullable(true)->default(null);
            $table->string('buyer_zip')->nullable(true)->default(null);
            $table->string('buyer_phone')->nullable(true)->default(null);
            $table->string('buyer_phone_cc')->nullable(true)->default(null);
            $table->string('buyer_email')->nullable(true)->default(null);
            $table->integer('gateway_id')->nullable(true)->default(null); // This should be Size 11
            $table->boolean('approved')->nullable(true)->default('0');
            $table->string('taxname')->nullable(true)->default(null);
            $table->string('taxrate',35)->nullable(true)->default(null);
            $table->datetime('due_at')->nullablE(true)->default(null);
            $table->datetime('reminded_at')->nullablE(true)->default(null);
            $table->datetime('paid_at')->nullablE(true)->default(null);
            $table->timestamps();
        });
        Schema::create('invoice_item', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('invoice_id')->nullable(true)->default(null)->index();
            $table->string('type',100)->nullable(true)->default(null);
            $table->text('rel_id')->nullable(true)->default(null);
            $table->string('task',100)->nullable(true)->default(null);
            $table->string('status',100)->nullable(true)->default(null);
            $table->string('title')->nullable(true)->default(null);
            $table->string('period',10)->nullable(true)->default(null);
            $table->bigInteger('quantity')->nullable(true)->default(null);
            $table->string('unit',100)->nullable(true)->default(null);
            $table->double('price',18,2)->nullable(true)->default(null);
            $table->boolean('charged')->nullable(true)->default('0');
            $table->boolean('taxed')->nullable(true)->default('0');
            $table->timestamps();
        });
            // Knowledge Base
        Schema::create('kb_article', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('kb_article_category_id')->nullable(true)->default(null)->index();
            $table->integer('views')->nullable(true)->default(null); // This should be Size 11
            $table->string('title',100)->nullable(true)->default(null);
            $table->text('content')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->string('status',30)->nullable(true)->default('active');
            $table->timestamps();
        });
        Schema::create('kb_article_category', function (Blueprint $table) {
            $table->id();
            $table->string('title',100)->nullable(true)->default(null);
            $table->text('description')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->timestamps();
        });

            // CORE ????
        Schema::create('pay_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(true)->default(null);
            $table->string('gateway')->nullable(true)->default(null);
            $table->text('accepted_currencies')->nullable(true)->default(null);
            $table->boolean('enabled')->nullable(true)->default('1');
            $table->boolean('allow_single')->nullable(true)->default('1');
            $table->boolean('allow_recurrent')->nullable(true)->default('1');
            $table->boolean('test_mode')->nullable(true)->default('0');
            $table->text('config')->nullable(true)->default(null);
            $table->timestamps();
        });

            // CORE ????
        Schema::create('post', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->nullable(true)->default(null)->index();
            $table->string('title')->nullable(true)->default(null);
            $table->text('description')->nullable(true)->default(null);
            $table->text('content')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->string('status',30)->nullable(true)->default('draft');
            $table->string('image')->nullable(true)->default(null);
            $table->string('section')->nullable(true)->default(null);
            $table->datetime('publish_at')->nullable(true)->default(null);
            $table->datetime('published_at')->nullable(true)->default(null);
            $table->datetime('expires_at')->nullable(true)->default(null);
            $table->timestamps();
        });

        //Core
            // Products
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_category_id')->nullable(true)->default(null)->index();
            $table->bigInteger('product_payment_id')->nullable(true)->default(null)->index();
            $table->bigInteger('form_id')->nullable(true)->default(null)->index();
            $table->string('title')->nullable(true)->default(null);
            $table->string('slug')->nullable(true)->default(null)->unique();
            $table->text('description')->nullable(true)->default(null);
            $table->string('unit',30)->nullable(true)->default('product');
            $table->boolean('active')->nullable(true)->default('1');
            $table->string('status',50)->nullable(true)->default('enabled');
            $table->boolean('hidden')->nullable(true)->default('0');
            $table->boolean('is_addon')->nullable(true)->default('0');
            $table->string('setup',50)->nullable(true)->default('after_payment');
            $table->text('addons')->nullable(true)->default(null);
            $table->string('icon_url')->nullable(true)->default(null)->unique();
            $table->boolean('allow_quantity_select')->nullable(true)->default('0');
            $table->boolean('stock_control')->nullable(true)->default('0');
            $table->integer('quantity_in_stock')->nullable(true)->default(null); // This should be Size 11
            $table->string('plugin')->nullable(true)->default('after_payment');
            $table->text('plugin_config')->nullable(true)->default(null);
            $table->text('upgrades')->nullable(true)->default(null);
            $table->bigInteger('priority')->nullable(true)->default(null);
            $table->text('config')->nullable(true)->default(null);
            $table->timestamps();
            $table->string('type')->nullable(true)->default(null);
        });
        Schema::create('product_category', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(true)->default(null);
            $table->text('description')->nullable(true)->default(null);
            $table->string('icon_url')->nullable(true)->default(null)->unique();
            $table->timestamps();
        });
        Schema::create('product_payment', function (Blueprint $table) {
            $table->id();
            $table->string('type',30)->nullable(true)->default(null);
            $table->decimal('once_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('once_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('w_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('m_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('q_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('b_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('a_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('bia_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('tria_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('w_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('m_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('q_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('b_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('a_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('bia_setup_price',18,2)->nullable(true)->default('0.00');
            $table->decimal('tria_setup_price',18,2)->nullable(true)->default('0.00');
            $table->boolean('w_enabled')->nullable(true)->default('1');
            $table->boolean('m_enabled')->nullable(true)->default('1');
            $table->boolean('q_enabled')->nullable(true)->default('1');
            $table->boolean('b_enabled')->nullable(true)->default('1');
            $table->boolean('a_enabled')->nullable(true)->default('1');
            $table->boolean('bia_enabled')->nullable(true)->default('1');
            $table->boolean('tria_enabled')->nullable(true)->default('1');
        });

        // Core
        // Promo
        Schema::create('promo', function (Blueprint $table) {
            $table->id();
            $table->string('code',100)->nullable(true)->default(null)->index();
            $table->text('description')->nullable(true)->default(null);
            $table->string('type')->nullable(false)->default('percentage');
            $table->decimal('value',18,2)->nullable(true)->default(null);
            $table->integer('maxuses')->nullable(true)->default('0'); // This should be Size 11
            $table->integer('used')->nullable(true)->default('0'); // This should be Size 11
            $table->boolean('freesetup')->nullable(true)->default('0');
            $table->boolean('once_per_client')->nullable(true)->default('0');
            $table->boolean('recurring')->nullable(true)->default('0');
            $table->boolean('active')->nullable(true)->default('0');
            $table->text('products')->nullable(true)->default(null);
            $table->text('periods')->nullable(true)->default(null);
            $table->text('client_groups')->nullable(true)->default(null);
            $table->datetime('start_at')->nullable(true)->default(null)->index();
            $table->datetime('end_at')->nullable(true)->default(null)->index();

            $table->timestamps();
        });
        // Settings
        Schema::create('setting', function (Blueprint $table) {
            $table->id();
            $table->string('param')->nullable(true)->default(null)->unique();
            $table->text('value')->nullable(true)->default(null);
            $table->boolean('public')->nullable(true)->default('0');
            $table->string('category')->nullable(true)->default(null);
            $table->string('hash')->nullable(true)->default(null);

            $table->timestamps();
        });
        // Subscription
        Schema::create('subscription', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable(true)->default(null)->index();
            $table->bigInteger('pay_gateway_id')->nullable(true)->default(null)->index();
            $table->string('sid')->nullable(false)->default(null);
            $table->string('rel_type',100)->nullable(false)->default(null);
            $table->bigInteger('rel_id')->nullable(true)->default(null);
            $table->string('period')->nullable(false)->default(null);
            $table->double('amount',18,2)->nullable(true)->default(null);
            $table->string('currency',50)->nullable(false)->default(null);
            $table->string('status')->nullable(false)->default(null);
            $table->timestamps();
        });
        // Tax
        Schema::create('tax', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('level')->nullable(true)->default(null);
            $table->string('name')->nullable(false)->default(null);
            $table->string('country')->nullable(false)->default(null);
            $table->string('state')->nullable(false)->default(null);
            $table->string('taxrate')->nullable(false)->default(null);
            $table->timestamps();
        });

        // Tax
        Schema::create('transaction', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('invoice_id')->nullable(true)->default(null);
            $table->integer('gateway_id')->nullable(true)->default(null); // This should be Size 11
            $table->string('txn_id')->nullable(false)->default(null);
            $table->string('txn_status')->nullable(false)->default(null);
            $table->string('s_id')->nullable(false)->default(null);
            $table->string('s_period')->nullable(false)->default(null);
            $table->string('currency',10)->nullable(false)->default(null);
            $table->string('type')->nullable(false)->default(null);
            $table->string('status')->nullable(false)->default('received');
            $table->string('ip',45)->nullable(false)->default(null);
            $table->text('error')->nullable(true)->default(null);
            $table->integer('error_code')->nullable(true)->default(null); // This should be Size 11
            $table->boolean('validate_ipn')->nullable(true)->default('1');
            $table->text('ipn')->nullable(true)->default(null);
            $table->text('output')->nullable(true)->default(null);
            $table->text('note')->nullable(true)->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_admin_history');
        Schema::dropIfExists('activity_client_email');
        Schema::dropIfExists('activity_client_history');
        Schema::dropIfExists('activity_system');
        Schema::dropIfExists('admin');
        Schema::dropIfExists('admin_group');
        Schema::dropIfExists('api_request');
        Schema::dropIfExists('cart');
        Schema::dropIfExists('cart_product');
        Schema::dropIfExists('client');
        Schema::dropIfExists('client_balance');
        Schema::dropIfExists('client_group');
        Schema::dropIfExists('client_order');
        Schema::dropIfExists('client_order_meta');
        Schema::dropIfExists('client_order_status');
        Schema::dropIfExists('client_password_reset');
        Schema::dropIfExists('currency');
        Schema::dropIfExists('email_template');
        Schema::dropIfExists('extension');
        Schema::dropIfExists('extension_meta');
        Schema::dropIfExists('form');
        Schema::dropIfExists('form_field');

        Schema::dropIfExists('forum');
        Schema::dropIfExists('forum_topic');
        Schema::dropIfExists('forum_topic_message');

        Schema::dropIfExists('invoice');
        Schema::dropIfExists('invoice_item');

        Schema::dropIfExists('kb_article');
        Schema::dropIfExists('kb_article_category');

        Schema::dropIfExists('pay_gateway');

        Schema::dropIfExists('post');

        Schema::dropIfExists('product');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('product_payment');

        Schema::dropIfExists('promo');
        Schema::dropIfExists('setting');
        Schema::dropIfExists('subscription');
        Schema::dropIfExists('tax');

        Schema::dropIfExists('transaction');


    }
};
