<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::firstOrCreate(['key' => 'programs'], [
            'value' => json_encode(config('programs.default', [])),
        ]);
    }
}

