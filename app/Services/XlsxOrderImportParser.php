<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxOrderImportParser
{
    public function __construct(private readonly CsvOrderImportParser $csvParser) {}

    /** @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function parse(string $contents): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX imports require the PHP zip extension.');
        }

        $path = tempnam(sys_get_temp_dir(), 'b2b-xlsx-import-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary XLSX file.');
        }

        file_put_contents($path, $contents);

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('The uploaded XLSX file could not be opened.');
            }

            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($this->firstWorksheetPath($zip));

            if ($sheetXml === false) {
                throw new RuntimeException('The uploaded XLSX file does not contain a worksheet.');
            }

            $rows = $this->worksheetRows($sheetXml, $sharedStrings);
            $zip->close();
        } finally {
            @unlink($path);
        }

        if ($rows === []) {
            return ['headers' => [], 'rows' => [], 'errors' => [1 => ['XLSX file is empty.']]];
        }

        $headers = array_map(fn ($value): string => trim((string) $value), array_shift($rows));
        $records = [];

        foreach ($rows as $index => $values) {
            $hasValue = false;
            foreach ($values as $value) {
                if (trim((string) $value) !== '') {
                    $hasValue = true;
                    break;
                }
            }

            if (! $hasValue) {
                continue;
            }

            $records[] = [
                'row_number' => $index + 2,
                'values' => $values,
            ];
        }

        return $this->csvParser->parseRecords($headers, $records);
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        if (! $document instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];
        foreach ($document->si as $item) {
            $strings[] = $this->flattenText($item);
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relationships === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbookXml = simplexml_load_string($workbook);
        $relsXml = simplexml_load_string($relationships);

        if (! $workbookXml instanceof SimpleXMLElement || ! $relsXml instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $workbookXml->xpath('//main:sheet') ?: [];
        $sheet = $sheets[0] ?? null;
        if (! $sheet instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($attributes['id'] ?? '');

        $relsXml->registerXPathNamespace('pkg', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relsXml->xpath('//pkg:Relationship') ?: [] as $relationship) {
            $relationshipAttributes = $relationship->attributes();
            if ((string) ($relationshipAttributes['Id'] ?? '') !== $relationshipId) {
                continue;
            }

            $target = (string) ($relationshipAttributes['Target'] ?? 'worksheets/sheet1.xml');

            return str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/'.ltrim($target, '/');
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function worksheetRows(string $sheetXml, array $sharedStrings): array
    {
        $document = simplexml_load_string($sheetXml);
        if (! $document instanceof SimpleXMLElement) {
            return [];
        }

        $rows = [];
        foreach ($document->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $cellAttributes = $cell->attributes();
                $reference = (string) ($cellAttributes['r'] ?? '');
                $columnIndex = $reference !== '' ? $this->columnIndex($reference) : count($values);
                $values[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values !== []) {
                ksort($values);
                $rows[] = array_values(array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values));
            }
        }

        return $rows;
    }

    /** @param array<int, string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell->attributes()['t'] ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) ($cell->v ?? -1)] ?? '';
        }

        if ($type === 'inlineStr') {
            return $this->flattenText($cell->is);
        }

        return trim((string) ($cell->v ?? ''));
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function flattenText(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = $node->xpath('.//main:t') ?: [];

        if ($parts === []) {
            return trim((string) $node->t);
        }

        return implode('', array_map(fn (SimpleXMLElement $part): string => (string) $part, $parts));
    }
}
