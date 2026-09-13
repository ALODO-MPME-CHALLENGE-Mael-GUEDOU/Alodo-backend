<?php

namespace Tests\Feature;

use App\Http\Requests\QuestionRequest;
use App\Models\Domain;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class QuestionOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only the tables needed here; unrelated diagnostic migrations are not exercised.
        (require database_path('migrations/2026_09_12_124209_create_domains_table.php'))->up();
        (require database_path('migrations/2026_09_12_124219_create_questions_table.php'))->up();
        Domain::create(['intitule' => 'Finance']);
    }

    private function payload(string $type = 'unique_choice'): array
    {
        return [
            'domain_id' => 1,
            'intitule' => 'Suivez-vous vos dépenses ?',
            'ordre' => 1,
            'type' => $type,
            'options' => [
                ['value' => 'oui', 'label' => 'Oui'],
                ['value' => 'non', 'label' => 'Non'],
            ],
        ];
    }

    private function validatePayload(array $payload): \Illuminate\Validation\Validator
    {
        $request = QuestionRequest::create('/', 'POST', $payload);

        return Validator::make($payload, $request->rules());
    }

    public function test_choice_options_are_validated_and_survive_database_round_trip(): void
    {
        foreach (['unique_choice', 'multiple_choice'] as $type) {
            $payload = $this->payload($type);
            $validated = $this->validatePayload($payload)->validate();
            $question = Domain::findOrFail(1)->questions()->create($validated);

            $this->assertSame($payload['options'], $question->fresh()->options);
        }
    }

    public function test_invalid_options_and_types_are_rejected(): void
    {
        $invalidOverrides = [
            ['type' => 'unknown'],
            ['options' => null],
            ['options' => []],
            ['options' => [['value' => 'oui', 'label' => 'Oui']]],
            ['options' => [['value' => 'oui', 'label' => 'Oui'], ['value' => 'oui', 'label' => 'Encore oui']]],
            ['options' => [['value' => false, 'label' => 'Non'], ['value' => 'oui', 'label' => 'Oui']]],
            ['options' => [['value' => '', 'label' => 'Non'], ['value' => 'oui', 'label' => 'Oui']]],
            ['options' => [['value' => 'non'], ['value' => 'oui', 'label' => 'Oui']]],
            ['options' => [['value' => 'non', 'label' => ' '], ['value' => 'oui', 'label' => 'Oui']]],
            ['options' => 'invalid'],
            ['options' => ['first' => ['value' => 'non', 'label' => 'Non'], 'second' => ['value' => 'oui', 'label' => 'Oui']]],
            ['ordre' => 0],
            ['domain_id' => 999],
        ];

        foreach ($invalidOverrides as $override) {
            $this->assertTrue($this->validatePayload(array_replace($this->payload(), $override))->fails(), json_encode($override));
        }
    }

    public function test_free_input_questions_accept_no_options_and_reject_populated_options(): void
    {
        foreach (['text', 'number'] as $type) {
            $payload = $this->payload($type);
            $this->assertTrue($this->validatePayload($payload)->fails());
            unset($payload['options']);
            $this->assertTrue($this->validatePayload($payload)->passes());
        }
    }
}
