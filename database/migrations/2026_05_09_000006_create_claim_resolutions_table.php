<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision');
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 8)->default('USD');
            $table->string('finance_reference')->nullable();
            $table->text('production_outcome')->nullable();
            $table->text('notes')->nullable();
            $table->json('evidence_files')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_resolutions');
    }
};
