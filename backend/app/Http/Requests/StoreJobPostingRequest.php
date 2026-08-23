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
                'requried',
                'string',
                'max:255'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'crew_type' => [
                'required',
                'in:normal:supervisor'
            ],

            'number_of_positions' => [
                'required',
                'integer',
                'in:normal,supervisor'
            ],

            'pay_rate' => [
                'nullable',
                'numeric',
                'min: 0'
            ],
        ];
    }
}
