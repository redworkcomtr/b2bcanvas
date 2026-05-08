<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('required_actions', function (Blueprint $table): void {
            $table->string('priority')->default('normal')->after('status');
            $table->foreignId('assigned_to_id')->nullable()->after('order_id')->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('resolved_at');
            $table->json('resolution_payload')->nullable()->after('payload');
        });

        Schema::create('required_action_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('required_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_action_comments');

        Schema::table('required_actions', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to_id']);
            $table->dropColumn(['priority', 'assigned_to_id', 'escalated_at', 'resolution_payload']);
        });
    }
};
