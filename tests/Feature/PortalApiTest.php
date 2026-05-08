<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_payload_contains_core_modules(): void
    {
        $this->seed();

        $response = $this->getJson('/api/portal');

        $response->assertOk()
            ->assertJsonStructure([
                'tenant',
                'user',
                'metrics' => ['orders', 'tickets', 'claims', 'requiredActions'],
                'orders',
                'productTypes',
                'productMappings',
                'issues',
                'requiredActions',
                'notificationSubscriptions',
            ]);
    }

    public function test_import_preview_routes_unmapped_rows_to_actions(): void
    {
        $this->seed();

        $csv = implode("\n", [
            'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
            'A-100,Unknown Marketplace Product,NO-MAP,1,Ada Lovelace,1 Main St,Austin,TX,78701,US',
        ]);

        $response = $this->postJson('/api/orders/imports/preview', ['csv' => $csv]);

        $response->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.needs_action', 1);
    }
}
