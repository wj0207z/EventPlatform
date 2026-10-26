<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string'
            ],
            'job_type' => [
                'sometimes',
                'required',
                'string',
                'max:100'
            ],
            'number_of_positions' => [
                'sometimes',
                'required',
                'integer',
                'min:1'
            ],
            'pay_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0'
            ],
            'status' => [
                'sometimes',
                'required',
                'in:open,closed,cancelled'
            ],
        ];
    }
}
