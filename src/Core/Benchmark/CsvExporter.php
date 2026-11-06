<?php

declare(strict_types=1);

namespace AStarLab\Core\Benchmark;

use RuntimeException;

final class CsvExporter
{
    /** @param list<array<string, string|int|float|null>> $rows
     *  @param list<string> $headers
     */
    public function write(string $path, array $headers, array $rows): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException(sprintf('Unable to write CSV file: %s', $path));
        }

        try {
            fputcsv($stream, $headers, ',', '"', '');
            foreach ($rows as $row) {
                $values = [];
                foreach ($headers as $header) {
                    $values[] = $row[$header] ?? '';
                }

                fputcsv($stream, $values, ',', '"', '');
            }
        } finally {
            fclose($stream);
        }
    }
}
