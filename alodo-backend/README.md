# ALODO — API de diagnostic des MPME

Backend du prototype de diagnostic ALODO, destiné aux micro, petites et moyennes entreprises du Bénin. Il permet d’enregistrer les pratiques déclarées par une entreprise, de calculer des scores de structuration et de produire une interprétation pour orienter son accompagnement.

## 1. Présentation — qu’avons-nous construit ?

Une API REST qui prend en charge le parcours suivant :

**Inscription → questionnaire par domaines → sauvegarde progressive → finalisation → scoring → interprétation Gemini → consultation du résultat.**

Le questionnaire contient **12 questions**, réparties entre trois dimensions évaluées — **Finance, Comptabilité et Commercial** — et un domaine **Général**, non noté, qui apporte le contexte de l’entreprise.

Le résultat comprend un score global lorsqu’il est calculable, des scores par domaine, la couverture du diagnostic et une interprétation : synthèse, points forts, faiblesses ou limites et recommandations. Le score est calculé par le backend ; l’IA intervient uniquement pour l’interpréter.

Ce dépôt contient l’API Laravel. Le frontend React est un projet distinct. La documentation interactive des endpoints est accessible via **`/swagger`**.

## 2. Choix produit — pourquoi ces dimensions et ces questions ?

L’objectif est de comprendre les pratiques réelles d’une petite entreprise, puis de dégager des pistes d’accompagnement. 

| Domaine | Informations recherchées | Intérêt pour l’accompagnement |
| --- | --- | --- |
| Général — non noté | Activité et clients visés, principal frein déclaré, état des démarches de formalisation | Comprendre le contexte et la priorité exprimée par le dirigeant |
| Finance | Connaissance de la marge, anticipation des dépenses et encaissements, suivi des créances clients | Identifier les pratiques à renforcer pour mieux piloter l’argent disponible |
| Comptabilité | Enregistrement des opérations, disponibilité des traces, vérification et correction des écarts | Apprécier la fiabilité des informations utilisées pour gérer l’activité |
| Commercial | Suivi de l’évolution des ventes, connaissance des canaux d’acquisition, utilisation des retours clients | Comprendre comment l’entreprise suit ses ventes et améliore sa relation client |

Finance et Comptabilité sont distinguées : la première examine l’utilisation des informations pour décider et anticiper ; la seconde examine la manière dont ces informations sont enregistrées et vérifiées.

Le périmètre est volontairement limité à trois dimensions notées, avec trois questions chacune. Il permet de proposer un parcours court et d’expliquer chaque score. La digitalisation n’est pas une dimension évaluée dans cette version : un cahier, un reçu ou un message peuvent constituer des outils pertinents selon les pratiques de l’entreprise.

Ces choix constituent une **hypothèse de diagnostic adaptée au prototype**, à confronter à des dirigeants de MPME et à des professionnels de l’accompagnement au Bénin. Ils ne sont pas présentés comme une grille métier déjà validée sur le terrain.

## 3. Choix techniques — pourquoi cette stack ?

| Technologie | Rôle et justification |
| --- | --- |
| PHP / Laravel 13 | Construire une API structurée avec validation, routage, transactions et migrations dans un même framework |
| MySQL / Eloquent | Persister les entreprises, les réponses et les résultats, et permettre une reprise du questionnaire après déconnexion |
| Laravel Sanctum | Authentifier les appels API avec des tokens Bearer et limiter l’accès aux données du compte connecté |
| Gemini via le client HTTP Laravel | Produire une interprétation en français à partir des réponses et des scores, avec un format JSON contrôlé |
| swagger-php / annotations `@OA` | Documenter les méthodes des contrôleurs et générer un contrat OpenAPI consultable dans Swagger UI |
| PHPUnit / SQLite en mémoire | Vérifier les règles métier et les endpoints sans modifier la base de développement ni dépendre de Gemini |

### Séparation des responsabilités

- Les **Form Requests** contrôlent les données entrantes et les options autorisées.
- Les **contrôleurs** orchestrent l’authentification, la sauvegarde et la consultation.
- [DiagnosticScoringService](app/Services/DiagnosticScoringService.php) calcule les scores à partir du [barème versionné](config/diagnostic_scoring.php).
- [DiagnosticInterpretationService](app/Services/DiagnosticInterpretationService.php) appelle Gemini et valide sa réponse.
- Les **seeders** installent les rôles, les domaines et les questions avec leurs `question_code` stables.

### Calcul explicable

Les réponses évaluables des dimensions notées rapportent entre **0 et 3 points**.

