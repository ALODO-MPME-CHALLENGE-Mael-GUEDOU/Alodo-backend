<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Tests\TestCase;

class RegistrationTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Entreprise test', 'email' => 'registration@example.test', 'password' => 'Password!2026'];
    }

    public function test_successful_registration_creates_account_diagnostic_and_token(): void
    {
        Role::create(['intitule' => 'user']);
        $this->postJson('/api/register', $this->payload())
            ->assertCreated()->assertJsonStructure(['data' => ['token', 'user' => ['id', 'role']]]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('diagnostics', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('diagnostics', ['status' => 'baseline']);
    }

    public function test_failure_during_diagnostic_creation_rolls_back_account(): void
    {
        $this->assertRegistrationRollsBack('eloquent.creating: '.Diagnostic::class);
    }

    public function test_failure_after_token_insertion_rolls_back_all_three_records(): void
    {
        $this->assertRegistrationRollsBack('eloquent.created: '.PersonalAccessToken::class);
    }

    private function assertRegistrationRollsBack(string $event): void
    {
        Role::create(['intitule' => 'user']);
        $this->withoutExceptionHandling();
        Event::listen($event, function (): void {
            throw new RuntimeException('Simulated registration failure');
        });

        try {
            $this->postJson('/api/register', $this->payload());
            $this->fail('Registration should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated registration failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('diagnostics', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('roles', 1);
    }
}
