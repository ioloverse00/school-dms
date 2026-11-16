<?php

namespace App\Support;

use App\Models\DocumentRequest;
use Illuminate\Validation\ValidationException;

class RequestEditToken
{
    public const MESSAGE = 'This request changed, or this form is no longer current. Review the latest values below before saving again.';

    public static function for(DocumentRequest $request): string
    {
        // The event ID also detects change-and-revert within the same timestamp second.
        $snapshot = json_encode([
            (int) $request->id,
            $request->status,
            $request->remarks,
            $request->getRawOriginal('archived_at'),
            $request->getRawOriginal('updated_at'),
            (int) $request->events()->max('id'),
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $snapshot, (string) config('app.key'));
    }

    public static function assertCurrent(DocumentRequest $request, string $token): void
    {
        if (! hash_equals(self::for($request), $token)) {
            throw ValidationException::withMessages(['edit_token' => self::MESSAGE]);
        }
    }
}
