<?php

namespace Tests\Feature;

use App\Http\Requests\QuestionRequest;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DomainSeeder;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_repeatable_and_preserves_diagnostic_progress(): void
    {
        $this->seed();
        $user = User::where('email', 'entreprise@alodo.test')->firstOrFail();
        $diagnostic = $user->diagnostics;
        $diagnostic->update(['status' => 'pending']);
        $user->update(['password' => 'PersonalPassword!']);
        $this->seed();

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('domains', 4);
        $this->assertDatabaseCount('questions', 12);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('diagnostics', 1);
        $this->assertSame('pending', $diagnostic->fresh()->status);
        $this->assertTrue(Hash::check('PersonalPassword!', $user->fresh()->password));
        $this->assertSame([1, 2, 3, 4], Domain::orderBy('ordre')->pluck('ordre')->all());
        Sanctum::actingAs($user);
        $this->getJson('/api/display/questionnaire')->assertOk()->assertJsonCount(4, 'data.domains');
    }

    public function test_general_questions_and_all_seeded_options_are_valid(): void
    {
        $this->seed();
        $general = Domain::where('intitule', 'Général')->firstOrFail();
        $this->assertFalse($general->is_scored);
        $this->assertSame(['text', 'unique_choice', 'unique_choice'], $general->questions()->orderBy('ordre')->pluck('type')->all());
        foreach (Question::all() as $question) {
            $data = $question->only(['domain_id', 'intitule', 'ordre', 'type', 'options']);
            $request = QuestionRequest::create('/', 'POST', $data);
            $this->assertTrue(Validator::make($data, $request->rules())->passes(), $question->intitule);
        }
    }

    public function test_legacy_question_is_updated_without_duplication(): void
    {
        $this->seed(DomainSeeder::class);
        $domain = Domain::where('intitule', 'Finance')->firstOrFail();
        $old = $domain->questions()->create(['intitule' => 'Comment estimez-vous ce qui reste après les dépenses de votre activité ?', 'ordre' => 1, 'type' => 'unique_choice']);
        $this->seed(QuestionSeeder::class);
        $this->assertDatabaseCount('questions', 12);
        $this->assertStringStartsWith('Pour votre principal produit', $old->fresh()->intitule);
    }

    public function test_recording_question_replaces_old_frequency_options_without_duplicates(): void
    {
        $this->seed(DomainSeeder::class);
        $domain = Domain::where('intitule', 'Comptabilité')->firstOrFail();
        $old = $domain->questions()->create([
            'intitule' => 'Quand votre activité fonctionne, à quelle fréquence enregistrez-vous les entrées et les sorties d’argent ?',
            'ordre' => 1,
            'type' => 'unique_choice',
            'options' => [['value' => 'quotidien', 'label' => 'Chaque jour']],
        ]);
        $this->seed(QuestionSeeder::class);
        $this->seed(QuestionSeeder::class);
        $this->assertDatabaseCount('questions', 12);
        $values = array_column($old->fresh()->options, 'value');
        $this->assertNotContains('quotidien', $values);
        $this->assertNotContains('hebdomadaire', $values);
        $this->assertContains('complet_a_jour', $values);
        $this->assertContains('pas_operations', $values);
    }

    public function test_rewording_does_not_change_questions_with_existing_answers(): void
    {
        $this->seed();
        $question = Domain::where('intitule', 'Comptabilité')->firstOrFail()->questions()->where('ordre', 1)->firstOrFail();
        $oldOptions = [['value' => 'quotidien', 'label' => 'Chaque jour']];
        $question->update(['options' => $oldOptions]);
        $diagnostic = User::where('email', 'entreprise@alodo.test')->firstOrFail()->diagnostics;
        $diagnostic->responses()->create(['question_id' => $question->id, 'valeur' => 'quotidien']);

        try {
            $this->seed(QuestionSeeder::class);
            $this->fail('An answered question must not be rewritten.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('possède déjà des réponses', $exception->getMessage());
        }

        $this->assertSame($oldOptions, $question->fresh()->options);
        $this->assertSame('quotidien', $diagnostic->responses()->firstOrFail()->valeur);
        $this->assertDatabaseCount('questions', 12);
    }

    public function test_domain_scoring_flags_are_corrected_on_reseed(): void
    {
        Domain::create(['intitule' => 'Général', 'ordre' => 1, 'is_scored' => true]);
        Domain::create(['intitule' => 'Finance', 'ordre' => 2, 'is_scored' => false]);
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('domains', 4);
        $this->assertFalse(Domain::where('intitule', 'Général')->firstOrFail()->is_scored);
        foreach (['Finance', 'Comptabilité', 'Commercial'] as $name) {
            $this->assertTrue(Domain::where('intitule', $name)->firstOrFail()->is_scored);
        }
    }

    public function test_existing_domain_order_is_preserved(): void
    {
        Domain::create(['intitule' => 'Existant', 'ordre' => 1]);
        $this->seed();
        $this->assertDatabaseHas('domains', ['intitule' => 'Existant', 'ordre' => 1]);
        $this->assertSame(5, Domain::distinct()->count('ordre'));
    }

    public function test_admin_cannot_create_two_domains_with_same_order(): void
    {
        $role = Role::create(['intitule' => 'admin']);
        Sanctum::actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->postJson('/api/store/domains', ['intitule' => 'Finance', 'ordre' => 1, 'is_scored' => true])->assertCreated();
        $this->postJson('/api/store/domains', ['intitule' => 'Commercial', 'ordre' => 1, 'is_scored' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('ordre');
        $this->assertDatabaseCount('domains', 1);
    }
}
