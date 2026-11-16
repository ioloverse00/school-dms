<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->unique();
            $table->string('submission_hash', 64)->nullable();
        });

        Schema::create('document_status_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->json('snapshot');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_status_emails');
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropUnique(['submission_token']);
            $table->dropColumn(['submission_token', 'submission_hash']);
        });
    }
};
