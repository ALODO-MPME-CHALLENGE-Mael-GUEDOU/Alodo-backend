<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ([
            ['admin@alodo.test', 'Administrateur démo', 'admin'],
            ['entreprise@alodo.test', 'Entreprise démo', 'user'],
        ] as [$email, $name, $role]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'role_id' => Role::where('intitule', $role)->firstOrFail()->id,
                'password' => 'AlodoDemo!2026',
            ]);

            if ($role === 'user' && $user->role->intitule === 'user') {
                $user->diagnostics()->firstOrCreate([], ['status' => 'baseline']);
            }
        }
    }
}
