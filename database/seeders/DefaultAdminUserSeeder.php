<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DefaultAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        throw new \RuntimeException('Default passwords are disabled. Run php artisan scheduler:create-admin instead.');
    }
}
