<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Services\Scheduler\Catalog;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('scheduler.user_units') as $i => $name) {
            if (! Unit::where('code', $name)->exists() && Unit::where('name', $name)->exists()) {
                $this->command?->warn('Skipped default unit '.$name.': its name belongs to another catalog entry.');

                continue;
            }
            Unit::firstOrCreate(['code' => $name], ['name' => $name, 'sort_order' => $i + 1, 'for_exams' => in_array($name, config('scheduler.units'), true), 'for_users' => true]);
        }
        app(Catalog::class)->clear();
    }
}
