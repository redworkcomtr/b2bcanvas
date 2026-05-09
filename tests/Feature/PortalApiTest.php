<?php

namespace Tests\Feature;

use App\Models\ImportRow;
use App\Models\Issue;
use App\Models\MediaFile;
use App\Models\NotificationMailLog;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_portal_payload_includes_reporting_metrics(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('email', 'selin@example.test')->firstOrFail());

        $response = $this->getJson('/api/portal');

        $response->assertOk();

        $response->assertJsonStructure([
            'metrics' => [
                'orders_total',
                'orders_open_action_needed',
                'orders_draft',
                'orders_action_needed',
                'orders_in_production',
                'orders_shipped',
                'imports_total_rows',
                'imports_action_ratio',
                'imports_preview',
                'imports_committed',
                'import_rows_ready',
                'import_rows_needs_action',
            ],
        ]);

        $metrics = $response->json('metrics');
        $this->assertIsInt($metrics['imports_total_rows']);
        $this->assertIsInt($metrics['imports_action_ratio']);
        $this->assertIsInt($metrics['orders_open_action_needed']);
    }

    public function test_ticket_workflow_updates_status_comments_attachments_and_read_state(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $support = User::query()->where('email', 'support@example.test')->firstOrFail();
        $ticket = Issue::query()->where('type', 'ticket')->firstOrFail();
        $media = MediaFile::query()->create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'collection' => 'issue_attachment',
            'disk' => 'local',
            'path' => 'issue-attachments/delivery-proof.pdf',
            'original_name' => 'delivery-proof.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1280,
            'checksum' => hash('sha256', 'delivery-proof'),
            'scan_state' => 'clean',
            'metadata' => [],
        ]);

        $this->actingAs($owner);

        $this->patchJson('/api/issues/'.$ticket->id, [
            'status' => 'waiting_customer',
            'priority' => 'urgent',
            'assigned_to_id' => $support->id,
        ])->assertOk()
            ->assertJsonPath('status', 'waiting_customer')
            ->assertJsonPath('priority', 'urgent')
            ->assertJsonPath('assigned_to_id', $support->id);

        $this->postJson('/api/issues/'.$ticket->id.'/comments', [
            'body' => 'Internal carrier note with delivery proof attached.',
            'internal' => true,
            'attachments' => [[
                'id' => $media->id,
                'original_name' => $media->original_name,
            ]],
        ])->assertOk()
            ->assertJsonPath('comments.3.body', 'Internal carrier note with delivery proof attached.')
            ->assertJsonPath('comments.3.internal', true)
            ->assertJsonPath('total_notes_count', 4);

        $this->postJson('/api/issues/'.$ticket->id.'/read')
            ->assertOk()
            ->assertJsonPath('unread_notes_count', 0);

        $ticket->refresh()->load('comments');
        $this->assertSame('waiting_customer', $ticket->status);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame($support->id, $ticket->assigned_to_id);
        $this->assertTrue($ticket->comments->last()->internal);
        $this->assertSame($media->id, $ticket->comments->last()->attachments[0]['id']);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'issue.comment_added',
            'auditable_type' => Issue::class,
            'auditable_id' => $ticket->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'issue.marked_read',
            'auditable_id' => $ticket->id,
        ]);
    }

    public function test_ticket_public_reply_increments_unread_for_assignee(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $support = User::query()->where('email', 'support@example.test')->firstOrFail();
        $ticket = Issue::query()->where('type', 'ticket')->firstOrFail();
        $ticket->update([
            'assigned_to_id' => $support->id,
            'unread_notes_count' => 0,
        ]);

        $this->actingAs($owner);

        $this->postJson('/api/issues/'.$ticket->id.'/comments', [
            'body' => 'Customer-facing update that should be unread for the assignee.',
            'internal' => false,
        ])->assertOk()
            ->assertJsonPath('unread_notes_count', 1);
    }

    public function test_viewer_cannot_manage_tickets(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $ticket = Issue::query()->where('type', 'ticket')->firstOrFail();

        $this->actingAs($viewer);

        $this->getJson('/api/issues/'.$ticket->id)->assertForbidden();
        $this->patchJson('/api/issues/'.$ticket->id, ['status' => 'closed'])->assertForbidden();
        $this->postJson('/api/issues/'.$ticket->id.'/comments', ['body' => 'viewer attempt'])->assertForbidden();
    }

    public function test_claim_resolution_records_evidence_and_finance_outcome(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $claim = Issue::query()->where('type', 'claim')->firstOrFail();
        $media = MediaFile::query()->create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'collection' => 'claim_evidence',
            'disk' => 'local',
            'path' => 'claim-evidence/return-label.pdf',
            'original_name' => 'return-label.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1536,
            'checksum' => hash('sha256', 'claim-evidence'),
            'scan_state' => 'clean',
            'metadata' => [],
        ]);
        $this->actingAs($owner);

        $response = $this->postJson('/api/claims/'.$claim->id.'/resolution', [
            'decision' => 'refund',
            'amount_cents' => 2500,
            'currency' => 'USD',
            'finance_reference' => 'FIN-2026-1',
            'production_outcome' => 'Reprint with updated ink profile.',
            'notes' => 'Customer accepted refund and requested reprint cancellation.',
            'evidence_files' => [['id' => $media->id]],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('claim_resolution.decision', 'refund')
            ->assertJsonPath('claim_resolution.finance_reference', 'FIN-2026-1')
            ->assertJsonPath('claim_resolution.amount_cents', 2500);

        $claim->refresh();
        $claim->load('claimResolution');
        $this->assertSame('resolved', $claim->status);
        $this->assertSame('refund', $claim->claimResolution->decision);
        $this->assertSame(2500, $claim->claimResolution->amount_cents);
        $this->assertSame('FIN-2026-1', $claim->claimResolution->finance_reference);
        $this->assertDatabaseHas('claim_resolutions', [
            'issue_id' => $claim->id,
            'decision' => 'refund',
            'amount_cents' => 2500,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'claim.decided',
            'auditable_type' => Issue::class,
            'auditable_id' => $claim->id,
        ]);
        $this->assertDatabaseHas('issue_comments', [
            'issue_id' => $claim->id,
        ]);
        $this->assertSame($media->id, $claim->claimResolution->evidence_files[0]['id']);
    }

    public function test_non_claim_items_cannot_be_resolved_as_claim_decisions(): void
    {
        $this->seed();
        $ticket = Issue::query()->where('type', 'ticket')->firstOrFail();
        $this->actingAs(User::query()->where('email', 'selin@example.test')->firstOrFail());

        $this->postJson('/api/claims/'.$ticket->id.'/resolution', [
            'decision' => 'reject',
        ])->assertNotFound();
    }

    public function test_manage_users_can_preview_and_retry_notification_log(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        Queue::fake();

        $log = NotificationMailLog::query()->create([
            'tenant_id' => $owner->tenant_id,
            'subscription_id' => null,
            'event' => 'ORDER_ACTION_NEEDED',
            'recipient_email' => 'ops@ateliercanvas.test',
            'subject' => 'Import check required',
            'body_html' => '<h1>Import check</h1><p>Action needed.</p>',
            'body_text' => 'Import check\nAction needed.',
            'status' => 'failed',
            'attempts' => 3,
            'max_attempts' => 3,
            'message_id' => null,
            'error_message' => 'SMTP timeout.',
            'metadata' => null,
            'sent_at' => null,
        ]);

        $this->actingAs($owner);

        $this->getJson('/api/notifications/logs')
            ->assertOk()
            ->assertJsonFragment(['id' => $log->id]);

        $this->getJson('/api/notifications/logs/'.$log->id)
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('attempts', 3);

        $this->postJson('/api/notifications/logs/'.$log->id.'/retry', [
            'recipient_email' => 'delivery@ateliercanvas.test',
        ])->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('recipient_email', 'delivery@ateliercanvas.test')
            ->assertJsonPath('attempts', 0);

        $this->assertDatabaseHas('notification_mail_logs', [
            'id' => $log->id,
            'recipient_email' => 'delivery@ateliercanvas.test',
            'status' => 'queued',
            'attempts' => 0,
            'error_message' => null,
        ]);
    }

    public function test_notification_log_endpoints_are_restricted_to_manage_users(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();

        $log = NotificationMailLog::query()->create([
            'tenant_id' => $owner->tenant_id,
            'subscription_id' => null,
            'event' => 'ORDER_SHIPPED',
            'recipient_email' => 'shipping@ateliercanvas.test',
            'subject' => 'Order shipped',
            'body_html' => '<h1>Shipped</h1>',
            'body_text' => 'Order shipped',
            'status' => 'sent',
            'attempts' => 1,
            'max_attempts' => 3,
            'message_id' => null,
            'error_message' => null,
            'metadata' => null,
            'sent_at' => now(),
        ]);

        $this->actingAs($viewer);

        $this->getJson('/api/notifications/logs')->assertForbidden();
        $this->getJson('/api/notifications/logs/'.$log->id)->assertForbidden();
        $this->postJson('/api/notifications/logs/'.$log->id.'/retry')->assertForbidden();
    }

    public function test_duplicate_import_rows_create_required_action_and_notification_log(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        Queue::fake();
        NotificationSubscription::query()
            ->where('user_id', $owner->id)
            ->where('event', 'ORDER_VALIDATION_FAILED')
            ->update(['is_subscribed' => true]);

        $this->actingAs($owner);
        $duplicateOrder = Order::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        $preview = $this->postJson('/api/orders/imports/preview', [
            'csv' => implode("\n", [
                'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
                $duplicateOrder->order_number.',"Framed Art Print-Black / 36"" x 24""",MGC-FP-36x24_Black,1,Ada Lovelace,1 Main St,Austin,TX,78701,US',
            ]),
        ]);

        $preview->assertOk()
            ->assertJsonPath('summary.ready', 0)
            ->assertJsonPath('summary.needs_action', 1)
            ->assertJsonPath('summary.total', 1);

        $importId = $preview->json('import_id');
        $commit = $this->postJson('/api/orders/imports/'.$importId.'/commit');
        $commit->assertOk()
            ->assertJsonPath('created_orders', 0)
            ->assertJsonPath('import.status', 'partial');

        $row = ImportRow::query()->where('import_id', $importId)->firstOrFail();
        $this->assertSame('needs_action', $row->status);
        $this->assertStringContainsString('order_number already exists.', implode(' | ', $row->errors ?? []));

        $action = RequiredAction::query()
            ->where('tenant_id', $owner->tenant_id)
            ->where('type', 'duplicate_order')
            ->where('status', 'open')
            ->latest()
            ->first();

        $this->assertNotNull($action);
        $this->assertSame($duplicateOrder->order_number, $action->payload['order_number']);
        $this->assertDatabaseHas('notification_mail_logs', [
            'tenant_id' => $owner->tenant_id,
            'event' => 'ORDER_VALIDATION_FAILED',
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
            ->assertJsonStructure(['import_id'])
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.needs_action', 1);
    }

    public function test_mapping_creation_revalidates_import_rows_linked_to_required_actions(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $this->actingAs($owner);

        $csv = implode("\n", [
            'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
            'IMP-RA-1,Unmapped Canvas 88,UNMAPPED-CANVAS-88,1,Ada Lovelace,1 Main St,Austin,TX,78701,US',
        ]);

        $preview = $this->postJson('/api/orders/imports/preview', ['csv' => $csv]);
        $preview->assertOk()
            ->assertJsonPath('summary.needs_action', 1);

        $row = ImportRow::query()->where('status', 'needs_action')->latest()->firstOrFail();
        $this->assertSame('UNMAPPED-CANVAS-88', $row->payload['item_sku']);

        $this->postJson('/api/product-mappings', [
            'name' => 'Import revalidation canvas mapping',
            'product_variant_id' => $variant->id,
            'properties' => ['Bleed' => 'Mirror'],
            'rules' => [[
                'field' => 'sku',
                'operator' => 'equals',
                'value' => 'UNMAPPED-CANVAS-88',
                'priority' => 70,
            ]],
        ])->assertCreated()
            ->assertJsonPath('resolved_actions', 1);

        $row->refresh();
        $this->assertSame('ready', $row->status);
        $this->assertSame([], $row->errors);
        $this->assertSame($variant->id, $row->payload['matched_product_variant_id']);
        $this->assertSame(1, $row->import()->firstOrFail()->valid_rows);
        $this->assertSame(0, $row->import()->firstOrFail()->invalid_rows);
    }

    public function test_import_preview_creates_batch_and_commit_creates_ready_orders(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('email', 'selin@example.test')->firstOrFail());

        $csv = implode("\n", [
            'order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country',
            'WEB-9001,"Framed Art Print-Black / 36"" x 24""",MGC-FP-36x24_Black,1,Jordan Lee,101 Harbor Road,Seattle,WA,98101,US',
            'WEB-9002,Unknown marketplace product,CUSTOM-44x30,1,Mina Chen,225 Lake Drive,Denver,CO,80202,US',
        ]);

        $preview = $this->postJson('/api/orders/imports/preview', ['csv' => $csv]);

        $preview->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.ready', 1)
            ->assertJsonPath('summary.needs_action', 1)
            ->assertJsonPath('rows.0.status', 'ready')
            ->assertJsonPath('rows.1.status', 'needs_action');

        $importId = $preview->json('import_id');

        $this->assertDatabaseHas('imports', [
            'id' => $importId,
            'total_rows' => 2,
            'valid_rows' => 1,
            'invalid_rows' => 1,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_id' => $importId,
            'row_number' => 2,
            'status' => 'ready',
        ]);

        $this->postJson('/api/orders/imports/'.$importId.'/commit')
            ->assertOk()
            ->assertJsonPath('created_orders', 1)
            ->assertJsonPath('import.status', 'partial');

        $this->assertDatabaseHas('orders', ['order_number' => 'WEB-9001', 'status' => 'verified']);
        $this->assertDatabaseHas('import_rows', ['import_id' => $importId, 'row_number' => 2, 'status' => 'committed']);
        $this->assertDatabaseHas('required_actions', ['type' => 'product_mapping_required']);
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

    public function test_orders_index_filters_sorts_and_paginates_server_side(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $this->actingAs($owner);

        $this->getJson('/api/orders?status=shipped&sort=order_number&direction=asc&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', 'AT-10038')
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.shipped', 1);

        $this->getJson('/api/orders?q=Kaitlyn&sort=customer_name&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_name', 'Kaitlyn Janigian');
    }

    public function test_orders_export_respects_active_filters(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $this->actingAs($owner);

        $response = $this->get('/api/orders/export?status=shipped');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('AT-10038', $csv);
        $this->assertStringNotContainsString('AT-10035', $csv);
    }

    public function test_owner_can_save_views_and_transition_order_lifecycle(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $order = Order::query()->where('order_number', 'AT-10035')->firstOrFail();
        $this->actingAs($owner);

        $this->postJson('/api/orders/saved-views', [
            'name' => 'Verified orders',
            'filters' => ['status' => 'verified'],
            'sort' => ['sort' => 'submitted_at', 'direction' => 'desc'],
        ])->assertCreated()
            ->assertJsonPath('name', 'Verified orders');

        $this->assertDatabaseHas('saved_views', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'scope' => 'orders',
            'name' => 'Verified orders',
        ]);

        $this->postJson('/api/orders/'.$order->uuid.'/transition', [
            'status' => 'submitted',
            'note' => 'Ready for production handoff.',
        ])->assertOk()
            ->assertJsonPath('order.status', 'submitted')
            ->assertJsonPath('event.from_status', 'verified')
            ->assertJsonPath('event.to_status', 'submitted');

        $order->refresh();
        $this->assertSame('submitted', $order->status);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'from_status' => 'verified',
            'to_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'event' => 'order.status_changed',
        ]);

        $this->postJson('/api/orders/'.$order->uuid.'/transition', [
            'status' => 'shipped',
        ])->assertUnprocessable();
    }

    public function test_owner_can_update_order_address_and_notes_with_audit(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $order = Order::query()->where('order_number', 'AT-10036')->firstOrFail();
        $this->actingAs($owner);

        $this->patchJson('/api/orders/'.$order->uuid.'/address', [
            'customer_name' => 'Kaitlyn J.',
            'shipping_service' => 'UPS Ground',
            'tracking_number' => '1ZTEST',
            'tracking_url' => 'https://tracking.example.test/1ZTEST',
            'shipping_address' => [
                'line1' => '500 New Market Street',
                'line2' => 'Suite 5',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78755',
                'country' => 'US',
            ],
        ])->assertOk()
            ->assertJsonPath('customer_name', 'Kaitlyn J.')
            ->assertJsonPath('shipping_address.line1', '500 New Market Street');

        $this->patchJson('/api/orders/'.$order->uuid.'/notes', [
            'notes' => 'Handle with revised packing checklist.',
        ])->assertOk()
            ->assertJsonPath('notes', 'Handle with revised packing checklist.');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'event' => 'order.address_updated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'event' => 'order.notes_updated',
        ]);
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

    public function test_owner_can_create_priced_manual_order_with_artwork(): void
    {
        Storage::fake('public');
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $this->actingAs($owner);

        $upload = $this->postJson('/api/uploads', [
            'collection' => 'artwork',
            'file' => UploadedFile::fake()->image('panel.png', 1200, 900),
        ])->assertCreated();

        $response = $this->postJson('/api/orders', [
            'order_number' => 'WEB-MANUAL-100',
            'customer_name' => 'Manual Customer',
            'shipping_service' => 'Standard Ground',
            'shipping_address' => [
                'line1' => '10 Wizard Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'items' => [[
                'product_variant_id' => $variant->id,
                'item_sku' => 'CLIENT-CANVAS-36',
                'quantity' => 2,
                'artwork_media_file_id' => $upload->json('id'),
                'options' => [
                    'print' => 'Mirror',
                    'hanging' => 'Security Hanger',
                ],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('order_number', 'WEB-MANUAL-100')
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('totals.subtotal_cents', 4180)
            ->assertJsonPath('items.0.product_code', 'AT-CV-36X24')
            ->assertJsonPath('items.0.options.artwork_media_file_id', $upload->json('id'));

        $this->assertDatabaseHas('media_files', [
            'id' => $upload->json('id'),
            'mediable_type' => Order::class,
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'to_status' => 'verified',
            'note' => 'Manual order created from wizard.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'order.created',
            'auditable_type' => Order::class,
        ]);
    }

    public function test_verified_order_can_create_stripe_payment_intent_and_confirm(): void
    {
        config([
            'services.stripe.secret_key' => 'sk_test_123',
            'services.stripe.webhook_secret' => 'whsec_test',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_01J123456789',
                'status' => 'requires_payment_method',
                'client_secret' => 'pi_test_01J123456789_secret',
            ], 200),
            'https://api.stripe.com/v1/payment_intents/pi_test_01J123456789' => Http::response([
                'id' => 'pi_test_01J123456789',
                'status' => 'succeeded',
            ], 200),
        ]);

        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $this->actingAs($owner);

        $order = $this->postJson('/api/orders', [
            'order_number' => 'WEB-PAY-101',
            'customer_name' => 'Payment Customer',
            'shipping_service' => 'Standard Ground',
            'shipping_address' => [
                'line1' => '10 New Lane',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'options' => [],
            ]],
        ])->assertCreated()
            ->json();

        $this->postJson('/api/orders/'.$order['uuid'].'/payment/intent', [])
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'unpaid')
            ->assertJsonPath('payment.provider_payment_intent_id', 'pi_test_01J123456789');

        $this->postJson('/api/orders/'.$order['uuid'].'/payment/confirm', [
            'payment_intent_id' => 'pi_test_01J123456789',
        ])->assertOk()
            ->assertJsonPath('result.payment_status', 'paid')
            ->assertJsonPath('result.order_status', 'submitted')
            ->assertJsonPath('result.payment_intent_status', 'succeeded');

        $this->assertDatabaseHas('orders', [
            'uuid' => $order['uuid'],
            'status' => 'submitted',
            'payment_status' => 'paid',
        ]);
    }

    public function test_webhook_marks_order_paid_and_emits_event(): void
    {
        config([
            'services.stripe.secret_key' => 'sk_test_456',
            'services.stripe.webhook_secret' => 'whsec_test',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_webhook_01J987654321',
                'status' => 'requires_payment_method',
                'client_secret' => 'pi_webhook_01J987654321_secret',
            ], 200),
        ]);

        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $this->actingAs($owner);

        $order = $this->postJson('/api/orders', [
            'order_number' => 'WEB-PAY-102',
            'customer_name' => 'Webhook Customer',
            'shipping_service' => 'Standard Ground',
            'shipping_address' => [
                'line1' => '20 New Lane',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'options' => [],
            ]],
        ])->assertCreated()
            ->json();

        $intent = $this->postJson('/api/orders/'.$order['uuid'].'/payment/intent', [])->json('payment');

        $eventPayload = [
            'id' => 'evt_01',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $intent['provider_payment_intent_id'],
                    'status' => 'succeeded',
                ],
            ],
        ];
        $rawPayload = json_encode($eventPayload);
        $secret = config('services.stripe.webhook_secret');
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, (string) $secret);

        $response = $this->withoutMiddleware()
            ->postJson('/api/payments/stripe/webhook', $eventPayload, ['stripe-signature' => "t=$timestamp,v1=$signature"]);

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('orders', [
            'uuid' => $order['uuid'],
            'status' => 'submitted',
            'payment_status' => 'paid',
        ]);
    }

    public function test_owner_can_create_draft_manual_order(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-FP-36X24-BLK')->firstOrFail();
        $this->actingAs($owner);

        $this->postJson('/api/orders', [
            'order_number' => 'WEB-DRAFT-100',
            'status' => 'draft',
            'customer_name' => 'Draft Customer',
            'shipping_address' => [
                'line1' => '20 Draft Lane',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78702',
                'country' => 'US',
            ],
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'options' => [],
            ]],
        ])->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('submitted_at', null)
            ->assertJsonPath('totals.subtotal_cents', 2490);
    }

    public function test_viewer_cannot_mutate_existing_orders(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $order = Order::query()->where('order_number', 'AT-10035')->firstOrFail();
        $this->actingAs($viewer);

        $this->patchJson('/api/orders/'.$order->uuid.'/notes', [
            'notes' => 'Viewer should not write.',
        ])->assertForbidden();

        $this->postJson('/api/orders/'.$order->uuid.'/transition', [
            'status' => 'submitted',
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

    public function test_owner_can_manage_product_catalog(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $this->actingAs($owner);

        $typeResponse = $this->postJson('/api/products/types', [
            'name' => 'Metal Print',
            'code' => 'METAL_PRINT',
            'description' => 'Aluminum print product line.',
            'image_url' => 'https://example.test/metal.jpg',
        ])->assertCreated()
            ->assertJsonPath('code', 'METAL_PRINT');

        $typeId = $typeResponse->json('id');

        $variantResponse = $this->postJson('/api/products/types/'.$typeId.'/variants', [
            'name' => 'Metal 24x16',
            'sku' => 'AT-MT-24X16',
            'layout' => 'Horizontal',
            'panel_count' => 1,
            'price_cents' => 4200,
            'image_sizes' => ['24" X 16"'],
            'panel_sizes' => ['24" X 16"'],
            'template_url' => '/templates/AT-MT-24X16.pdf',
        ])->assertCreated()
            ->assertJsonPath('sku', 'AT-MT-24X16');

        $optionResponse = $this->postJson('/api/products/types/'.$typeId.'/options', [
            'group' => 'Finish Options',
            'name' => 'Gloss',
            'code' => 'gloss',
            'price_cents' => 300,
        ])->assertCreated()
            ->assertJsonPath('code', 'gloss');

        $this->patchJson('/api/products/variants/'.$variantResponse->json('id'), [
            'name' => 'Metal 24x16 Satin',
            'sku' => 'AT-MT-24X16',
            'layout' => 'Horizontal',
            'panel_count' => 1,
            'price_cents' => 4400,
            'image_sizes' => ['24" X 16"'],
            'panel_sizes' => ['24" X 16"'],
            'template_url' => '/templates/AT-MT-24X16.pdf',
        ])->assertOk()
            ->assertJsonPath('price_cents', 4400);

        $this->patchJson('/api/products/options/'.$optionResponse->json('id'), [
            'group' => 'Finish Options',
            'name' => 'Satin',
            'code' => 'satin',
            'price_cents' => 250,
        ])->assertOk()
            ->assertJsonPath('code', 'satin');

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['code' => 'METAL_PRINT']);
    }

    public function test_viewer_cannot_manage_product_catalog(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $this->actingAs($viewer);

        $this->postJson('/api/products/types', [
            'name' => 'Blocked',
            'code' => 'BLOCKED',
        ])->assertForbidden();
    }

    public function test_catalog_media_upload_stores_metadata_and_file(): void
    {
        Storage::fake('public');
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $this->actingAs($owner);

        $response = $this->postJson('/api/uploads', [
            'collection' => 'product_image',
            'file' => UploadedFile::fake()->image('catalog.png', 640, 480),
        ]);

        $response->assertCreated()
            ->assertJsonPath('collection', 'product_image')
            ->assertJsonPath('mime_type', 'image/png')
            ->assertJsonStructure(['checksum', 'path', 'url', 'scan_state']);

        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_mapping_simulator_returns_ranked_matches_and_conflicts(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'atelier-canvas')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-FP-24X36-WAL')->firstOrFail();

        $secondary = ProductMapping::query()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'name' => 'Broad framed art fallback',
            'properties' => ['Frame' => 'Walnut'],
        ]);
        $secondary->rules()->create([
            'field' => 'name',
            'operator' => 'contains',
            'value' => 'Framed Art Print',
            'priority' => 10,
        ]);

        $this->actingAs($owner);

        $response = $this->postJson('/api/product-mappings/simulate', [
            'item_name' => 'Framed Art Print-Black / 36" x 24" / Football Stadium',
            'item_sku' => 'MGC-FP-36x24_Black-100',
        ]);

        $response->assertOk()
            ->assertJsonPath('matched_mapping.name', 'Black framed football art 36x24')
            ->assertJsonCount(2, 'candidates')
            ->assertJsonCount(2, 'conflicts');
    }

    public function test_mapping_creation_rejects_duplicate_rules_and_invalid_regex(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();

        $this->actingAs($owner);

        $this->postJson('/api/product-mappings', [
            'name' => 'Duplicate framed SKU',
            'product_variant_id' => $variant->id,
            'properties' => ['Frame' => 'Black Modern Thin'],
            'rules' => [[
                'field' => 'sku',
                'operator' => 'contains',
                'value' => 'MGC-FP-36x24_Black',
                'priority' => 80,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rules');

        $this->postJson('/api/product-mappings', [
            'name' => 'Invalid regex mapping',
            'product_variant_id' => $variant->id,
            'rules' => [[
                'field' => 'name',
                'operator' => 'regex',
                'value' => '/Custom Canvas(',
                'priority' => 20,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rules.0.value');
    }

    public function test_mapping_creation_resolves_required_actions_and_updates_order_items(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $action = RequiredAction::query()->where('type', 'product_mapping_required')->firstOrFail();
        $order = $action->order()->with('items')->firstOrFail();

        $this->assertSame('action_needed', $order->status);
        $this->assertNull($order->items->first()->product_variant_id);

        $this->actingAs($owner);

        $response = $this->postJson('/api/product-mappings', [
            'name' => 'Custom canvas 36x24 marketplace',
            'product_variant_id' => $variant->id,
            'properties' => ['Bleed' => 'Mirror'],
            'rules' => [[
                'field' => 'name',
                'operator' => 'contains',
                'value' => 'Custom Canvas Print',
                'priority' => 60,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('mapping.name', 'Custom canvas 36x24 marketplace')
            ->assertJsonPath('resolved_actions', 1);

        $action->refresh();
        $order->refresh()->load('items');

        $this->assertSame('resolved', $action->status);
        $this->assertSame('verified', $order->status);
        $this->assertSame($variant->id, $order->items->first()->product_variant_id);
        $this->assertSame($variant->sku, $order->items->first()->product_code);
    }

    public function test_required_action_workflow_comments_escalates_reopens_and_resolves_order(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();
        $action = RequiredAction::query()->where('type', 'product_mapping_required')->firstOrFail();
        $order = $action->order()->with('items')->firstOrFail();

        $this->actingAs($owner);

        $this->postJson('/api/required-actions/'.$action->id.'/comments', [
            'body' => 'Checking marketplace source data before assigning a production SKU.',
        ])->assertOk()
            ->assertJsonPath('comments.0.body', 'Checking marketplace source data before assigning a production SKU.');

        $this->postJson('/api/required-actions/'.$action->id.'/escalate', [
            'priority' => 'urgent',
            'comment' => 'Needs operations owner review.',
        ])->assertOk()
            ->assertJsonPath('status', 'escalated')
            ->assertJsonPath('priority', 'urgent')
            ->assertJsonPath('assigned_to_id', $owner->id);

        $this->postJson('/api/required-actions/'.$action->id.'/reopen', [
            'comment' => 'Operations confirmed it can be handled in the mapping queue.',
        ])->assertOk()
            ->assertJsonPath('status', 'open');

        $this->postJson('/api/required-actions/'.$action->id.'/resolve', [
            'resolution' => [
                'product_variant_id' => $variant->id,
                'note' => 'Custom canvas marketplace item maps to 36x24 stretched canvas.',
            ],
            'comment' => 'Resolved and ready for validation.',
        ])->assertOk()
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('resolution_payload.product_variant_id', $variant->id);

        $action->refresh()->load('comments');
        $order->refresh()->load('items');

        $this->assertSame('resolved', $action->status);
        $this->assertSame('verified', $order->status);
        $this->assertSame($variant->id, $order->items->first()->product_variant_id);
        $this->assertCount(4, $action->comments);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'required_action.resolved',
            'auditable_type' => RequiredAction::class,
            'auditable_id' => $action->id,
        ]);
    }

    public function test_address_required_action_resolution_updates_order_and_unblocks_it(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $order = Order::query()->where('order_number', 'AT-10035')->firstOrFail();
        $order->update(['status' => 'action_needed']);

        $action = RequiredAction::query()->create([
            'tenant_id' => $owner->tenant_id,
            'order_id' => $order->id,
            'status' => 'open',
            'type' => 'address_error',
            'title' => 'Shipping address needs correction',
            'description' => 'Postal validation rejected the destination address.',
            'payload' => ['customer_name' => $order->customer_name],
            'last_activity_at' => now(),
        ]);

        $this->actingAs($owner);

        $this->postJson('/api/required-actions/'.$action->id.'/resolve', [
            'resolution' => [
                'customer_name' => 'Avery Brooks',
                'shipping_address' => [
                    'line1' => '500 Market Street',
                    'line2' => 'Suite 9',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postal_code' => '94105',
                    'country' => 'US',
                ],
            ],
            'comment' => 'Address corrected from customer confirmation.',
        ])->assertOk()
            ->assertJsonPath('status', 'resolved');

        $order->refresh();

        $this->assertSame('verified', $order->status);
        $this->assertSame('Avery Brooks', $order->customer_name);
        $this->assertSame('500 Market Street', $order->shipping_address['line1']);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'required_action.resolved',
            'auditable_id' => $action->id,
        ]);
    }

    public function test_viewer_cannot_manage_required_actions(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $action = RequiredAction::query()->where('type', 'product_mapping_required')->firstOrFail();

        $this->actingAs($viewer);

        $this->getJson('/api/required-actions')->assertForbidden();
        $this->postJson('/api/required-actions/'.$action->id.'/resolve', [
            'resolution' => ['note' => 'viewer attempt'],
        ])->assertForbidden();
    }

    public function test_owner_can_update_and_delete_product_mapping(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'selin@example.test')->firstOrFail();
        $mapping = ProductMapping::query()->where('name', 'Black framed football art 36x24')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-24X16')->firstOrFail();

        $this->actingAs($owner);

        $this->patchJson('/api/product-mappings/'.$mapping->id, [
            'name' => 'Updated compact canvas mapping',
            'product_variant_id' => $variant->id,
            'properties' => ['Bleed' => 'Gallery Wrap'],
            'rules' => [[
                'field' => 'sku',
                'operator' => 'starts_with',
                'value' => 'UNIQUE-CANVAS-24',
                'priority' => 30,
            ]],
        ])->assertOk()
            ->assertJsonPath('mapping.name', 'Updated compact canvas mapping')
            ->assertJsonPath('mapping.rules.0.operator', 'starts_with');

        $this->deleteJson('/api/product-mappings/'.$mapping->id)->assertOk();

        $this->assertDatabaseMissing('product_mappings', ['id' => $mapping->id]);
        $this->assertDatabaseMissing('mapping_rules', ['product_mapping_id' => $mapping->id]);
    }

    public function test_viewer_cannot_manage_product_mappings(): void
    {
        $this->seed();
        $viewer = User::query()->where('email', 'viewer@example.test')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'AT-CV-36X24')->firstOrFail();

        $this->actingAs($viewer);

        $this->getJson('/api/product-mappings')->assertForbidden();
        $this->postJson('/api/product-mappings', [
            'name' => 'Blocked mapping',
            'product_variant_id' => $variant->id,
            'rules' => [[
                'field' => 'name',
                'operator' => 'contains',
                'value' => 'Blocked',
                'priority' => 10,
            ]],
        ])->assertForbidden();
    }
}
