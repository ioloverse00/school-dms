<?php

namespace App\Console\Commands;

use App\Services\SyntheticForecastDataset;
use Illuminate\Console\Command;

class GenerateForecastDemo extends Command
{
    protected $signature = 'forecast:demo-dataset {--seed=42 : Nonnegative integer seed}';
    protected $description = 'Write clearly labeled synthetic weekly CSV/calendar fixtures in isolated demo storage; never creates real requests.';

    public function handle(SyntheticForecastDataset $generator): int
    {
        if (!app()->environment(['local', 'testing']) || (!app()->environment('testing') && basename(str_replace('\\', '/', storage_path())) !== 'local-uat')) {
            $this->error('Use isolated local-uat storage or testing. The working database/storage is not a demo target.');
            return self::FAILURE;
        }
        $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        if ($seed === false) { $this->error('Seed must be an integer between 0 and 2147483647.'); return self::FAILURE; }
        $path = storage_path('app/private/forecast-demo/seed-'.$seed);
        if (is_dir($path)) { $this->error('Dataset already exists; refusing to overwrite it. Use a different seed.'); return self::FAILURE; }
        $dataset = $generator->build($seed);
        if (!mkdir($path, 0700, true)) { $this->error('Could not create private dataset directory.'); return self::FAILURE; }
        foreach (['calendar', 'manifest'] as $name) {
            $stream = fopen($path.'/'.$name.'.json', 'x');
            fwrite($stream, json_encode($dataset[$name], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); fclose($stream);
        }
        $stream = fopen($path.'/weekly-requests.csv', 'x');
        fputcsv($stream, array_keys($dataset['rows'][0]), ',', '"', '');
        foreach ($dataset['rows'] as $row) fputcsv($stream, $row, ',', '"', '');
        fclose($stream);
        $this->info('Synthetic dataset created: '.$path.' ('.$dataset['manifest']['rows'].' rows). No school request records were inserted; no model was trained.');
        return self::SUCCESS;
    }
}
