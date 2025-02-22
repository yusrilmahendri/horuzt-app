<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $user = User::create([
            'name' => 'hanif',
            'email' => 'hanif@gmail.com',
            'email_verified_at' => now(),
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('user');
        $user->createToken('auth_token')->plainTextToken;
        return $user;
    }
}
