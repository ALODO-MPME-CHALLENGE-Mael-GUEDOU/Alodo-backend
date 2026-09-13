<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;
use RuntimeException;

class DomainSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Général' => ['Etat général de l’entreprise.', false],
            'Finance' => ['Trésorerie, marges et anticipation des dépenses.', true],
            'Comptabilité' => ['Enregistrement des opérations et conservation des justificatifs.', true],
            'Commercial' => ['Suivi des ventes et des relations clients.', true],
        ] as $name => [$description, $isScored]) {
            $domain = Domain::where('intitule', $name)->first();

            if ($domain) {
                $domain->update(['is_scored' => $isScored]);

                continue;
            }

            $order = collect(range(1, 8))->diff(Domain::pluck('ordre'))->first();

            if ($order === null) {
                throw new RuntimeException('Aucun ordre disponible entre 1 et 8 pour créer le domaine '.$name);
            }

            Domain::create(['intitule' => $name, 'description' => $description, 'ordre' => $order, 'is_scored' => $isScored]);
        }
    }
}
