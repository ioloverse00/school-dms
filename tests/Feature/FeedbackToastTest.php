<?php

namespace Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FeedbackToastTest extends TestCase
{
    public function test_flash_feedback_is_escaped_and_status_codes_are_readable(): void
    {
        session()->flash('success', '<script>alert(1)</script>');
        session()->flash('status', 'profile-updated');
        $html = view('partials.feedback-toasts', ['errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Profile updated successfully.', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    public function test_named_validation_errors_produce_persistent_error_toast(): void
    {
        $errors = (new ViewErrorBag)->put('updatePassword', new MessageBag(['password' => 'Invalid password.']));
        $html = view('partials.feedback-toasts', ['errors' => $errors])->render();
        $this->assertStringContainsString('data-tone="danger"', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }
}
