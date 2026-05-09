<?php

namespace Database\Seeders;

use App\Models\Issue;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\ProductMapping;
use App\Models\ProductOption;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\SavedView;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'atelier-canvas'],
            [
                'name' => 'Atelier Canvas Supply',
                'support_email' => 'support@ateliercanvas.test',
                'settings' => ['currency' => 'USD', 'timezone' => 'America/New_York'],
            ],
        );

        $seedSubscriptions = function (User $user) use ($tenant): void {
            foreach ([
                'ORDER_SHIPPED',
                'ORDER_ACTION_NEEDED',
                'ORDER_ISSUE_COMMENT_ADDED',
                'ORDER_VALIDATION_FAILED',
                'ORDER_PAYMENT_COMPLETED',
            ] as $index => $event) {
                NotificationSubscription::query()->create([
                    'user_id' => $user->id,
                    'event' => $event,
                    'email' => $tenant->support_email,
                    'is_subscribed' => $index !== 3,
                ]);
            }
        };

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Selin Morgan',
            'email' => 'selin@example.test',
            'role' => 'owner',
            'active' => true,
        ]);

        $seedSubscriptions($user);

        collect([
            ['Ozan Kaya', 'operations@example.test', 'operations', true],
            ['Mina Support', 'support@example.test', 'support', true],
            ['Vera Viewer', 'viewer@example.test', 'viewer', true],
            ['Pending Partner', 'pending@example.test', 'viewer', false],
        ])->each(function (array $data) use ($tenant, $user, $seedSubscriptions): void {
            $teamUser = User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $data[0],
                'email' => $data[1],
                'role' => $data[2],
                'active' => $data[3],
                'invited_at' => $data[3] ? null : now()->subDay(),
            ]);

            $seedSubscriptions($teamUser);

            if (! $teamUser->active) {
                UserInvite::query()->create([
                    'tenant_id' => $tenant->id,
                    'invited_by_id' => $user->id,
                    'email' => $teamUser->email,
                    'role' => $teamUser->role,
                    'token' => Str::random(64),
                    'status' => 'pending',
                    'expires_at' => now()->addDays(6),
                ]);
            }
        });

        $canvas = ProductType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Stretched Canvas',
            'code' => 'CANVAS',
            'description' => 'Gallery-grade stretched canvas on a 1.25 inch stretcher bar with multiple panel layouts.',
            'image_url' => 'https://images.unsplash.com/photo-1579762593175-20226054cad0?auto=format&fit=crop&w=1200&q=80',
        ]);

        $framed = ProductType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Framed Art Print',
            'code' => 'FRAMED_PRINT',
            'description' => 'Framed fine art print variants with modern wood frame colors and production templates.',
            'image_url' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=1200&q=80',
        ]);

        $variants = collect([
            [$canvas, 'Canvas 1 Horizontal 24" X 16"', 'AT-CV-24X16', 'Horizontal', 1264, ['24" X 16"'], ['24" X 16"']],
            [$canvas, 'Canvas 1 Horizontal 36" X 24"', 'AT-CV-36X24', 'Horizontal', 1890, ['36" X 24"'], ['36" X 24"']],
            [$canvas, 'Canvas 3 Horizontal 72" X 48"', 'AT-CV-72X48-3P', 'Horizontal', 6200, ['72" X 48"'], ['24" X 48"', '24" X 48"', '24" X 48"']],
            [$framed, 'Framed Print 1 Horizontal 36" X 24" Black', 'AT-FP-36X24-BLK', 'Horizontal', 2490, ['36" X 24"'], ['36" X 24"']],
            [$framed, 'Framed Print 1 Vertical 24" X 36" Walnut', 'AT-FP-24X36-WAL', 'Vertical', 2490, ['24" X 36"'], ['24" X 36"']],
        ])->map(fn (array $variant): ProductVariant => ProductVariant::query()->create([
            'product_type_id' => $variant[0]->id,
            'name' => $variant[1],
            'sku' => $variant[2],
            'layout' => $variant[3],
            'panel_count' => str_contains($variant[2], '3P') ? 3 : 1,
            'price_cents' => $variant[4],
            'image_sizes' => $variant[5],
            'panel_sizes' => $variant[6],
            'template_url' => '/templates/'.$variant[2].'.pdf',
        ]));

        foreach ([
            ['Print Options', 'Mirror', 'mirror', 0],
            ['Print Options', 'Mirror With Blur', 'mirror_blur', 0],
            ['Print Options', 'Gallery Wrap', 'gallery_wrap', 0],
            ['Hanging Options', 'None', 'hanger_none', 0],
            ['Hanging Options', 'Security Hanger', 'security_hanger', 200],
            ['Hanging Options', 'Wire Hanger', 'wire_hanger', 200],
        ] as $option) {
            ProductOption::query()->create([
                'product_type_id' => $canvas->id,
                'group' => $option[0],
                'name' => $option[1],
                'code' => $option[2],
                'price_cents' => $option[3],
            ]);
        }

        $mapping = ProductMapping::query()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variants[3]->id,
            'name' => 'Black framed football art 36x24',
            'properties' => ['Frame' => 'Black Modern Thin'],
        ]);
        $mapping->rules()->createMany([
            ['field' => 'sku', 'operator' => 'contains', 'value' => 'MGC-FP-36x24_Black', 'priority' => 80],
            ['field' => 'name', 'operator' => 'contains', 'value' => 'Framed Art Print-Black / 36" x 24"', 'priority' => 40],
        ]);

        $orders = collect([
            ['AT-10035', 'verified', 'Taylor Nitterhouse', 'Standard Ground', $variants[4], 'Vintage World Map Personalized Push Pin Print', ['frame' => 'walnut', 'pushpin-color' => 'gold-silver']],
            ['AT-10036', 'in_production', 'Kaitlyn Janigian', 'Standard Ground', $variants[1], 'Mountain Lake Canvas Wall Art', ['bleed' => 'mirror', 'hanger' => 'none']],
            ['AT-10037', 'action_needed', 'Derek Troxler', 'Standard Shipping', null, 'Custom Canvas Print, Personalized Photo Wall Art', []],
            ['AT-10038', 'shipped', 'Milana Volkova', 'Amazon Shipping Standard', $variants[2], 'Three Panel Abstract Landscape Canvas', ['bleed' => 'gallery_wrap']],
        ])->map(function (array $data, int $index) use ($tenant): Order {
            $order = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'order_number' => $data[0],
                'status' => $data[1],
                'order_date' => now()->subDays($index + 1),
                'submitted_at' => now()->subDays($index + 1)->addHours(2),
                'shipped_at' => $data[1] === 'shipped' ? now()->subHours(6) : null,
                'shipping_service' => $data[3],
                'tracking_number' => $data[1] === 'shipped' ? '1Z999AA10123456784' : null,
                'tracking_url' => $data[1] === 'shipped' ? 'https://tracking.example.test/1Z999AA10123456784' : null,
                'customer_name' => $data[2],
                'shipping_address' => [
                    'line1' => ($index + 310).' Market Street',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '7870'.$index,
                    'country' => 'US',
                ],
                'totals' => ['subtotal_cents' => $data[4]?->price_cents ?? 0, 'currency' => 'USD'],
            ]);

            $order->items()->create([
                'product_variant_id' => $data[4]?->id,
                'item_name' => $data[5],
                'item_sku' => $data[4]?->sku,
                'quantity' => 1,
                'product_code' => $data[4]?->sku,
                'product_type' => $data[4]?->productType->name,
                'panel_summary' => $data[4] ? implode(', ', $data[4]->panel_sizes ?? []) : null,
                'design_images' => ['https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80'],
                'options' => $data[6],
            ]);

            return $order;
        });

        SavedView::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'scope' => 'orders',
            'name' => 'Action queue',
            'filters' => ['status' => 'action_needed', 'q' => ''],
            'sort' => ['sort' => 'submitted_at', 'direction' => 'desc'],
            'is_default' => true,
        ]);

        $orders->each(function (Order $order) use ($tenant, $user): void {
            OrderStatusEvent::query()->create([
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => $order->status,
                'note' => 'Seeded initial lifecycle state.',
                'metadata' => ['source' => 'seed'],
            ]);
        });

        $supportUser = User::query()->where('email', 'support@example.test')->first();

        $ticket = Issue::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $orders[1]->id,
            'assigned_to_id' => $supportUser?->id,
            'type' => 'ticket',
            'status' => 'in_progress',
            'priority' => 'high',
            'request_type' => 'Support',
            'reasons' => ['Never Received'],
            'description' => 'Customer has not received the package and asks for tracking status.',
            'contact' => ['name' => 'Selin Morgan', 'email' => $tenant->support_email],
            'total_notes_count' => 3,
            'unread_notes_count' => 1,
            'last_activity_at' => now()->subHours(8),
        ]);

        $ticket->comments()->createMany([
            [
                'user_id' => $user->id,
                'body' => 'Customer opened a tracking support request.',
                'attachments' => [],
                'internal' => false,
            ],
            [
                'user_id' => $supportUser?->id,
                'body' => 'Carrier scan is missing after production handoff. Monitoring for next scan.',
                'attachments' => [],
                'internal' => true,
            ],
            [
                'user_id' => $user->id,
                'body' => 'Customer asked for an updated ETA this morning.',
                'attachments' => [],
                'internal' => false,
            ],
        ]);

        $claim = Issue::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $orders[0]->id,
            'type' => 'claim',
            'status' => 'in_progress',
            'priority' => 'normal',
            'request_type' => 'Credit',
            'reasons' => ['Damaged In Transit'],
            'description' => 'Customer reports corner damage and requests a credit.',
            'contact' => ['name' => 'Selin Morgan', 'email' => $tenant->support_email],
            'total_notes_count' => 1,
            'unread_notes_count' => 1,
            'last_activity_at' => now()->subDay(),
        ]);

        $claim->comments()->create([
            'user_id' => $user->id,
            'body' => 'Customer reports corner damage and requests a credit.',
            'attachments' => [],
            'internal' => false,
        ]);

        RequiredAction::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $orders[2]->id,
            'status' => 'open',
            'type' => 'product_mapping_required',
            'title' => 'Product code mapping is required',
            'description' => 'No production SKU matched "Custom Canvas Print, Personalized Photo Wall Art".',
            'payload' => ['item_name' => 'Custom Canvas Print, Personalized Photo Wall Art', 'item_sku' => 'CUSTOM-36x24'],
            'last_activity_at' => now()->subHours(2),
        ]);
    }
}
