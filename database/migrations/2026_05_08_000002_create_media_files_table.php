<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('mediable');
            $table->string('collection')->default('general');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('scan_state')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
