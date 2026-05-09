<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('support_email')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('client_admin')->after('email');
        });

        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('layout')->nullable();
            $table->unsignedInteger('panel_count')->default(1);
            $table->unsignedInteger('price_cents')->default(0);
            $table->json('image_sizes')->nullable();
            $table->json('panel_sizes')->nullable();
            $table->string('template_url')->nullable();
            $table->timestamps();
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('price_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('mapping_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('operator')->default('equals');
            $table->string('value');
            $table->unsignedInteger('priority')->default(10);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('not_required');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('shipping_service')->nullable();
            $table->string('customer_name');
            $table->json('shipping_address')->nullable();
            $table->json('totals')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('product_code')->nullable();
            $table->string('product_type')->nullable();
            $table->string('panel_summary')->nullable();
            $table->json('design_images')->nullable();
            $table->json('print_images')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('status')->default('preview');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->json('payload');
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('request_type')->nullable();
            $table->json('reasons')->nullable();
            $table->text('description');
            $table->json('contact')->nullable();
            $table->unsignedInteger('total_notes_count')->default(0);
            $table->unsignedInteger('unread_notes_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('required_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('type');
            $table->string('title');
            $table->text('description');
            $table->json('payload')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('email');
            $table->boolean('is_subscribed')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'event']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_subscriptions');
        Schema::dropIfExists('required_actions');
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('mapping_rules');
        Schema::dropIfExists('product_mappings');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_types');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('role');
        });

        Schema::dropIfExists('tenants');
    }
};
