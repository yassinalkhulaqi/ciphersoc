<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RbacSeeder::class, MitreSeeder::class, DetectionRuleSeeder::class, ProviderSeeder::class, PlaybookSeeder::class, DemoDataSeeder::class]);
    }
}
