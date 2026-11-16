<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Safe on fresh installations and installations that have not run this historical migration.
        // Keep existing catalog entries and assigned courses; do not normalize or null user data.
        app(\App\Services\PreserveProgramCatalog::class)->handle();
    }

    public function down(): void
    {
        // Catalog preservation does not have a destructive reverse operation.
    }
};