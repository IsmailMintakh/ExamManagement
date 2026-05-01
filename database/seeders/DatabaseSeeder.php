<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe default seed.
     *
     * Runs only the baseline data needed for the system to function:
     *   - Roles & permissions
     *   - DDO super-admin user (login: ddo@exam.com / password)
     *   - Current academic session
     *   - Master subjects, exam types, grading scale
     *   - Certificate template designs
     *   - Public website default content (school info, hero slides, DDO message)
     *
     * Demo data (sample schools / students / teachers / exams / marks /
     * questions) is NOT included. To populate demo data on a dev machine,
     * run them explicitly:
     *
     *   php artisan db:seed --class=DemoDataSeeder
     *   php artisan db:seed --class=DemoQuestionsSeeder
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DefaultDataSeeder::class,
            CertificateTemplatesSeeder::class,
            WebsiteContentSeeder::class,
            WebsitePhase2Seeder::class,
            WebsitePhase3Seeder::class,
            WebsitePhase4Seeder::class,
            WebsitePhase5Seeder::class,
        ]);
    }
}
