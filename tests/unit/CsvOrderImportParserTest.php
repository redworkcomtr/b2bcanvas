<?php

namespace Tests\Unit;

use App\Services\CsvOrderImportParser;
use PHPUnit\Framework\TestCase;

class CsvOrderImportParserTest extends TestCase
{
    public function test_it_validates_required_import_columns_and_rows(): void
    {
        $csv = implode("\n", [
            'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
            'A-100,Canvas Print,SKU-1,2,Ada Lovelace,1 Main St,Austin,TX,78701,US',
            'A-101,,SKU-2,0,Grace Hopper,2 Main St,Austin,TX,78701,US',
        ]);

        $result = (new CsvOrderImportParser())->parse($csv);

        $this->assertCount(2, $result['rows']);
        $this->assertSame('valid', $result['rows'][0]['status']);
        $this->assertSame('invalid', $result['rows'][1]['status']);
        $this->assertArrayHasKey(3, $result['errors']);
    }
}
