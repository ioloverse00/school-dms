<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_type_id')->constrained()->onDelete('cascade');
            $table->string('purpose');
            $table->integer('copies')->default(1);
            $table->enum('urgency', ['regular', 'urgent', 'very_urgent'])->default('regular');
            $table->enum('student_category', ['regular', 'graduating', 'alumni'])->default('regular');
            $table->integer('priority_score')->default(0);
            $table->enum('status', [
                'pending_requirements',
                'pending_verification',
                'processing',
                'ready_for_release',
                'completed',
                'rejected'
            ])->default('pending_requirements');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};