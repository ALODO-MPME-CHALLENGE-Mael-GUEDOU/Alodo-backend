<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswersRequest extends FormRequest
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
        // define the validation rules for the request
        $rules = [
            'diagnostic_id' => ['required', 'integer', 'exists:diagnostics,id'],
            'status' => ['required', Rule::in(['pending', 'completed'])],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*' => ['required', 'array'],
            'responses.*.question_id' => ['required', 'integer', 'distinct', 'exists:questions,id'],
            'responses.*.valeur' => ['required'],
        ];

        $responses = $this->input('responses', []);
        // check if responses is an array and apply additional validation rules
        if (! is_array($responses)) {
            return $rules;
        }

        $questionIds = [];

        foreach ($responses as $response) {
            if (
                is_array($response) &&
                isset($response['question_id']) &&
                is_scalar($response['question_id']) && // check if question_id is a scalar value(simple type)
                ctype_digit((string) $response['question_id']) // check if question_id is a digit(positive integer)
            ) {
                $questionIds[] = $response['question_id'];
            }
        }
        // retrieve the questions from the database based on the provided question_ids
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        foreach ($responses as $index => $response) {
            // check if the response is an array and contains a valid question_id
            if (
                ! is_array($response) ||
                ! isset($response['question_id']) ||
                ! is_scalar($response['question_id'])
            ) {
                continue;
            }

            $question = $questions->get($response['question_id']);
            if (! $question) {
                continue;
            }

            $field = "responses.$index.valeur";
            $allowedValues = array_column($question->options ?? [], 'value');

            $rules[$field] = match ($question->type) {
                'unique_choice' => ['required', 'string', Rule::in($allowedValues)],
                'multiple_choice' => ['required', 'array', 'list', 'min:1'],
                'text' => ['required', 'string', 'max:5000'],
                'number' => ['required', 'numeric'],
                default => ['prohibited'],
            };

            if ($question->type === 'multiple_choice') {
                $rules["$field.*"] = ['required', 'string', 'distinct', Rule::in($allowedValues)];
            }
        }

        return $rules;
    }
}
