<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDemoData extends Command
{
    protected $signature   = 'db:clean-demo';
    protected $description = 'Truncate student accounts and document requests, then reset auto-increments.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo commands are restricted to local and testing environments.');
            return self::FAILURE;
        }

        if (!$this->confirm('This will DELETE all students and document requests. Continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear request-related tables
        DB::table('notifications')->truncate();
        $this->line('  notifications truncated');

        DB::table('required_files')->truncate();
        $this->line('  required_files truncated');

        DB::table('document_requests')->truncate();
        $this->line('  document_requests truncated');

        // Remove student users and their role assignments
        $studentIds = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'student')
            ->pluck('users.id');

        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', $studentIds)
            ->delete();

        DB::table('users')->whereIn('id', $studentIds)->delete();
        $this->line("  {$studentIds->count()} student account(s) deleted");

        // Reset auto-increments
        DB::statement('ALTER TABLE notifications      AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE required_files     AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE document_requests  AUTO_INCREMENT = 1;');
        $this->line('  Auto-increments reset');

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info('Done. Database is clean and ready for production.');

        return self::SUCCESS;
    }
}
