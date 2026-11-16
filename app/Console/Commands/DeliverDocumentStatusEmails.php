<?php

namespace App\Console\Commands;

use App\Mail\DocumentStatusUpdated;
use App\Models\DocumentStatusEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DeliverDocumentStatusEmails extends Command
{
    protected $signature = 'notifications:deliver-email {--limit=50 : Maximum emails per run}';

    protected $description = 'Deliver persisted status emails and retry failures without changing request status';

    public function handle(): int
    {
        $ids = DocumentStatusEmail::whereNull('sent_at')->where('available_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('claimed_at')->orWhere('claimed_at', '<', now()->subMinutes(10)))
            // Preserve event order: an older deferred email must not follow a newer status email.
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('document_status_emails as earlier')
                    ->whereColumn('earlier.document_request_id', 'document_status_emails.document_request_id')
                    ->whereColumn('earlier.id', '<', 'document_status_emails.id')->whereNull('earlier.sent_at');
            })
            ->orderBy('id')->limit(max(1, min(500, (int) $this->option('limit'))))->pluck('id');
        $sent = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $token = (string) Str::uuid();
            $claimed = DocumentStatusEmail::whereKey($id)->whereNull('sent_at')->where('available_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('claimed_at')->orWhere('claimed_at', '<', now()->subMinutes(10)))
                ->update(['claim_token' => $token, 'claimed_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $email = DocumentStatusEmail::find($id);
            if (! $email) {
                continue;
            }
            try {
                Mail::to($email->recipient)->send(new DocumentStatusUpdated($email->requestSnapshot()));
                DocumentStatusEmail::whereKey($id)->where('claim_token', $token)
                    ->update(['sent_at' => now(), 'claim_token' => null, 'claimed_at' => null]);
                $sent++;
            } catch (Throwable) {
                $attempts = $email->attempts + 1;
                DocumentStatusEmail::whereKey($id)->where('claim_token', $token)->update([
                    'attempts' => $attempts, 'claim_token' => null, 'claimed_at' => null,
                    'available_at' => now()->addMinutes(min(360, 2 ** min($attempts, 8))),
                ]);
                Log::warning('Document status email delivery deferred.', ['delivery_id' => $id]);
                $failed++;
            }
        }
        $this->info("Sent: {$sent}; deferred: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
