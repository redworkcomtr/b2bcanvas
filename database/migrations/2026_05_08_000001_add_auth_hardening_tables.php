<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('role');
            $table->timestamp('invited_at')->nullable()->after('active');
            $table->timestamp('last_login_at')->nullable()->after('invited_at');
        });

        Schema::create('user_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active', 'invited_at', 'last_login_at']);
        });
    }
};
