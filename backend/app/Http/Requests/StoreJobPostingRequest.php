<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'recruiter';
    }

    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'job_type' => [
                'required',
                'string',
                'max:100'
            ],

            'number_of_positions' => [
                'required',
                'integer',
                'min:1'
            ],

            'pay_rate' => [
                'nullable',
                'numeric',
                'min: 0'
            ],
        ];
    }
}
