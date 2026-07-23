<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * No authentication in this interview project, so always allow.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /*
         * TODO (Alejandro): Add validation rules for this request.
         *
         * Requirements:
         * - category is required and must be one of: need, want, debt_savings
         * - reviewed is required and must be a boolean
         *
         * Look up Laravel Form Request validation and the "in" / "boolean" rules.
         *
         * Do not paste a finished solution under this comment.
         * An empty rules array is only a temporary placeholder.
         */
        return [
            'category' => ['required', Rule::in(['need', 'want', 'debt_savings'])],
            'reviewed' => ['required', 'boolean'],
        ];
    }
}