```text
Score d’un domaine = points obtenus / maximum des questions évaluables × 100
Score global = moyenne des trois scores de domaine, avant leur arrondi d’affichage
```

Un domaine nécessite au moins **deux questions évaluables sur trois**. Le score global nécessite que les trois domaines notés soient évaluables. Les scores finaux sont arrondis à deux décimales.

Une réponse exclue ne contribue ni aux points obtenus ni au maximum possible. Son motif reste disponible dans le résultat. **Un score `null` signifie « non calculable », jamais zéro.** Un questionnaire entièrement rempli peut donc produire une évaluation partielle.

Le résultat conserve le détail des calculs, la version du barème et les données utilisées pour l’interprétation. 

### Interprétation synchrone

La configuration actuelle utilise **`QUEUE_CONNECTION=sync`**, afin de fonctionner sans worker séparé pour le prototype.

À la finalisation, les réponses, le calcul et le statut du diagnostic sont enregistrés dans une transaction. **Après validation de cette transaction**, le job `GenerateDiagnosticInterpretation` s’exécute immédiatement dans la requête HTTP et appelle Gemini. Le backend enregistre l’interprétation avant de répondre au frontend.

Si Gemini échoue, les réponses et les scores restent sauvegardés. L’interprétation passe à `failed` et peut être relancée indépendamment. 
## 4. Installation — comment lancer le projet ?

### Prérequis

- PHP **8.4 recommandé**, version utilisée pour le développement et les tests ; les dépendances exactes sont verrouillées dans `composer.lock`.
- Composer 2 et les extensions PHP requises par les dépendances, notamment cURL et PDO MySQL. PDO SQLite est nécessaire aux tests.
- Un serveur MySQL et une base dédiée.
- Une clé Gemini et un modèle accessible avec cette clé pour obtenir l’interprétation.

### Installer et configurer

Depuis une copie du dépôt, entrer dans `alodo-backend` :

```bash
cd alodo-backend
composer install
composer check-platform-reqs
```

Pour une première installation, créer le fichier de configuration :

```bash
cp .env.example .env
php artisan key:generate
```

Si `.env` existe déjà, conserver son contenu et adapter uniquement les paramètres nécessaires. Ne pas publier ce fichier ni la clé Gemini dans le dépôt.

Créer la base MySQL, puis configurer sa connexion dans `.env`. Exemple de création :

```sql
CREATE DATABASE alodo_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Exemple de paramètres à adapter :

```dotenv
APP_NAME=ALODO
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alodo_backend
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync

GEMINI_API_KEY=votre_cle_gemini
GEMINI_MODEL=gemini-3.6-flash
```

`gemini-3.6-flash` est le modèle utilisé lors du test réel du prototype. Le modèle configuré doit être accessible avec la clé utilisée et accepter les sorties JSON structurées. Sans configuration Gemini valide, le questionnaire et le scoring fonctionnent, mais l’interprétation échoue.

### Initialiser et démarrer

```bash
php artisan config:clear
php artisan migrate --seed
php artisan swagger:generate
php artisan serve --host=127.0.0.1 --port=8000
```

- API : `http://127.0.0.1:8000/api`
- Swagger : `http://127.0.0.1:8000/swagger`
- Contrat généré : `http://127.0.0.1:8000/openapi.json`


### Comptes de démonstration

Les seeders créent ces comptes uniquement en environnement `local` ou `testing` :

| Rôle | E-mail | Mot de passe initial |
| --- | --- | --- |
| Entreprise | `entreprise@alodo.test` | `AlodoDemo!2026` |
| Administrateur | `admin@alodo.test` | `AlodoDemo!2026` |

Le compte entreprise possède un diagnostic initial. Une nouvelle inscription crée également son propre diagnostic. Les seeders préservent les comptes existants ; ils ne réinitialisent pas un mot de passe déjà modifié.

### Essayer l’API

Dans Swagger, appeler `POST /api/login`, puis copier `data.token` dans **Authorize**, sans ajouter le préfixe `Bearer`. Charger ensuite le questionnaire pour récupérer les véritables identifiants et options.

