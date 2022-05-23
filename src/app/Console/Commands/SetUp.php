<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Setting;
use App\Models\Currency;
use App\Models\Tax;
use App\Models\PaymentGateway;
use App\Models\ProductCategory;
use App\Models\AdminGroup;
use App\Models\Admin;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foss:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install FOSSBilling';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // migrate
        $this->askStep(
            __("Would you like to migrate the database."),
            function () {
                $this->call('migrate');

                        // Model User
        $this->askStep(
            'Add Super Admin User',
             function () {
                 $user = User::create(
                 [
                     'id' => 5,
                     'name' => 'Admin',
                     'email' => 'admin@localhost',
                     'password' => Hash::make('password')
                 ]);
             }
         );
         //Create the roles now so we can use the min the migration
         $superadmin = Role::firstOrCreate(['name' => 'Super Admin']);
         if (isset($user)){
             $user = User::first();
             $user->assignRole($superadmin);
         }
 
         $admin = Role::firstOrCreate(['name' => 'admin']);
         $permission = Permission::firstOrCreate(['name' => 'view admin']);
         $admin->givePermissionTo($permission);
         $permission = Permission::firstOrCreate(['name' => 'edit settings']);
         $admin->givePermissionTo($permission);
 
         $staff = Role::firstOrCreate(['name' => 'staff']);
        
         $this->info("Created roles");
                //settings table
                if (Schema::hasTable('setting')) {

                    DB::table('setting')
                    ->lazyById()->each(function ($setting) {
                        $row = Setting::firstOrCreate(['key'=>$setting->param],[
                            'key'=>$setting->param,
                            'value'=>$setting->value
                        ]);
                        $row->save();
                    });
                    Schema::drop('setting');
                }
                //currency table
                if (Schema::hasTable('currency')) {

                    DB::table('currency')
                    ->lazyById()->each(function ($currency) {
                        $row = Currency::firstOrCreate(['code'=>$currency->code],[
                            'title'=>$currency->title,
                            'code'=>$currency->code,
                            'is_default'=>$currency->is_default,
                            'conversion_rate'=>$currency->conversion_rate
                        ]);
                        $row->save();
                    });
                    Schema::drop('currency');
                }
                //tax table
                if (Schema::hasTable('tax')) {

                    DB::table('tax')
                    ->lazyById()->each(function ($tax) {
                        $row = Tax::firstOrCreate(['level'=>$tax->level,'country'=>$tax->country,'state'=>$tax->state],[
                            'level'=>($tax->level === null ? 0: $tax->level),
                            'name'=>$tax->name,
                            'country'=>$tax->country,
                            'state'=>$tax->state,
                            'taxrate'=>$tax->taxrate
                        ]);
                        $row->save();
                    });
                    Schema::drop('tax');
                }
                //pay_gateway table
                if (Schema::hasTable('pay_gateway')) {

                    DB::table('pay_gateway')
                      ->lazyById()->each(function ($pay_gateways) {
                        $row = PaymentGateway::firstOrCreate(['name'=>$pay_gateways->name,'gateway'=>$pay_gateways->gateway,'accepted_currencies'=>$pay_gateways->accepted_currencies],[
                            'name'=>$pay_gateways->name,
                            'gateway'=>$pay_gateways->gateway,
                            'accepted_currencies'=>$pay_gateways->accepted_currencies,
                            'enabled'=>$pay_gateways->enabled,
                            'allow_single'=>$pay_gateways->allow_single,
                            'allow_recurrent'=>$pay_gateways->allow_recurrent,
                            'test_mode'=>$pay_gateways->test_mode,
                            'config'=>$pay_gateways->config
                        ]);
                        $row->save();
                    });
                    Schema::drop('pay_gateway');
                }
                //product_category table
                if (Schema::hasTable('product_category')) {

                    DB::table('product_category')
                      ->lazyById()->each(function ($product_category) {
                        $row = ProductCategory::firstOrCreate(['title'=>$product_category->title,'description'=>$product_category->description],[
                            'title'=>$product_category->title,
                            'description'=>$product_category->description,
                            'icon_url'=>$product_category->icon_url
                        ]);
                        $row->save();
                    });
                    Schema::drop('product_category');
                }
                //admin_group table
                if (Schema::hasTable('admin_group')) {

                    DB::table('admin_group')
                      ->lazyById()->each(function ($admin_group) {
                        $row = AdminGroup::firstOrCreate(['name'=>$admin_group->name],[
                            'name'=>$admin_group->name
                        ]);
                        $row->save();
                    });
                    Schema::drop('admin_group');
                }
                //admin table
                $this->warn("Migrating admin and staff accounts");
                if (Schema::hasTable('admin')) {

                    DB::table('admin')
                      ->lazyById()->each(function ($admin) {
                        $user = User::create(
                            [
                                'id' => $admin->id,
                                'name' => $admin->name,
                                'email' => $admin->email,
                                'role' => $admin->role,
                                'password' => $admin->pass
                            ]);
                        $role = Role::firstOrCreate(['name' => $admin->role]);
                        $this->info("Migraded user $user->name with the role of $admin->role");
                        $user->assignRole($role);
                    });
                    Schema::drop('admin');
                }
                //client table
                $this->warn("Migrating client accounts");
                if (Schema::hasTable('client')) {

                    DB::table('client')
                      ->lazyById()->each(function ($client) {
                        $user = User::create(
                            [
                                'id' => $client->id,
                                'name' => "$client->first_name $client->last_name",
                                'email' => $client->email,
                                'role' => $client->role,
                                'password' => $client->pass
                            ]);
                        $role = Role::firstOrCreate(['name' => $client->role]);
                        $this->info("Migraded user $user->name with the role of $client->role");
                        $user->assignRole($role);
                    });
                    Schema::drop('client');
                }
            }
        );
        return 0;
    }

    public function askStep($question, $yesCallback, $noCallback = null)
    {
        if ($this->confirm($question, "yes")) {
            $yesCallback();
        } else {
            if ($noCallback === null) {
                $this->info("Step Skipped.");
            } else {
                $noCallback();
            }
        }
    }
}
