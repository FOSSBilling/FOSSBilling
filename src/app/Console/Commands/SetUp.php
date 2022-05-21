<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Setting;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            }
        );

        // Model User
        $this->askStep(
            'Add Alice User',
            function () {
                $user = User::create(
                    [
                        'id' => 1,
                        'name' => 'Alice Hunter',
                        'email' => 'alice@localhost',
                        'password' => 'password'
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
