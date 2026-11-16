<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'category' => ['nullable', 'in:academic,certification'],
            'document_type_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('document_types', 'id')->where(function ($query) {
                if ($this->filled('category')) {
                    $query->where('category', $this->input('category'));
                }
            })],
            'purpose' => ['required', 'string', 'max:255'],
            'copies' => ['required', 'integer', 'min:1', 'max:' . config('document_requests.max_copies', 100)],
            'urgency' => ['required', 'in:regular,urgent,very_urgent'],
            'year_graduated' => [$this->user()->student_type === 'alumni' ? 'required' : 'nullable', 'digits:4', 'integer', 'min:1990', 'max:' . date('Y')],
            'proof_of_payment' => ['bail', 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240', function ($attribute, $value, $fail) {
                if (mb_strlen($value->getClientOriginalName()) > 255) {
                    $fail('The uploaded filename must not exceed 255 characters.');
                }
            }],
        ];
    }
}
