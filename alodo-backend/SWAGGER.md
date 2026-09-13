# Documentation Swagger des contrôleurs

Une fois le backend démarré (`php artisan serve`), ouvrir :

- Interface interactive : <http://127.0.0.1:8000/swagger.html>
- Spécification exportable : <http://127.0.0.1:8000/openapi.json>

Adapter l’hôte et le port au serveur utilisé. Les fichiers se trouvent dans `public`, servi par Laravel ou le serveur web. Ne pas ouvrir la page directement en `file://`.

## Tester un parcours

1. Déplier `POST /api/register` ou `POST /api/login`, choisir **Try it out**, renseigner les données puis **Execute**.
2. Copier `data.token` de la réponse.
3. Cliquer sur **Authorize**, coller uniquement le token, puis valider. Swagger ajoute `Authorization: Bearer …`.
4. Exécuter `GET /api/display/questionnaire` avec un compte de rôle `user`.
5. Utiliser les vrais `diagnostic.id`, `questions[].id` et `options[].value` pour `POST /api/store/answers`.
6. Sauvegarder avec `pending`, puis envoyer `completed` lorsque toutes les questions ont une réponse valide. Même la finalisation exige au moins une réponse dans le tableau envoyé.
7. Consulter `GET /api/diagnostics/{diagnostic}/result`. Relancer l’interprétation seulement si `analysis_status` vaut `failed`.

Les exemples sont fictifs. Les appels modifient réellement la base : une finalisation verrouille les réponses ; une connexion ou déconnexion invalide les anciens tokens. Les endpoints de création de domaine et de question nécessitent un token administrateur.

## Contenu et maintenance

[public/openapi.json](public/openapi.json) est la source de vérité de cette documentation : OpenAPI 3.0.3, 10 opérations, schémas des requêtes/réponses, authentification et erreurs. Chaque description identifie la méthode de contrôleur concernée. La route `/api/user` est aussi documentée, bien qu’elle soit une closure.

Le fichier est maintenu explicitement ; il n’est pas généré automatiquement à partir d’annotations PHP. Lors d’une modification de route, de FormRequest, de sérialisation de modèle ou de comportement de contrôleur, mettre à jour les schémas, exemples et descriptions correspondants. Aucun package Composer n’a été ajouté.

[public/swagger.html](public/swagger.html) affiche le contrat avec Swagger UI. La version des assets est fixée à `5.11.0` et chargée depuis unpkg : une connexion Internet est nécessaire à leur chargement. La page reste sur l’origine du backend pour les appels, force `Accept: application/json`, ne persiste pas le token après rechargement et désactive le validateur distant. Aucun appel Gemini n’est effectué par Swagger UI : il passe par les endpoints et le worker existants.

Les restrictions actuelles sont documentées : toutes les questions sont requises, score global éventuellement `null`, analyse asynchrone, absence de modification d’un diagnostic terminé, et création de question sans configuration automatique de son `question_code` ni du barème.

Références du format et de l’interface : [OpenAPI 3.0.3](https://spec.openapis.org/oas/v3.0.3), [installation officielle de Swagger UI](https://swagger.io/docs/open-source-tools/swagger-ui/usage/installation/).
