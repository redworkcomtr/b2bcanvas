<?php

namespace App\Services;

class CsvOrderImportParser
{
    private const REQUIRED_HEADERS = ['order_number', 'item_name', 'item_sku', 'quantity', 'customer_name', 'address_line_1', 'city', 'state', 'postal_code', 'country'];

    /** @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents));

        if (! $lines || trim($lines[0]) === '') {
            return ['headers' => [], 'rows' => [], 'errors' => [1 => ['CSV file is empty.']]];
        }

        $headers = array_map(fn (string $value): string => $this->normalizeHeader($value), str_getcsv(array_shift($lines)));
        $records = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $records[] = [
                'row_number' => $index + 2,
                'values' => str_getcsv($line),
            ];
        }

        return $this->parseRecords($headers, $records);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array{row_number: int, values: array<int, mixed>}>  $records
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>}
     */
    public function parseRecords(array $headers, array $records): array
    {
        $headers = array_map(fn (string $value): string => $this->normalizeHeader($value), $headers);
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        $rows = [];
        $errors = [];

        if ($missingHeaders !== []) {
            $errors[1] = array_map(fn (string $header): string => "Missing required column: {$header}.", $missingHeaders);
        }

        foreach ($records as $record) {
            $rowNumber = $record['row_number'];
            $values = $record['values'];
            $row = array_combine($headers, array_pad($values, count($headers), null)) ?: [];
            $rowErrors = [];

            foreach (self::REQUIRED_HEADERS as $field) {
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

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $normalized = str_replace([' ', '-'], ['_', '_'], $header);

        return match ($normalized) {
            'clientOrderId' => 'order_number',
            'shipToCustomer.fullName' => 'customer_name',
            'shipToCustomer.address1' => 'address_line_1',
            'shipToCustomer.address2' => 'address_line_2',
            'shipToCustomer.city' => 'city',
            'shipToCustomer.state' => 'state',
            'shipToCustomer.zip' => 'postal_code',
            'shipToCustomer.country' => 'country',
            'shippingCode', 'carrier' => 'shipping_service',
            default => $this->normalizeItemHeader($normalized),
        };
    }

    private function normalizeItemHeader(string $header): string
    {
        if (! preg_match('/^items(?:\[\d+\])?\.(.+)$/', $header, $matches)) {
            return $header;
        }

        return match ($matches[1]) {
            'quantity' => 'quantity',
            'name' => 'item_name',
            'clientProductCode', 'sku' => 'item_sku',
            'productCode', 'fulfillmentSku' => 'fulfillment_sku',
            default => $header,
        };
    }
}
