<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::insert([
            [
                'name' => 'Ricky Galang 2',
                'email' => 'ricky.psgtso@reliancehealth.com.ph',
                'password' => Hash::make('HAVrV9tTlMdHazsuEQXtBE8WOuac68SIOBdH6WU4')
            ],
            [
                'name' => 'Ian Mendoza',
                'email' => 'ian.mendoza@reliancehealth.com.ph',
                'password' => Hash::make('HAVrV9tTlMdHazsuEQXtBE8WOuac68SIOBdH6WU4')
            ]
        ]);
    }
}
