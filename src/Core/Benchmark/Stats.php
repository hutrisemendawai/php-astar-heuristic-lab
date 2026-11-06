<?php

declare(strict_types=1);

namespace AStarLab\Core\Benchmark;

use InvalidArgumentException;

final class Stats
{
    /** @param list<float|int> $values
     *  @return array{mean: float, median: float, stddev: float, min: float, max: float, p95: float}
     */
    public static function summarize(array $values): array
    {
        if ($values === []) {
            throw new InvalidArgumentException('Cannot summarize an empty sample.');
        }

        $numbers = array_map(static fn (float|int $value): float => (float) $value, $values);
        sort($numbers);
        $count = count($numbers);
        $mean = array_sum($numbers) / $count;
        $sumSquaredDifferences = 0.0;
        foreach ($numbers as $number) {
            $sumSquaredDifferences += ($number - $mean) ** 2;
        }

        $median = $count % 2 === 1
            ? $numbers[intdiv($count, 2)]
            : ($numbers[intdiv($count, 2) - 1] + $numbers[intdiv($count, 2)]) / 2;

        return [
            'mean' => $mean,
            'median' => $median,
            'stddev' => $count > 1 ? sqrt($sumSquaredDifferences / ($count - 1)) : 0.0,
            'min' => $numbers[0],
            'max' => $numbers[$count - 1],
            'p95' => $numbers[(int) ceil(0.95 * $count) - 1],
        ];
    }

    /** @param list<float|int> $values */
    public static function median(array $values): float
    {
        return self::summarize($values)['median'];
    }
}
