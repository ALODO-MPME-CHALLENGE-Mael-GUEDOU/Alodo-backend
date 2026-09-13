<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\Domain;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $role = Role::firstOrCreate(['intitule' => 'user']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_questionnaire_groups_and_orders_questions_and_isolates_answers(): void
    {
        $user = $this->user();
        $diagnostic = Diagnostic::create(['user_id' => $user->id, 'status' => 'pending']);
        $otherDiagnostic = Diagnostic::create(['user_id' => $this->user()->id]);
        $finance = Domain::create(['intitule' => 'Finance', 'ordre' => 2]);
        $commercial = Domain::create(['intitule' => 'Commercial', 'ordre' => 1]);
        Domain::create(['intitule' => 'Vide', 'ordre' => 3]);
        $later = $finance->questions()->create(['intitule' => 'Plus tard', 'ordre' => 2, 'type' => 'text']);
        $first = $finance->questions()->create([
            'intitule' => 'Première', 'ordre' => 1, 'type' => 'unique_choice',
            'options' => [['value' => 'oui', 'label' => 'Oui'], ['value' => 'non', 'label' => 'Non']],
        ]);
        $commercial->questions()->create(['intitule' => 'Ventes', 'ordre' => 1, 'type' => 'number']);
        $diagnostic->responses()->create(['question_id' => $first->id, 'valeur' => 'oui']);
        $otherDiagnostic->responses()->create(['question_id' => $later->id, 'valeur' => 'Privé']);
        Sanctum::actingAs($user);

        $this->getJson('/api/display/questionnaire')
            ->assertOk()
            ->assertJsonPath('data.diagnostic.id', $diagnostic->id)
            ->assertJsonCount(2, 'data.domains')
            ->assertJsonPath('data.domains.1.id', $finance->id)
            ->assertJsonPath('data.domains.1.ordre', 2)
            ->assertJsonPath('data.domains.0.id', $commercial->id)
            ->assertJsonCount(2, 'data.domains.1.questions')
            ->assertJsonPath('data.domains.1.questions.0.id', $first->id)
            ->assertJsonPath('data.domains.1.questions.0.options.0.value', 'oui')
            ->assertJsonPath('data.domains.1.questions.0.valeur', 'oui')
            ->assertJsonPath('data.domains.1.questions.1.valeur', null)
            ->assertJsonPath('data.domains.0.questions.0.valeur', null)
            ->assertJsonMissing(['valeur' => 'Privé']);
    }

    public function test_completed_diagnostic_keeps_the_same_response_structure(): void
    {
        $user = $this->user();
        Diagnostic::create(['user_id' => $user->id, 'status' => 'completed', 'completed_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson('/api/display/questionnaire')->assertOk()
            ->assertJsonPath('data.diagnostic.status', 'completed')
            ->assertJsonStructure(['data' => ['diagnostic' => ['id', 'status', 'completed_at'], 'domains']]);
    }

    public function test_missing_diagnostic_returns_not_found(): void
    {
        Sanctum::actingAs($this->user());
        $this->getJson('/api/display/questionnaire')->assertNotFound();
    }

    public function test_guest_cannot_read_questionnaire(): void
    {
        $this->getJson('/api/display/questionnaire')->assertUnauthorized();
    }
}
