<?php

/**
 * Barème proposé pour le prototype, à valider auprès des utilisateurs métier.
 * Les clés de questions correspondent à questions.question_code, pas aux IDs
 * ni aux intitulés. Le service de calcul doit appliquer ces règles explicitement.
 * Aucun calcul ni appel à l’IA n’est exécuté par ce fichier.
 */
return [
    'version' => '1.0.0',
    'status' => 'provisional',

    'calculation' => [
        'scale' => 100,
        'precision' => 2,
        'rounding' => 'half_up',
        'round_only_final_scores' => true,
        'domain_method' => 'earned_points_over_evaluable_max_points',
        'min_evaluable_questions_per_domain' => 2,
        'global_method' => 'equal_domain_mean',
        'require_all_scored_domains_for_global' => true,
        'insufficient_coverage_score' => null,
        'exclude_null_points_from_numerator_and_denominator' => true,
        'report_coverage' => true,
    ],

    'validation' => [
        'require_completed_answers' => true,
        'missing_answer' => 'error',
        'unknown_question_code' => 'error',
        'unknown_option' => 'error',
        'unsupported_type' => 'error',
        'domain_scoring_flag_mismatch' => 'error',
    ],

    'exclusion_reasons' => [
        'context_only' => 'Information de contexte, non notée.',
        'pas_de_vente' => 'Aucune vente réalisée ; pratique non évaluable avec cette réponse.',
        'pratique_non_exercee' => 'Paiements différés envisagés mais pas encore pratiqués.',
        'non_applicable' => 'Clients payant immédiatement ; aucun suivi de crédit client applicable.',
        'absence_operations' => 'Aucune opération déclarée permettant d’évaluer cette pratique.',
        'historique_insuffisant' => 'Historique insuffisant pour comparer les ventes.',
        'absence_clients' => 'Aucun client déclaré ; pratique non encore exercée.',
    ],

    'domains' => [
        'general' => [
            'is_scored' => false,
            'question_codes' => ['general_activite', 'general_frein', 'general_formalisation'],
        ],
        'finance' => [
            'is_scored' => true,
            'question_codes' => ['finance_marge', 'finance_anticipation', 'finance_creances'],
        ],
        'comptabilite' => [
            'is_scored' => true,
            'question_codes' => ['comptabilite_enregistrement', 'comptabilite_tracabilite', 'comptabilite_verification'],
        ],
        'commercial' => [
            'is_scored' => true,
            'question_codes' => ['commercial_evolution_ventes', 'commercial_acquisition', 'commercial_retours_clients'],
        ],
    ],

    'questions' => [
        'general_activite' => [
            'domain' => 'general',
            'type' => 'text',
            'is_scored' => false,
            'max_points' => null,
            'reason' => 'context_only',
        ],
        'general_frein' => [
            'domain' => 'general',
            'type' => 'unique_choice',
            'is_scored' => false,
            'max_points' => null,
            'reason' => 'context_only',
        ],
        'general_formalisation' => [
            'domain' => 'general',
            'type' => 'unique_choice',
            'is_scored' => false,
            'max_points' => null,
            'reason' => 'context_only',
        ],

        'finance_marge' => [
            'domain' => 'finance',
            'measures' => 'Connaissance et actualisation de la marge',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'inconnu' => ['points' => 0, 'reason' => null],
                'estimation' => ['points' => 1, 'reason' => null],
                'calcul' => ['points' => 2, 'reason' => null],
                'actualisation' => ['points' => 3, 'reason' => null],
                'pas_de_vente' => ['points' => null, 'reason' => 'pas_de_vente'],
            ],
        ],

        'finance_anticipation' => [
            'domain' => 'finance',
            'measures' => 'Anticipation des dépenses et des encaissements',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'au_moment' => ['points' => 0, 'reason' => null],
                'memoire' => ['points' => 1, 'reason' => null],
                'calendrier' => ['points' => 2, 'reason' => null],
                'prevision' => ['points' => 3, 'reason' => null],
            ],
        ],

        'finance_creances' => [
            'domain' => 'finance',
            'measures' => 'Suivi des sommes dues par les clients',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'memoire' => ['points' => 0, 'reason' => null],
                'montants' => ['points' => 1, 'reason' => null],
                'echeances' => ['points' => 2, 'reason' => null],
                'relances' => ['points' => 3, 'reason' => null],
                'aucune_experience_credit' => ['points' => null, 'reason' => 'pratique_non_exercee'],
                'paiement_immediat' => ['points' => null, 'reason' => 'non_applicable'],
            ],
        ],

        'comptabilite_enregistrement' => [
            'domain' => 'comptabilite',
            'measures' => 'Exhaustivité et tenue à jour des opérations',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'jamais' => ['points' => 0, 'reason' => null],
                'partiel' => ['points' => 1, 'reason' => null],
                'complet_irregulier' => ['points' => 2, 'reason' => null],
                'complet_a_jour' => ['points' => 3, 'reason' => null],
                'pas_operations' => ['points' => null, 'reason' => 'absence_operations'],
            ],
        ],

        'comptabilite_tracabilite' => [
            'domain' => 'comptabilite',
            'measures' => 'Disponibilité des traces et lien avec les opérations',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'aucune' => ['points' => 0, 'reason' => null],
                'difficile' => ['points' => 1, 'reason' => null],
                'retrouvable' => ['points' => 2, 'reason' => null],
                'rapprochement' => ['points' => 3, 'reason' => null],
                'pas_operations' => ['points' => null, 'reason' => 'absence_operations'],
            ],
        ],

        'comptabilite_verification' => [
            'domain' => 'comptabilite',
            'measures' => 'Vérification des enregistrements et traitement des écarts',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'jamais' => ['points' => 0, 'reason' => null],
                'incident' => ['points' => 1, 'reason' => null],
                'comparaison' => ['points' => 2, 'reason' => null],
                'correction' => ['points' => 3, 'reason' => null],
                'pas_operations' => ['points' => null, 'reason' => 'absence_operations'],
            ],
        ],

        'commercial_evolution_ventes' => [
            'domain' => 'commercial',
            'measures' => 'Suivi et compréhension de l’évolution des ventes',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'impression' => ['points' => 0, 'reason' => null],
                'totaux' => ['points' => 1, 'reason' => null],
                'comparaison' => ['points' => 2, 'reason' => null],
                'analyse' => ['points' => 3, 'reason' => null],
                'trop_recent' => ['points' => null, 'reason' => 'historique_insuffisant'],
                'pas_de_vente' => ['points' => null, 'reason' => 'pas_de_vente'],
            ],
        ],

        'commercial_acquisition' => [
            'domain' => 'commercial',
            'measures' => 'Connaissance des moyens qui apportent des clients',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'inconnu' => ['points' => 0, 'reason' => null],
                'echanges' => ['points' => 1, 'reason' => null],
                'suivi' => ['points' => 2, 'reason' => null],
                'comparaison' => ['points' => 3, 'reason' => null],
                'pas_de_clients' => ['points' => null, 'reason' => 'absence_clients'],
            ],
        ],

        'commercial_retours_clients' => [
            'domain' => 'commercial',
            'measures' => 'Recueil des retours clients et suivi des améliorations',
            'type' => 'unique_choice',
            'is_scored' => true,
            'max_points' => 3,
            'options' => [
                'rarement' => ['points' => 0, 'reason' => null],
                'spontane' => ['points' => 1, 'reason' => null],
                'regulier' => ['points' => 2, 'reason' => null],
                'ameliorations' => ['points' => 3, 'reason' => null],
                'pas_de_clients' => ['points' => null, 'reason' => 'absence_clients'],
            ],
        ],
    ],
];