Pour un client HTTP, envoyer :

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer VOTRE_TOKEN
```

Le frontend utilise l’origine du backend, sans suffixe `/api` dans sa variable :

```dotenv
VITE_API_URL=http://127.0.0.1:8000
VITE_DATA_SOURCE=api
```

### Vérifications

```bash
php artisan test --compact
php artisan route:list --path=api
```

Les tests couvrent notamment l’inscription transactionnelle, les options de questions, les sauvegardes, la complétude, le scoring, les exclusions, les accès aux résultats, l’interprétation et la documentation Swagger. Les appels Gemini sont simulés dans les tests automatisés ; un test réel a été effectué séparément. Une réussite des tests ne garantit pas la disponibilité future du fournisseur.

## 5. Fonctionnalités — qu’est-ce qui fonctionne ?

| Fonctionnalité | Endpoint |
| --- | --- |
| Inscription avec création du diagnostic | `POST /api/register` |
| Connexion et émission d’un token | `POST /api/login` |
| Lecture du compte connecté | `GET /api/user` |
| Déconnexion et révocation des tokens du compte | `POST /api/logout` |
| Questionnaire ordonné par domaines, avec réponses enregistrées | `GET /api/display/questionnaire` |
| Sauvegarde progressive et finalisation | `POST /api/store/answers` |
| Consultation du score et de l’interprétation | `GET /api/diagnostics/{diagnostic}/result` |
| Relance d’une interprétation en échec | `POST /api/diagnostics/{diagnostic}/interpretation/retry` |
| Création d’un domaine, rôle administrateur | `POST /api/store/domains` |
| Création d’une question, rôle administrateur | `POST /api/store/questions` |

Les endpoints de diagnostic sont réservés au rôle `user`. Chaque entreprise accède uniquement à son diagnostic et à son résultat. Une connexion réussie invalide les anciens tokens du compte.

La sauvegarde `pending` met à jour les réponses envoyées et conserve les précédentes. La finalisation `completed` exige toutes les réponses valides, y compris celles de Général. Un diagnostic terminé ne peut plus être modifié. Un tableau de réponses non vide est requis dans les deux cas.

L’analyse IA distingue les faiblesses observées des limites du diagnostic. Une liste de points forts vide est acceptée afin de ne pas en inventer. Les recommandations et constats doivent citer des codes de questions connus. Le résultat indique également la couverture et les motifs d’exclusion du scoring.

Les formats détaillés des requêtes, réponses et erreurs sont décrits dans Swagger. Les succès utilisent généralement `{ success, message, data }` ; `/api/user` renvoie directement l’utilisateur et certaines erreurs Laravel n’incluent pas `success`.

## 6. Limites — qu’avons-nous volontairement laissé de côté ?

- **Périmètre métier réduit.** Les autres dimensions possibles d’ALODO, telles que les opérations, les ressources humaines ou la préparation au financement, ne sont pas évaluées dans ce prototype.
- **Diagnostic déclaratif et barème provisoire.** Aucune pièce justificative n’est contrôlée. Le résultat n’atteste ni une conformité administrative ni une éligibilité au financement. Les scores et recommandations nécessitent une validation métier avant un usage décisionnel.
- **Administration partielle.** Les endpoints créent des domaines et des questions, mais ne constituent pas un éditeur complet. La création de question ne configure pas automatiquement `question_code` ni son barème. Tout ajout doit être aligné avec la configuration de scoring pour ne pas bloquer la finalisation.
- **Accompagnement non implémenté.** Pas de dashboard
- **Authentification limitée au besoin du prototype.** Pas de récupération de mot de passe, de vérification d’e-mail ou de gestion avancée des membres d’une entreprise.
- **Traitement synchrone.** L’utilisateur attend Gemini pendant la finalisation. Ce choix simplifie l’exploitation, mais limite le confort et la capacité à absorber plusieurs appels longs. La dépendance réseau, les quotas et la variabilité de l’IA restent présents.


## 7. Améliorations — qu’aurions-nous ajouté avec plus de temps ?

1. **Valider le diagnostic sur le terrain** avec des dirigeants de MPME béninoises et des accompagnateurs : compréhension des questions, pertinence des exclusions, calibration des points et utilité des recommandations.
2. **Adapter le questionnaire au profil de l’entreprise** et à ses réponses, pour éviter les éventuels questions sans rapport avec son activité, tout en gardant un calcul explicable.
3. **Versionner entièrement les questionnaires et barèmes**, avec publication contrôlée, afin de faire évoluer les questions sans altérer la lecture des anciens diagnostics.
4. **Construire l’espace accompagnateur(admin)** : consultation autorisée des dossiers, revue humaine des recommandations, priorisation des actions et suivi des progrès.
5. **Renforcer l’exploitation** : traitement asynchrone avec worker supervisé lorsque l’hébergement le permet, gestion des délais et reprises, journalisation des erreurs du fournisseur sans exposer de secrets, suivi de la latence et des coûts.

