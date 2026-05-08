<?php

namespace App\Services;

class CsvOrderImportParser
{
    /** @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents));

        if (! $lines || trim($lines[0]) === '') {
            return ['headers' => [], 'rows' => [], 'errors' => [1 => ['CSV file is empty.']]];
        }

        $headers = array_map(fn (string $value): string => trim($value), str_getcsv(array_shift($lines)));
        $required = ['order_number', 'item_name', 'item_sku', 'quantity', 'customer_name', 'address_line_1', 'city', 'state', 'postal_code', 'country'];
        $missingHeaders = array_values(array_diff($required, $headers));
        $rows = [];
        $errors = [];

        if ($missingHeaders !== []) {
            $errors[1] = array_map(fn (string $header): string => "Missing required column: {$header}.", $missingHeaders);
        }

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rowNumber = $index + 2;
            $values = str_getcsv($line);
            $row = array_combine($headers, array_pad($values, count($headers), null)) ?: [];
            $rowErrors = [];

            foreach ($required as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    $rowErrors[] = "{$field} is required.";
                }
            }

            if (isset($row['quantity']) && (! is_numeric($row['quantity']) || (int) $row['quantity'] < 1)) {
                $rowErrors[] = 'quantity must be a positive integer.';
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'status' => $rowErrors === [] ? 'valid' : 'invalid',
                'payload' => $row,
            ];

            if ($rowErrors !== []) {
                $errors[$rowNumber] = $rowErrors;
            }
        }

        return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
    }
}
