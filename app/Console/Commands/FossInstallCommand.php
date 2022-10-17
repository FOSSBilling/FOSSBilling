<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FossInstallCommand extends Command
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
            __('Would you like to migrate the database.'),
            function () {
                $this->call('migrate');

                // Model User
                $this->askStep(
                    'Add Super Admin User',
                    function () {
                        $first_name = $this->ask('First name');
                        $last_name = $this->ask('Last name');
                        $email = $this->ask('Email');
                        $password = Str::random();
                        $user = User::create(
                            [
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                                'email' => $email,
                                'password' => Hash::make($password),
                                'type' => 'admin',
                            ]);
                        $superadmin = Role::firstOrCreate(['name' => 'Super Admin']);
                        $user->assignRole([$superadmin]);
                        $user->save();
                        $this->info('Password: '.$password);
                    }
                );
                //Create the roles now so we can use the min the migration

                $admin = Role::firstOrCreate([    'name'=> 'admin']);
                $permission = Permission::firstOrCreate(['name' => 'view admin']);
                $admin->givePermissionTo($permission);
                $permission = Permission::firstOrCreate(['name' => 'edit settings']);
                $admin->givePermissionTo($permission);

                $staff = Role::firstOrCreate(['name' => 'staff']);

                $this->info('Created roles');
            }
        );

        return 0;
    }

    public function askStep($question, $yesCallback, $noCallback = null)
    {
        if ($this->confirm($question, true)) {
            $yesCallback();
        } else {
            if ($noCallback === null) {
                $this->info('Step Skipped.');
            } else {
                $noCallback();
            }
        }
    }
}
