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
        // Modules
        // Mod email queue
        Schema::dropIfExists('mod_email_queue');
        Schema::dropIfExists('session');

        // Let the Module hanle the migration
        if (Schema::hasTable('mod_massmailer')) {
            Schema::rename('mod_massmailer', 'legacy_mod_massmailer');
        }

        // Queue is technically a module
        if (Schema::hasTable('queue')) {
            Schema::rename('queue', 'legacy_queue');
        }
        if (Schema::hasTable('queue_message')) {
            Schema::rename('queue_message', 'legacy_queue_message');
        }

        // Service is also a complicated one
        // This is only temporary until we have services and provisioning handled
        if (Schema::hasTable('service_custom')) {
            Schema::rename('service_custom', 'legacy_service_custom');
        }
        if (Schema::hasTable('service_domain')) {
            Schema::rename('service_domain', 'legacy_service_domain');
        }
        if (Schema::hasTable('service_downloadable')) {
            Schema::rename('service_downloadable', 'legacy_service_downloadable');
        }
        if (Schema::hasTable('service_hosting')) {
            Schema::rename('service_hosting', 'legacy_service_hosting');
        }
        if (Schema::hasTable('service_hosting_hp')) {
            Schema::rename('service_hosting_hp', 'legacy_service_hosting_hp');
        }
        if (Schema::hasTable('service_hosting_server')) {
            Schema::rename('service_hosting_server', 'legacy_service_hosting_server');
        }
        if (Schema::hasTable('service_license')) {
            Schema::rename('service_license', 'legacy_service_license');
        }
        if (Schema::hasTable('service_membership')) {
            Schema::rename('service_membership', 'legacy_service_membership');
        }
        if (Schema::hasTable('service_solusvm')) {
            Schema::rename('service_solusvm', 'legacy_service_solusvm');
        }


        // Support Desk
        if (Schema::hasTable('support_helpdesk')) {
            Schema::rename('support_helpdesk', 'legacy_support_helpdesk');
        }
        if (Schema::hasTable('support_p_ticket')) {
            Schema::rename('support_p_ticket', 'legacy_support_p_ticket');
        }
        if (Schema::hasTable('support_p_ticket_message')) {
            Schema::rename('support_p_ticket_message', 'legacy_support_p_ticket_message');
        }
        if (Schema::hasTable('support_pr')) {
            Schema::rename('support_pr', 'legacy_support_pr');
        }
        if (Schema::hasTable('support_pr_category')) {
            Schema::rename('support_pr_category', 'legacy_support_pr_category');
        }
        if (Schema::hasTable('support_ticket')) {
            Schema::rename('support_ticket', 'legacy_support_ticket');
        }
        if (Schema::hasTable('support_ticket_message')) {
            Schema::rename('support_ticket_message', 'legacy_support_ticket_message');
        }
        if (Schema::hasTable('support_ticket_note')) {
            Schema::rename('support_ticket_note', 'legacy_support_ticket_note');
        }
        if (Schema::hasTable('tld')) {
            Schema::rename('tld', 'legacy_tld');
        }
        if (Schema::hasTable('tld_registrar')) {
            Schema::rename('tld_registrar', 'legacy_tld_registrar');
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
