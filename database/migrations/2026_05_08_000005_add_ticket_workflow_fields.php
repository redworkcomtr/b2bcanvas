<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->foreignId('assigned_to_id')->nullable()->after('order_id')->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal')->after('status');
            $table->timestamp('last_read_at')->nullable()->after('last_activity_at');
            $table->timestamp('resolved_at')->nullable()->after('last_read_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
        });

        Schema::table('issue_comments', function (Blueprint $table): void {
            $table->boolean('internal')->default(false)->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('issue_comments', function (Blueprint $table): void {
            $table->dropColumn('internal');
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to_id']);
            $table->dropColumn(['assigned_to_id', 'priority', 'last_read_at', 'resolved_at', 'closed_at']);
        });
    }
};
