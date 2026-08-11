<?php

namespace Database\Seeders;

use App\Models\Feed;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()
            ->has(Feed::factory()->state(['name' => 'Main']))
            ->create([
                'name'     => 'Bill Yanelli',
                'email'    => 'bill.yanelli@gmail.com',
                'password' => Hash::make('password'),
            ]);
    }
}
