<?php

namespace Database\Seeders;

use App\Models\ExamType;
use App\Services\Scheduler\Catalog;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('scheduler.types') as $code => $type) {
            if (! ExamType::where('code', $code)->exists() && ExamType::where('name', $type['label'])->exists()) {
                $this->command?->warn('Skipped default exam type '.$code.': its name belongs to another catalog entry.');

                continue;
            }
            ExamType::firstOrCreate(['code' => $code], ['name' => $type['label'], 'is_viva' => $type['viva'], 'sort_order' => array_search($code, array_keys(config('scheduler.types')), true) + 1]);
        }
        app(Catalog::class)->clear();
    }
}
