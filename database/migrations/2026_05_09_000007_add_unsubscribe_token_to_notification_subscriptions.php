<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_subscriptions', function (Blueprint $table): void {
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('is_subscribed');
        });

        Schema::table('notification_subscriptions', function (Blueprint $table): void {
            $table->index('unsubscribe_token');
        });
    }

    public function down(): void
    {
        Schema::table('notification_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['unsubscribe_token']);
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn('unsubscribe_token');
        });
    }
};
