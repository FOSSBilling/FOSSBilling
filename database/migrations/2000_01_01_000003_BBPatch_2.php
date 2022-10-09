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
        if (Schema::hasTable('client_order')) {
            Schema::table('client_order', function (Blueprint $table) {
                $table->string('group_id')->nullable(true)->default(null)->change();
            });        
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
        if (Schema::hasTable('legacy_mod_massmailer')) {
            Schema::rename('legacy_mod_massmailer', 'mod_massmailer');
        }

        if (Schema::hasTable('legacy_queue')) {
            Schema::rename('legacy_queue', 'queue');
        }
        if (Schema::hasTable('legacy_queue_message')) {
            Schema::rename('legacy_queue_message', 'queue_message');
        }
        if (Schema::hasTable('legacy_service_custom')) {
            Schema::rename('legacy_service_custom', 'service_custom');
        }
        if (Schema::hasTable('legacy_service_domain')) {
            Schema::rename('legacy_service_domain', 'service_domain');
        }
        if (Schema::hasTable('legacy_service_downloadable')) {
            Schema::rename('legacy_service_downloadable', 'service_downloadable');
        }
        if (Schema::hasTable('legacy_service_hosting')) {
            Schema::rename('legacy_service_hosting', 'service_hosting');
        }
        if (Schema::hasTable('legacy_service_hosting_hp')) {
            Schema::rename('legacy_service_hosting_hp', 'service_hosting_hp');
        }
        if (Schema::hasTable('legacy_service_hosting_server')) {
            Schema::rename('legacy_service_hosting_server', 'service_hosting_server');
        }
        if (Schema::hasTable('legacy_service_license')) {
            Schema::rename('legacy_service_license', 'service_license');
        }
        if (Schema::hasTable('legacy_service_membership')) {
            Schema::rename('legacy_service_membership', 'service_membership');
        }
        if (Schema::hasTable('legacy_service_solusvm')) {
            Schema::rename('legacy_service_solusvm', 'service_solusvm');
        }


        if (Schema::hasTable('legacy_support_helpdesk')) {
            Schema::rename('legacy_support_helpdesk', 'support_helpdesk');
        }
        if (Schema::hasTable('legacy_support_p_ticket')) {
            Schema::rename('legacy_support_p_ticket', 'support_p_ticket');
        }
        if (Schema::hasTable('legacy_support_p_ticket')) {
            Schema::rename('legacy_support_p_ticket', 'support_p_ticket_message');
        }
        if (Schema::hasTable('legacy_support_pr')) {
            Schema::rename('legacy_support_pr', 'support_pr');
        }
        if (Schema::hasTable('legacy_support_pr_category')) {
            Schema::rename('legacy_support_pr_category', 'support_pr_category');
        }
        if (Schema::hasTable('legacy_support_ticket')) {
            Schema::rename('legacy_support_ticket', 'support_ticket');
        }
        if (Schema::hasTable('legacy_support_ticket_message')) {
            Schema::rename('legacy_support_ticket_message', 'support_ticket_message');
        }
        if (Schema::hasTable('legacy_support_ticket_note')) {
            Schema::rename('legacy_support_ticket_note', 'support_ticket_note');
        }

        if (Schema::hasTable('legacy_tld')) {
            Schema::rename('legacy_tld', 'tld');
        }
        if (Schema::hasTable('legacy_tld_registrar')) {
            Schema::rename('legacy_tld_registrar', 'tld_registrar');
        }
    }
};
