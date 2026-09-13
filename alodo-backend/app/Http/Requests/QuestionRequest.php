<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        return [
            'intitule' => ['required', 'string', 'max:255'],
            'domain_id' => ['required', 'integer', 'exists:domains,id'],
            'ordre' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(['unique_choice', 'multiple_choice', 'text', 'number'])],
            'options' => [
                'required_if:type,unique_choice,multiple_choice',
                'prohibited_unless:type,unique_choice,multiple_choice',
                'nullable', 'array', 'list', 'min:2',
            ],
            'options.*' => ['required', 'array:value,label'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:255'],
        ];
    }
}
