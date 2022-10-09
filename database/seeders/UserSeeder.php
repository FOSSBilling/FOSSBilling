<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name' => 'Demo Client',
            'role' => 'client',
            'status' => 'active',
            'currency' => 'USD',
            'email' => 'demo@fossbiliing.org',
            'password' => Hash::make('demo123'),
        ]);
    }
}
