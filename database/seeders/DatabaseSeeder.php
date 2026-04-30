<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DefaultDataSeeder::class,
            CertificateTemplatesSeeder::class,
            DemoDataSeeder::class,
            DemoQuestionsSeeder::class,
        ]);
    }
}
