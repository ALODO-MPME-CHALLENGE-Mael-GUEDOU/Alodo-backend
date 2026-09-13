<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            'Général' => [
                [
                    'general_activite',
                    'Décrivez votre activité principale : que vendez-vous ou quel service proposez-vous, et à quels clients ?',
                    null,
                    null,
                ],
                [
                    'general_frein',
                    'Aujourd’hui, quelle difficulté freine le plus le développement de votre activité ?',
                    null,
                    [
                        'clients' => 'Trouver suffisamment de clients ou de débouchés',
                        'depenses_courantes' => 'Disposer d’argent pour les dépenses courantes',
                        'investissement' => 'Obtenir un financement pour un investissement',
                        'paiements_clients' => 'Être payé à temps par les clients',
                        'approvisionnements' => 'Faire face au coût ou à la disponibilité des approvisionnements',
                        'competences' => 'Trouver les compétences nécessaires ou organiser le travail',
                        'infrastructures' => 'Faire face aux coupures d’électricité, aux problèmes de transport ou de connexion',
                        'demarches' => 'Comprendre ou effectuer les démarches administratives',
                        'autre' => 'Une autre difficulté',
                        'aucune' => 'Je n’identifie pas de difficulté majeure actuellement',
                        'inconnue' => 'Je ne sais pas quelle difficulté est prioritaire',
                    ],
                ],
                [
                    'general_formalisation',
                    'Où en êtes-vous dans les démarches d’enregistrement officiel de votre entreprise ?',
                    null,
                    [
                        'non_commence' => 'Je n’ai pas commencé les démarches',
                        'en_cours' => 'Les démarches sont en cours',
                        'enregistree' => 'Mon entreprise est enregistrée et je peux retrouver les documents correspondants',
                        'documents_indisponibles' => 'Mon entreprise est enregistrée, mais je ne peux pas retrouver les documents correspondants',
                        'incertain' => 'Je ne sais pas si les démarches ont été finalisées',
                    ],
                ],
            ],
            'Finance' => [
                [
                    'finance_marge',
                    'Pour votre principal produit ou service, comment savez-vous ce qui reste sur le prix de vente après les coûts nécessaires pour le fournir ?',
                    'Comment estimez-vous ce qui reste après les dépenses de votre activité ?',
                    [
                        'inconnu' => 'Je ne sais pas ce qui reste',
                        'estimation' => 'Je fais une estimation de tête',
                        'calcul' => 'Je calcule ce qui reste à partir des coûts que j’ai notés',
                        'actualisation' => 'Je calcule à partir des coûts notés et je mets ce calcul à jour lorsque mes coûts ou mes prix changent',
                        'pas_de_vente' => 'Je n’ai pas encore réalisé de vente',
                    ],
                ],
                [
                    'finance_anticipation',
                    'Comment préparez-vous le paiement des dépenses prévues pour les prochaines semaines ?',
                    'Comment préparez-vous les dépenses à venir ?',
                    [
                        'au_moment' => 'Je cherche l’argent au moment où la dépense arrive',
                        'memoire' => 'Je prévois les principales dépenses de tête',
                        'calendrier' => 'Je note les montants et les dates des dépenses à venir',
                        'prevision' => 'Je compare ces dépenses à l’argent disponible et aux encaissements attendus pour repérer un manque éventuel',
                    ],
                ],
                [
                    'finance_creances',
                    'Lorsque des clients vous paient plus tard, comment suivez-vous les sommes dues, même si tout est réglé aujourd’hui ?',
                    ['Comment suivez-vous les sommes que vos clients vous doivent ?', 'Lorsque des clients vous paient plus tard, comment suivez-vous ce qu’ils vous doivent ?'],
                    [
                        'memoire' => 'Je me fie principalement à ma mémoire',
                        'montants' => 'Je note les clients et les montants dus',
                        'echeances' => 'Je note les clients, les montants dus et les dates prévues de paiement',
                        'relances' => 'Je note les montants et les échéances, puis je vérifie régulièrement les paiements et relance les retards',
                        'aucune_experience_credit' => 'Je prévois d’accepter des paiements différés, mais je n’ai pas encore eu à les suivre',
                        'paiement_immediat' => 'Mes clients paient toujours immédiatement ; je ne vends pas à crédit',
                    ],
                ],
            ],
            'Comptabilité' => [
                [
                    'comptabilite_enregistrement',
                    'Quelle description correspond le mieux à votre enregistrement des entrées et sorties d’argent de l’activité ?',
                    ['À quelle fréquence enregistrez-vous vos recettes et dépenses ?', 'Quand votre activité fonctionne, à quelle fréquence enregistrez-vous les entrées et les sorties d’argent ?'],
                    [
                        'jamais' => 'Je ne les enregistre pas',
                        'partiel' => 'J’enregistre seulement certaines opérations',
                        'complet_irregulier' => 'Je cherche à tout enregistrer, mais les mises à jour sont irrégulières et certaines opérations restent à rattraper',
                        'complet_a_jour' => 'J’enregistre toutes les opérations selon une routine adaptée à mon activité, sans accumulation de retard',
                        'pas_operations' => 'L’activité n’a encore enregistré aucune entrée ni sortie d’argent',
                    ],
                ],
                [
                    'comptabilite_tracabilite',
                    'Si vous devez vérifier une vente ou une dépense récente, comment retrouvez-vous sa trace ?',
                    'Comment conservez-vous les justificatifs de vos opérations ?',
                    [
                        'aucune' => 'Je n’ai généralement aucune trace',
                        'difficile' => 'Certaines traces existent, mais elles sont difficiles à retrouver',
                        'retrouvable' => 'Je retrouve généralement une facture, un reçu, un message ou une note',
                        'rapprochement' => 'Je peux retrouver cette trace et la rapprocher de mon enregistrement de l’opération',
                        'pas_operations' => 'L’activité n’a encore enregistré aucune vente ni dépense',
                    ],
                ],
                [
                    'comptabilite_verification',
                    'Comment vérifiez-vous que vos enregistrements correspondent aux sommes réellement encaissées et dépensées ?',
                    'Vérifiez-vous les montants enregistrés avec vos justificatifs et encaissements ?',
                    [
                        'jamais' => 'Je ne fais pas de vérification',
                        'incident' => 'Je vérifie uniquement lorsqu’un problème apparaît',
                        'comparaison' => 'Je compare régulièrement mes enregistrements à la caisse, aux relevés ou aux justificatifs disponibles',
                        'correction' => 'Je fais cette comparaison et je recherche les écarts pour corriger mes enregistrements',
                        'pas_operations' => 'L’activité n’a encore enregistré aucune entrée ni sortie d’argent',
                    ],
                ],
            ],
            'Commercial' => [
                [
                    'commercial_evolution_ventes',
                    'Comment savez-vous si vos ventes augmentent ou diminuent ?',
                    'Comment suivez-vous l’évolution de vos ventes ?',
                    [
                        'impression' => 'Je me fie principalement à mon impression',
                        'totaux' => 'Je consulte des totaux de ventes, sans comparer régulièrement les périodes',
                        'comparaison' => 'Je compare les ventes entre périodes comparables',
                        'analyse' => 'Je fais cette comparaison et je recherche ce qui explique les différences',
                        'trop_recent' => 'Mon entreprise est trop récente',
                        'pas_de_vente' => 'Je n’ai pas encore réalisé de vente',
                    ],
                ],
                [
                    'commercial_acquisition',
                    'Comment identifiez-vous les moyens qui vous apportent des clients ?',
                    'Comment suivez-vous les demandes de vos clients ?',
                    [
                        'inconnu' => 'Je ne sais pas vraiment comment les clients me trouvent',
                        'echanges' => 'J’en ai une idée grâce aux échanges avec les clients',
                        'suivi' => 'Je note régulièrement comment les nouveaux clients m’ont connu',
                        'comparaison' => 'Je compare les résultats des moyens utilisés pour décider où concentrer mes efforts',
                        'pas_de_clients' => 'Je n’ai pas encore de clients',
                    ],
                ],
                [
                    'commercial_retours_clients',
                    'Comment recueillez-vous les retours de vos clients sur vos produits ou services ?',
                    'Que faites-vous lorsque vos ventes diminuent ?',
                    [
                        'rarement' => 'Je recueille rarement leurs retours',
                        'spontane' => 'J’écoute les remarques lorsqu’un client m’en fait',
                        'regulier' => 'Je demande régulièrement leur avis',
                        'ameliorations' => 'Je recueille leurs retours et je suis les améliorations à apporter',
                        'pas_de_clients' => 'Je n’ai pas encore de clients',
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($questions): void {
            foreach ($questions as $domainName => $items) {
                $domain = Domain::where('intitule', $domainName)->firstOrFail();

                foreach ($items as $index => [$code, $title, $legacyTitle, $choices]) {
                    $question = $domain->questions()->where('question_code', $code)->first()
                        ?? $domain->questions()->where('intitule', $title)->first();

                    if (! $question && $legacyTitle !== null) {
                        $question = $domain->questions()->whereIn('intitule', (array) $legacyTitle)->first();
                    }

                    $options = null;
                    if ($choices !== null) {
                        $options = [];
                        foreach ($choices as $value => $label) {
                            $options[] = ['value' => $value, 'label' => $label];
                        }
                    }

                    $attributes = [
                        'question_code' => $code,
                        'intitule' => $title,
                        'ordre' => $index + 1,
                        'type' => $choices === null ? 'text' : 'unique_choice',
                        'options' => $options,
                    ];

                    if ($question) {
                        $question->fill($attributes);
                        if ($question->isDirty(['intitule', 'type', 'options']) && $question->responses()->exists()) {
                            throw new RuntimeException('La question '.$question->id.' possède déjà des réponses : sa modification nécessite un traitement explicite de l’historique.');
                        }
                        $question->save();
                    } else {
                        $domain->questions()->create($attributes);
                    }
                }
            }
        });
    }
}
