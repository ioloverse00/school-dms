<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action', 40);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('old_remarks')->nullable();
            $table->text('new_remarks')->nullable();
            $table->timestamps();
            $table->index(['document_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_events');
    }
};
