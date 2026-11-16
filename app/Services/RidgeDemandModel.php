<?php

namespace App\Services;

/** Offline numeric regression; no provider calls or database writes. */
class RidgeDemandModel
{
    public function fit(array $x, array $y, float $penalty): array
    {
        if (!$x || count($x) !== count($y) || $penalty <= 0) throw new \InvalidArgumentException('Invalid training data.');
        $width = count($x[0]); $means = []; $scales = [];
        for ($j = 0; $j < $width; $j++) {
            $values = array_column($x, $j); $means[$j] = array_sum($values) / count($values);
            $scales[$j] = max(1e-8, sqrt(array_sum(array_map(fn($v) => ($v - $means[$j]) ** 2, $values)) / count($values)));
        }
        $size = $width + 1; $a = array_fill(0, $size, array_fill(0, $size, 0.0)); $b = array_fill(0, $size, 0.0);
        foreach ($x as $i => $row) {
            if (count($row) !== $width) throw new \InvalidArgumentException('Inconsistent feature width.');
            $z = [1.0]; foreach ($row as $j => $v) $z[] = ($v - $means[$j]) / $scales[$j];
            for ($j = 0; $j < $size; $j++) { $b[$j] += $z[$j] * $y[$i]; for ($k = 0; $k < $size; $k++) $a[$j][$k] += $z[$j] * $z[$k]; }
        }
        for ($j = 1; $j < $size; $j++) $a[$j][$j] += $penalty;
        for ($j = 0; $j < $size; $j++) {
            $pivot = $j; for ($k = $j + 1; $k < $size; $k++) if (abs($a[$k][$j]) > abs($a[$pivot][$j])) $pivot = $k;
            [$a[$j], $a[$pivot]] = [$a[$pivot], $a[$j]]; [$b[$j], $b[$pivot]] = [$b[$pivot], $b[$j]];
            $divisor = $a[$j][$j]; if (abs($divisor) < 1e-10) throw new \RuntimeException('Singular training matrix.');
            for ($k = $j; $k < $size; $k++) $a[$j][$k] /= $divisor; $b[$j] /= $divisor;
            for ($r = 0; $r < $size; $r++) {
                if ($r === $j) continue; $factor = $a[$r][$j];
                for ($k = $j; $k < $size; $k++) $a[$r][$k] -= $factor * $a[$j][$k]; $b[$r] -= $factor * $b[$j];
            }
        }
        return ['means' => $means, 'scales' => $scales, 'coefficients' => $b, 'penalty' => $penalty];
    }

    public function predict(array $model, array $features): float
    {
        if (count($features) !== count($model['means'])) throw new \InvalidArgumentException('Feature width mismatch.');
        $result = $model['coefficients'][0];
        foreach ($features as $j => $value) $result += $model['coefficients'][$j + 1] * (($value - $model['means'][$j]) / $model['scales'][$j]);
        return max(0.0, $result);
    }
}
