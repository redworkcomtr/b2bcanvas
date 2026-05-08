<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_portal_requires_authentication(): void
    {
        $this->getJson('/api/portal')->assertUnauthorized();
    }

    public function test_active_user_can_login_and_read_session(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'selin@example.test')->firstOrFail();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'selin@example.test',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'selin@example.test')
            ->assertJsonPath('tenant.slug', 'atelier-canvas')
            ->assertJsonFragment(['manage_users']);

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/auth/session')->assertOk();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed();

        $this->postJson('/api/auth/login', [
            'email' => 'pending@example.test',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_portal_payload_contains_core_modules(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('email', 'selin@example.test')->firstOrFail());

        $this->getJson('/api/workspace')->assertOk();

        $response = $this->getJson('/api/portal');

        $response->assertOk()
            ->assertJsonStructure([
                'tenant',
                'user',
                'abilities',
                'metrics' => ['orders', 'tickets', 'claims', 'requiredActions'],
                'orders',
                'productTypes',
                'productMappings',
                'issues',
                'requiredActions',
                'notificationSubscriptions',
                'users',
                'userInvites',
            ]);
    }

    public function test_import_preview_routes_unmapped_rows_to_actions(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('email', 'selin@example.test')->firstOrFail());

        $csv = implode("\n", [
            'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
            'A-100,Unknown Marketplace Product,NO-MAP,1,Ada Lovelace,1 Main St,Austin,TX,78701,US',
        ]);

        $response = $this->postJson('/api/orders/imports/preview', ['csv' => $csv]);

        $response->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.needs_action', 1);
    }

    public function test_order_queries_are_tenant_isolated(): void
    {
        $this->seed();
        $tenant = Tenant::query()->where('slug', 'atelier-canvas')->firstOrFail();
        $actor = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $otherTenant = Tenant::query()->create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        $otherOrder = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'order_number' => 'OTHER-100',
            'status' => 'verified',
            'customer_name' => 'Outside Customer',
            'shipping_address' => ['line1' => '1 Outside', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'country' => 'US'],
        ]);

        $this->actingAs($actor);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonMissing(['order_number' => $otherOrder->order_number])
            ->assertJsonFragment(['tenant_id' => $tenant->id]);

        $this->getJson('/api/orders/'.$otherOrder->uuid)->assertNotFound();
    }

    public function test_viewer_cannot_create_orders_or_invite_users(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $this->actingAs($viewer);

        $this->postJson('/api/orders', [
            'order_number' => 'WEB-DENIED',
            'customer_name' => 'Read Only',
            'shipping_address' => ['line1' => '1 Main', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'country' => 'US'],
            'items' => [['item_name' => 'Canvas', 'quantity' => 1]],
        ])->assertForbidden();

        $this->postJson('/api/users/invites', [
            'name' => 'Blocked Invite',
            'email' => 'blocked@example.test',
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_owner_can_invite_and_update_tenant_users(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $this->actingAs($owner);

        $invite = $this->postJson('/api/users/invites', [
            'name' => 'New Operator',
            'email' => 'new-operator@example.test',
            'role' => 'operations',
        ]);

        $invite->assertCreated()
            ->assertJsonPath('user.active', false)
            ->assertJsonPath('user.role', 'operations');

        $userId = $invite->json('user.id');

        $this->patchJson('/api/users/'.$userId, [
            'active' => true,
            'role' => 'support',
        ])->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('role', 'support');
    }

    public function test_user_management_blocks_cross_tenant_updates(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $otherTenant = Tenant::query()->create(['name' => 'Other Tenant', 'slug' => 'other-users']);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($owner);

        $this->patchJson('/api/users/'.$otherUser->id, ['active' => false])->assertForbidden();
    }
}
