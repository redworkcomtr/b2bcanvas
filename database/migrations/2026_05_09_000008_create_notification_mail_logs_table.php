<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_mail_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('notification_subscriptions')->nullOnDelete();
            $table->string('event');
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mail_logs');
    }
};
