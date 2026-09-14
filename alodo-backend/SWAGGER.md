# Swagger — annotations dans les contrôleurs

Ouvrir **http://127.0.0.1:8000/swagger** après avoir démarré le backend avec `php artisan serve`.

La documentation provient des annotations `@OA` directement au-dessus des méthodes, avec `use OpenApi\Annotations as OA;`, comme dans l’exemple demandé.

## Où modifier la documentation

- `app/Http/Controllers/Api/AuthController.php` : inscription, connexion, déconnexion et utilisateur courant.
- `app/Http/Controllers/Api/DiagnosticController.php` : questionnaire, réponses, résultat et relance IA.
- `app/Http/Controllers/Api/QuestionsController.php` : création de domaines et questions.
- `app/Http/Controllers/Controller.php` : informations générales, authentification Bearer et schémas partagés.

Après une modification des annotations, lancer :

```bash
php artisan swagger:generate
```

La commande analyse les contrôleurs avec swagger-php, valide OpenAPI et produit `public/openapi.json`. **Ne pas modifier ce JSON à la main** : c’est désormais un fichier généré. Recharger `/swagger` pour voir les modifications. La génération n’exécute aucun endpoint métier.

Le support des commentaires `@OA` utilise `zircote/swagger-php` 5 et `doctrine/annotations`. Ce dernier est abandonné, mais nécessaire à cette syntaxe historique. L’intégration passe par notre commande `swagger:generate` et la route Laravel `/swagger`. Les versions récentes de L5-Swagger utilisent les attributs PHP : [guide de migration officiel](https://github.com/DarkaOnLine/L5-Swagger/wiki/Migration-Guides).

## Tester les endpoints

1. Exécuter `POST /api/register` ou `POST /api/login` avec **Try it out**.
2. Copier `data.token` dans **Authorize**, sans ajouter `Bearer`.
3. Charger `GET /api/display/questionnaire` avec un compte entreprise.
4. Utiliser les identifiants et options réellement reçus pour sauvegarder `pending`, puis terminer avec `completed`.
5. Consulter le résultat. Relancer l’interprétation uniquement si `analysis_status` vaut `failed`.

Les appels agissent réellement sur le backend. Une finalisation est définitive ; connexion et déconnexion invalident les anciens tokens. Les endpoints d’administration nécessitent un compte de rôle `admin`.

Swagger UI 5.11.0 charge ses assets depuis unpkg et nécessite Internet. Les appels API restent sur le backend courant, le token n’est pas persisté après rechargement et le validateur distant est désactivé. `/swagger.html` redirige vers `/swagger` pour les anciens liens. Le contrat généré reste téléchargeable sur `/openapi.json`.
