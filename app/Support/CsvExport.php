<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed, spreadsheet-safe CSV export.
 *
 * Two problems this exists to solve, both real in exports a regulator or auditor
 * might read:
 *
 *  - **Encoding.** Without a UTF-8 BOM, Excel on Windows reads the file as
 *    Latin-1 and mangles every non-ASCII name. That is what people mean when
 *    they say "we need Excel, not CSV".
 *  - **Formula injection.** A cell beginning = + - @ tab or CR is executed as a
 *    formula by Excel, Sheets and LibreOffice. A username of
 *    `=HYPERLINK("http://evil","click")` becomes a live link in an admin's
 *    spreadsheet. Prefixing with an apostrophe neutralises it while keeping the
 *    value readable.
 *
 * Rows are streamed, so a long export cannot exhaust memory or time out.
 */
class CsvExport
{
    /**
     * Characters that make a spreadsheet treat a cell as a formula.
     */
    private const RISKY_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int, string>  $headers
     * @param  callable():iterable  $rows  yields arrays matching $headers
     */
    public static function stream(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM first, so Excel detects UTF-8.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map([self::class, 'safe'], $headers));

            foreach ($rows() as $row) {
                fputcsv($handle, array_map([self::class, 'safe'], $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Neutralise formula injection without destroying the value.
     */
    public static function safe(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return $value === null ? '' : ($value ? 'yes' : 'no');
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return in_array($value[0], self::RISKY_PREFIXES, true) ? "'".$value : $value;
    }
}
