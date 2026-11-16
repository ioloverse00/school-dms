<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\NewDocumentRequestNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SubmitDocumentRequest
{
    public function handle(User $user, array $data, UploadedFile $file): DocumentRequest
    {
        $hash = hash('sha256', json_encode([
            (int) $data['document_type_id'], $data['purpose'], (int) $data['copies'],
            $data['urgency'], (string) ($data['year_graduated'] ?? ''), hash_file('sha256', $file->getRealPath()),
        ], JSON_THROW_ON_ERROR));
        $existing = $this->existing($user, $data['submission_token'], $hash);
        if ($existing) {
            return $existing;
        }

        $type = DocumentType::findOrFail($data['document_type_id']);
        if ($type->alumni_only && $user->student_type !== 'alumni') {
            throw ValidationException::withMessages(['document_type_id' => 'This document is available only to alumni.']);
        }

        $disk = Storage::disk('documents');
        // Preselect the path so even a partial failed write can be compensated.
        $path = 'request_files/' . Str::uuid() . '.' . $file->guessExtension();
        try {
            if ($disk->putFileAs('request_files', $file, basename($path)) === false) {
                throw new RuntimeException('Upload storage failed.');
            }

            return DB::transaction(function () use ($user, $data, $type, $path, $hash, $file) {
                // Reload inside the transaction: a rolled-back model instance may retain dirty state.
                $user = User::lockForUpdate()->findOrFail($user->id);
                if ($type->alumni_only && $user->student_type !== 'alumni') {
                    throw ValidationException::withMessages(['document_type_id' => 'This document is available only to alumni.']);
                }
                $urgency = SystemSetting::get('urgency_level_values', ['regular' => 1, 'urgent' => 2, 'very_urgent' => 3]);
                $weights = SystemSetting::get('student_category_weights', ['regular' => 2, 'graduating' => 5, 'alumni' => 3]);
                $score = ($urgency[$data['urgency']] ?? 1) * SystemSetting::get('urgency_weight', 5)
                    + ($weights[$user->student_type] ?? 1) + $type->weight;

                if ($user->student_type === 'alumni') {
                    $user->update(['year_graduated' => $data['year_graduated']]);
                }
                $documentRequest = DocumentRequest::create([
                    'request_id' => 'REQ-' . date('Y') . '-' . strtoupper((string) Str::ulid()),
                    'submission_token' => $data['submission_token'],
                    'submission_hash' => $hash,
                    'user_id' => $user->id,
                    'document_type_id' => $type->id,
                    'purpose' => $data['purpose'],
                    'copies' => $data['copies'],
                    'urgency' => $data['urgency'],
                    'student_category' => $user->student_type,
                    'priority_score' => $score,
                    'status' => 'pending_verification',
                ]);
                RecordRequestEvent::record($documentRequest, 'submitted', $user);
                $documentRequest->requiredFiles()->create([
                    'file_type' => 'proof_of_payment', 'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
                $documentRequest->load(['user', 'documentType']);
                foreach (User::role(['admin', 'registrar'])->get() as $staff) {
                    // Database notifications commit or roll back with the request.
                    $staff->notify(new NewDocumentRequestNotification($documentRequest));
                }

                return $documentRequest;
            });
        } catch (Throwable $exception) {
            try {
                if (! $disk->delete($path)) {
                    Log::error('Request upload compensation failed.');
                }
            } catch (Throwable) {
                Log::error('Request upload compensation failed.');
            }
            // A concurrent duplicate may have committed while this transaction was inserting.
            if ($exception instanceof QueryException) {
                $existing = $this->existing($user, $data['submission_token'], $hash);
                if ($existing) {
                    return $existing;
                }
            }
            throw $exception;
        }
    }

    private function existing(User $user, string $token, string $hash): ?DocumentRequest
    {
        $request = DocumentRequest::where('submission_token', $token)->first();
        if ($request && ($request->user_id !== $user->id || ! hash_equals((string) $request->submission_hash, $hash))) {
            throw ValidationException::withMessages(['submission_token' => 'This form was already submitted. Open a new request form for a different request.']);
        }

        return $request;
    }
}
