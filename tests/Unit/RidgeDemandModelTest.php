<?php

namespace Tests\Unit;

use App\Services\RidgeDemandModel;
use PHPUnit\Framework\TestCase;

class RidgeDemandModelTest extends TestCase
{
    public function test_model_learns_signal_handles_constant_features_and_reloads(): void
    {
        $model = new RidgeDemandModel; $x = []; $y = [];
        for ($i = 0; $i < 40; $i++) { $x[] = [$i, 1]; $y[] = 3 * $i + 5; }
        $fit = json_decode(json_encode($model->fit($x, $y, 0.01)), true);
        $this->assertEqualsWithDelta(140, $model->predict($fit, [45, 1]), 0.1);
        $this->assertSame(0.0, $model->predict($fit, [-100, 1]));
        $this->assertSame(19.5, $fit['means'][0]);
    }
}
