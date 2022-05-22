<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Setting;
use App\Models\Currency;
use App\Models\Tax;
use App\Models\Pay_gateway;

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
    protected $description = 'Install FossBilling';

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
                        $row = Pay_gateway::firstOrCreate(['name'=>$pay_gateways->name,'gateway'=>$pay_gateways->gateway,'accepted_currencies'=>$pay_gateways->accepted_currencies],[
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
            }
        );

        // Model User
        $this->askStep(
            'Add Super Admin User',
            function () {
                $user = User::create(
                    [
                        'id' => 1,
                        'name' => 'Admin',
                        'email' => 'admin@localhost',
                        'password' => Hash::make('password')
                    ]
                );
            }
        );

        $this->askStep(
            __("Add Roles"),
            function () {
                $superadmin = Role::firstOrCreate(['name' => 'Super Admin']);
                $user = User::first();
                $user->assignRole($superadmin);

                $admin = Role::firstOrCreate(['name' => 'Admin']);
                $permission = Permission::firstOrCreate(['name' => 'view admin']);
                $admin->givePermissionTo($permission);
                $permission = Permission::firstOrCreate(['name' => 'edit settings']);
                $admin->givePermissionTo($permission);
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
