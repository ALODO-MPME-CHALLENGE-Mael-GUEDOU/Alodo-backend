# Cahier de réalisation du frontend ALODO

Référence : backend présent au 14 septembre 2026. Les routes et contrats ci-dessous proviennent du code actuel. Les chemins de pages et comportements UX proposés sont des choix de réalisation frontend, pas des endpoints supplémentaires.

## 1. Objectif et périmètre

Permettre au dirigeant d’une MPME de créer son compte, répondre à un diagnostic par étapes, reprendre ses réponses enregistrées et consulter son score ainsi que son interprétation.

Le résultat est actuellement destiné à l’entreprise connectée. Le backend ne fournit pas de consultation des candidatures par un accompagnateur ou un administrateur.

Le questionnaire prévu contient 12 questions réparties dans quatre domaines : Général, Finance, Comptabilité et Commercial. Général apporte du contexte, sans score. Les trois autres domaines sont notés. Toutes les questions sont obligatoires pour terminer, y compris celles de Général et celles proposant une réponse non évaluable.

Les domaines, questions et options doivent être chargés depuis l’API. Ne pas coder leurs identifiants, leur ordre ou leurs textes en dur. L’interface peut prendre en charge les quatre types de questions du backend ; le questionnaire et le barème actuels utilisent du texte et des choix uniques.

## 2. Écrans à construire

| Page proposée | Accès | Fonction |
| --- | --- | --- |
| `/` | Public | Présenter le diagnostic et proposer de commencer ou reprendre |
| `/inscription` | Public | Créer le compte de l’entreprise |
| `/connexion` | Public | Se connecter |
| `/diagnostic` | Rôle `user` | Afficher les domaines par étapes et sauvegarder les réponses |
| `/diagnostic/resultat` | Rôle `user` | Afficher le calcul puis l’interprétation disponible |
| Page d’accès refusé | Tout utilisateur concerné | Expliquer un accès non autorisé |

Prévoir un en-tête simple avec le nom de l’utilisateur connecté et une action de déconnexion. Les écrans doivent fonctionner sur téléphone et ordinateur, sans défilement horizontal.

### 2.1. Accueil

Expliquer en français simple ce que l’entreprise va obtenir : un état des lieux de ses pratiques financières, comptables et commerciales et des pistes d’amélioration. Préciser que le résultat repose sur ses déclarations.

Actions : « Commencer mon diagnostic » et « Reprendre mon diagnostic ». Orienter vers l’inscription ou la connexion si nécessaire. Pour un compte connecté de rôle `user`, charger le questionnaire pour décider entre reprise et résultat.

Ne pas promettre une sélection, un financement, un rendez-vous ou un accompagnement automatiquement déclenché : ces opérations ne sont pas implémentées.

### 2.2. Inscription

| Champ affiché | Clé API | Contraintes |
| --- | --- | --- |
| Nom de l’entreprise | `name` | Texte obligatoire, 255 caractères maximum |
| Adresse e-mail | `email` | E-mail obligatoire, unique, 255 caractères maximum |
| Mot de passe | `password` | Obligatoire, au moins 8 caractères |

Le libellé « Nom de l’entreprise » est une convention d’interface : le backend stocke un nom générique dans `users.name`, pas une fiche entreprise distincte. Une confirmation du mot de passe peut être vérifiée localement ; elle n’est pas exigée par l’API.

Envoyer `POST /api/register`. Pendant l’envoi, désactiver le bouton et afficher un état de chargement. Au succès `201`, conserver `data.token` et `data.user`, puis charger le questionnaire. L’inscription crée déjà le diagnostic : aucun deuxième appel de création n’est nécessaire. Le serveur attribue le rôle `user` ; aucun sélecteur de rôle à l’inscription.

### 2.3. Connexion et session

Envoyer `email` et `password` à `POST /api/login`. Au succès, récupérer `data.token` et `data.user`. Le rôle chargé est disponible dans `data.user.role.intitule` ; ne pas associer les rôles à des identifiants numériques fixes.

Une nouvelle connexion invalide les anciens tokens du compte. `POST /api/logout` invalide également tous ses tokens. Au succès de la déconnexion, effacer les données de session et les réponses en mémoire, puis revenir à l’accueil.

Centraliser le token et les appels API. Ne jamais mettre le token dans une URL ni conserver le mot de passe. Lorsqu’une session est restaurée, vérifier sa validité avec `GET /api/user`. Cette route renvoie directement l’utilisateur, sans enveloppe `data`, et ne garantit pas la présence de la relation `role` chargée. Ne pas déduire le rôle d’un `role_id` supposé fixe ; les endpoints protégés restent l’autorité sur l’accès.

Un `401` sur une route protégée doit ramener à la connexion avec un message de session expirée. Un `401` lors de la connexion signifie que les identifiants sont incorrects. Ne pas proposer de récupération de mot de passe fonctionnelle : aucun endpoint n’existe actuellement.

### 2.4. Questionnaire par domaines

Au chargement, appeler `GET /api/display/questionnaire`. Afficher un état de chargement puis :

- le domaine courant, sa description et les questions ;
- un indicateur « Étape X sur Y » calculé depuis les domaines reçus ;
- une progression des réponses renseignées, distincte du score ;
- les boutons « Précédent », « Enregistrer et continuer » et, à la dernière étape, « Terminer mon diagnostic » ;
- un état de sauvegarde : modifications non enregistrées, enregistrement en cours, enregistré ou échec.

Respecter l’ordre reçu : le serveur classe les domaines par `ordre`, puis les questions par `ordre` et `id`. Les domaines vides sont absents de la réponse. Ne pas supposer que Général est toujours la première étape.

Préremplir les champs avec `question.valeur`. Pour un diagnostic non terminé, reprendre à la première étape contenant une réponse manquante. Si toutes les réponses sont déjà enregistrées, permettre de les relire et de terminer.

| `diagnostic.status` | Comportement |
| --- | --- |
| `baseline` | Diagnostic créé, commencer le questionnaire |
| `pending` | Diagnostic en cours, reprendre les réponses |
| `completed` | Aller au résultat ; aucune modification des réponses n’est acceptée |

Si `domains` est vide, afficher « Le questionnaire n’est pas encore disponible » et désactiver la soumission. Si le diagnostic est introuvable, afficher une erreur explicite ; le front ne peut pas en créer un séparément.

### 2.5. Composants de réponse

| `type` | Composant | Valeur envoyée dans `valeur` |
| --- | --- | --- |
| `text` | Zone de texte avec libellé | Chaîne non vide, maximum 5 000 caractères |
| `number` | Champ numérique | Nombre valide, y compris `0` |
| `unique_choice` | Boutons radio ou cartes sélectionnables accessibles | Une chaîne égale à une `options[].value` |
| `multiple_choice` | Cases à cocher | Tableau non vide de chaînes autorisées, sans doublons |

Afficher `options[].label`, envoyer `options[].value`. Même une option « Oui » ou « Non » utilise une valeur chaîne provenant de l’API : ne pas la convertir en booléen. Un choix multiple reste un tableau même s’il contient un seul élément.

Une réponse « Non applicable » est une vraie réponse à envoyer. Le serveur décidera de son exclusion du calcul. `is_scored: false` signifie « contexte non noté », pas « facultatif ».

Un type inconnu doit produire un message de questionnaire incompatible et empêcher la finalisation ; ne pas inventer un champ de remplacement. Les libellés doivent être associés aux champs, les groupes de choix accessibles au clavier et les erreurs annoncées près des questions.

## 3. Sauvegarde et finalisation

### 3.1. Contrat de lecture

Exemple réduit de `data` renvoyé par le questionnaire ; les identifiants sont illustratifs :

```json
{
  "diagnostic": { "id": 7, "status": "pending", "completed_at": null },
  "domains": [
    {
      "id": 2,
      "intitule": "Finance",
      "description": "Comprendre les pratiques financières",
      "ordre": 2,
      "is_scored": true,
      "questions": [
        {
          "id": 4,
          "intitule": "Exemple de question",
          "type": "unique_choice",
          "ordre": 1,
          "options": [
            { "value": "inconnu", "label": "Je ne sais pas" },
            { "value": "estimation", "label": "Je fais une estimation" }
          ],
          "valeur": "estimation"
        }
      ]
    }
  ]
}
```

### 3.2. Enregistrer une étape

Envoyer `POST /api/store/answers` :

```json
{
  "diagnostic_id": 7,
  "status": "pending",
  "responses": [
    { "question_id": 4, "valeur": "estimation" }
  ]
}
```

Le serveur crée ou remplace les réponses envoyées et conserve les autres. Ne pas envoyer `domain_id`, de score ou d’analyse. Chaque `question_id` doit apparaître une seule fois dans la requête.

Choix UX recommandé : vérifier toutes les questions de l’étape avant « Enregistrer et continuer », attendre le succès, puis avancer. Le serveur accepte néanmoins une sauvegarde partielle avec `pending`. Un bouton facultatif « Enregistrer et quitter » peut envoyer les réponses renseignées et valides, avec au moins une réponse.

Les valeurs vides, `null` et les tableaux vides sont refusés. Il n’existe pas de suppression de réponse enregistrée. Une réponse effacée localement puis omise de l’envoi reste donc présente en base : signaler cette modification non enregistrée et demander une nouvelle valeur avant de poursuivre.

La réponse de succès est `201`, même pour une modification :

```json
{
  "success": true,
  "message": "Responses saved successfully.",
  "data": {
    "result": null,
    "responses": [
      { "id": 18, "diagnostic_id": 7, "question_id": 4, "valeur": "estimation" }
    ],
    "newStatus": "pending",
    "completed_at": null
  }
}
```

L’exemple omet les timestamps des réponses. `data.responses` contient les réponses de cet envoi, pas nécessairement toutes les réponses du diagnostic.

### 3.3. Terminer

Envoyer le même format avec `status: "completed"`. Il faut toujours envoyer au moins une réponse : par exemple celles de la dernière étape. On peut aussi envoyer toutes les réponses valides du formulaire.

Le backend combine les réponses déjà sauvegardées et celles de cette requête, vérifie la complétude et les valeurs, calcule le score, enregistre le résultat et renseigne `completed_at`. Ces opérations sont transactionnelles. L’échec de cette requête ne supprime pas les sauvegardes précédentes.

Au succès, `data.newStatus` vaut `completed`, `data.result` contient le résultat enregistré et l’interprétation est déclenchée. Aller à l’écran de résultat et exploiter son état réel : elle peut encore attendre, être en cours, avoir échoué ou être déjà terminée.

La finalisation est définitive dans l’API actuelle : préciser près du bouton que les réponses ne pourront plus être modifiées. Aucun endpoint ne permet de recommencer un diagnostic.

### 3.4. Réseau et concurrence

- Désactiver les doubles soumissions et sérialiser les sauvegardes ; attendre les envois en cours avant de terminer.
- En cas d’échec réseau, conserver les saisies et proposer une nouvelle tentative, sans annoncer qu’elles sont enregistrées.
- Si la réponse à la finalisation est perdue, recharger d’abord le questionnaire. S’il est `completed`, consulter le résultat ; ne pas renvoyer aveuglément une finalisation.
- Avertir avant de quitter une page contenant des modifications non sauvegardées.
- Ne pas écraser une saisie locale récente avec une ancienne réponse réseau.

## 4. Écran de résultat

Appeler `GET /api/diagnostics/{diagnostic_id}/result`. L’identifiant vient du questionnaire, pas de l’identifiant du résultat.

Présenter dans cet ordre : score global ou raison de son absence, scores par domaine avec couverture, synthèse, points forts, points à améliorer ou limites, recommandations. Les scores doivent rester consultables si l’IA est indisponible.

### 4.1. Données du calcul

| Champ dans `data` | Usage frontend |
| --- | --- |
| `id`, `diagnostic_id` | Identifiants du résultat et du diagnostic |
| `score` | Score global, chaîne décimale comme `"66.67"`, ou `null` |
| `scoring_version` | Version du barème, affichable dans un détail secondaire |
| `scoring_details.status` | `evaluated` ou `partial` |
| `scoring_details.coverage` | `evaluated_domains` et `scored_domains` |
| `scoring_details.domains` | Objet indexé par `general`, `finance`, `comptabilite`, `commercial` |
| `analysis_status` | État de l’interprétation décrit ci-dessous |
| `analysis` | Objet d’interprétation, initialement `null` |
| `analysis_error` | Message d’échec, éventuellement `null` |
| `analyzed_at` | Date d’interprétation, éventuellement `null` |

Les métadonnées techniques `analysis_provider`, `analysis_model`, `prompt_version` et les timestamps peuvent aussi être présents. Elles ne sont pas nécessaires à l’affichage principal. `interpretation_input` est masqué par le backend.

Chaque entrée de `scoring_details.domains` contient : `domain_id`, `is_scored`, `status`, `score`, `earned_points`, `max_points`, `coverage: { evaluated, total }` et `questions` indexées par code de question.

Chaque détail de question contient `question_id`, `question_code`, `answer`, `points`, `max_points`, `included`, `exclusion_reason` et `exclusion_label`. Ces données permettent d’expliquer les réponses prises en compte ou exclues.

Règles d’affichage :

- Afficher les scores fournis par le backend sur 100, sans recalculer le barème dans le navigateur.
- Tester explicitement `score === null` avant conversion numérique. Un score de zéro est valide ; un score absent ne doit jamais devenir `0/100`.
- Afficher la couverture, par exemple « 2 questions évaluables sur 3 ».
- Pour `insufficient_coverage`, afficher « Informations évaluables insuffisantes » à la place du score du domaine.
- Pour `not_scored`, présenter Général comme contexte, sans jauge à zéro.
- Un domaine est actuellement calculable à partir de deux questions évaluables. Le score global exige les trois domaines évaluables. Un questionnaire terminé peut donc avoir un résultat `partial` et un score global `null`.
- Ne pas inventer de seuils « faible / moyen / excellent » : le backend n’en définit pas actuellement.
- Ne pas traiter une exclusion comme une faiblesse. Utiliser son `exclusion_label` pour l’expliquer.

### 4.2. États de l’IA

| `analysis_status` | Affichage et action |
| --- | --- |
| `pending` | « Votre interprétation est en attente. » Afficher les scores et actualiser périodiquement |
| `processing` | « Votre interprétation est en cours. » Conserver les scores visibles |
| `completed` | Afficher `analysis`, arrêter l’actualisation périodique |
| `failed` | Afficher un message d’indisponibilité et « Relancer l’interprétation » |

Proposition frontend : relire le résultat toutes les 3 secondes tant que l’écran est actif et l’état `pending` ou `processing`. Arrêter au départ de la page, en cas d’erreur d’accès ou lorsque le traitement se termine. Après une attente prolongée, espacer les lectures et proposer « Actualiser » ou de revenir plus tard. Ne pas transformer localement une longue attente en `failed`.

La relance utilise `POST /api/diagnostics/{diagnostic_id}/interpretation/retry`, sans corps métier. Elle est acceptée seulement pour `failed` et renvoie `202` avec le résultat actualisé. Désactiver le bouton pendant l’appel, puis reprendre la lecture périodique si nécessaire. Un `409` impose de relire le résultat : l’état a pu changer, ou les données nécessaires peuvent manquer.

Cette relance ne recalcule pas le score et ne soumet pas les réponses à nouveau. Le front ne contacte jamais Gemini directement et ne reçoit aucune clé Gemini. Le traitement asynchrone nécessite un worker opérationnel côté backend.

### 4.3. Structure de l’interprétation

```text
analysis.summary: string
analysis.strengths: [{ text, question_codes: string[] }]
analysis.weaknesses: [{ text, question_codes: string[], kind }]
analysis.recommendations: [{ action, expected_benefit, question_codes: string[] }]
```

Afficher `summary` comme synthèse, chaque `strengths[].text` comme point fort, et chaque recommandation avec son action et son bénéfice attendu. Garder l’ordre fourni pour les recommandations.

Pour une faiblesse, `kind: "observed"` correspond à un point à améliorer ; `kind: "limitation"` correspond à une limite du diagnostic. Les afficher avec des libellés distincts. La liste des forces peut être vide : ne pas en inventer. Présenter les constats comme fondés sur les pratiques déclarées.

Un lien facultatif « Réponses concernées » peut utiliser les `question_codes` de l’analyse, puis `scoring_details.domains.*.questions` pour retrouver les `question_id`, et enfin les libellés du questionnaire. La route questionnaire n’expose pas directement `question_code`.

Afficher les textes comme du texte, sans exécuter du HTML provenant des réponses ou de l’IA.

## 5. Convention API et gestion des erreurs

Configurer une URL de base par environnement. Les chemins ci-dessous incluent déjà `/api` : éviter de doubler ce préfixe. Envoyer `Accept: application/json`, `Content-Type: application/json` pour les corps JSON et `Authorization: Bearer <token>` sur les routes protégées.

| Méthode | Route | Accès | Succès |
| --- | --- | --- | --- |
| POST | `/api/register` | Public | 201, `data: { token, user }` |
| POST | `/api/login` | Public | 200, `data: { token, user }` |
| GET | `/api/user` | Authentifié | 200, utilisateur directement |
| POST | `/api/logout` | Authentifié | 200, `data: null` |
| GET | `/api/display/questionnaire` | `user` | 200, `data: { diagnostic, domains }` |
| POST | `/api/store/answers` | `user` | 201, `data: { result, responses, newStatus, completed_at }` |
| GET | `/api/diagnostics/{diagnostic}/result` | `user`, propriétaire | 200, résultat dans `data` |
| POST | `/api/diagnostics/{diagnostic}/interpretation/retry` | `user`, propriétaire | 202, résultat dans `data` |
| POST | `/api/store/domains` | `admin` | 201 |
| POST | `/api/store/questions` | `admin` | 201 |

Les succès des contrôleurs utilisent `{ success: true, message, data }`. Les erreurs peuvent utiliser `{ success: false, message, errors }`, mais les validations Laravel utilisent aussi `{ message, errors }` sans `success`. Certaines erreurs d’accès ne contiennent que `message`. Se baser sur le code HTTP et traiter `errors` lorsqu’il existe.

| Situation | Comportement attendu |
| --- | --- |
| 400 | Afficher le message métier, notamment un domaine déjà existant |
| 401 | Identifiants invalides à la connexion, ou session à renouveler sur route protégée |
| 403 | Afficher l’accès refusé, sans boucle de reconnexion |
| 404 | Ressource absente ou inaccessible ; ne pas afficher les données d’un autre diagnostic |
| 409 | Relance non autorisée dans l’état actuel ; relire le résultat |
| 422 | Afficher les erreurs sur les champs concernés, conserver les saisies |
| 500 / panne réseau | Message compréhensible, conservation des saisies et nouvelle tentative adaptée |

Pour les réponses, conserver l’ordre exact du tableau envoyé afin de rattacher `responses.0.valeur` à sa question. Une erreur `responses.0.valeur.1` concerne un élément d’un choix multiple. Les erreurs `answers.12` désignent directement la question d’identifiant `12` lors du contrôle final, même si elle appartient à une étape précédente. Ouvrir cette étape et placer le focus sur la première erreur. Les erreurs `diagnostic_id` ou `status` doivent aussi être visibles au niveau du formulaire.

## 6. Administration : possibilités et limites actuelles

L’administration n’est pas nécessaire pour réaliser le parcours entreprise. Si elle est incluse, limiter les écrans aux opérations disponibles et prévoir explicitement les lacunes suivantes.

**Création de domaine — `POST /api/store/domains`** : `intitule` obligatoire, maximum 100 caractères ; `description` facultative ; `ordre` entier unique entre 1 et 8 ; `is_scored` booléen obligatoire. Général doit rester non noté pour correspondre au barème actuel.

**Création de question — `POST /api/store/questions`** : `intitule` obligatoire, maximum 255 caractères ; `domain_id` existant ; `ordre` entier au moins égal à 1 ; `type` parmi les quatre types reconnus. Pour les choix, `options` doit contenir au moins deux objets `{ value, label }`, avec des valeurs chaînes non vides et distinctes (100 caractères maximum), et des libellés non vides (255 caractères maximum). Pour texte et nombre, omettre les options.

Il n’existe pas d’endpoint administrateur pour lister, modifier ou supprimer les domaines/questions. Le questionnaire est réservé au rôle `user`, il ne sert donc pas de catalogue à l’administrateur. Un formulaire de question avec sélecteur dynamique de domaine nécessite un endpoint de lecture supplémentaire.

La création de question ne prend actuellement pas en charge `question_code` dans sa validation. Le calcul exige une correspondance exacte entre les questions en base et le barème. Ajouter une question depuis l’administration peut donc empêcher la finalisation du diagnostic tant que sa configuration n’a pas été adaptée. Le questionnaire utilisé pour ce prototype doit rester celui préparé par les seeders et le barème ; ne pas présenter l’administration comme un éditeur autonome de diagnostics.

Ne pas prévoir comme fonctionnalités disponibles : liste des entreprises, sélection des candidatures, historique de plusieurs diagnostics, modification d’un diagnostic terminé, téléchargement d’un rapport PDF, rendez-vous ou suivi d’accompagnement. Ces fonctionnalités nécessitent de nouveaux contrats backend.

## 7. Ordre de réalisation conseillé

1. Mettre en place le client API, la session, les routes protégées et les erreurs communes.
2. Construire l’accueil, l’inscription et la connexion.
3. Charger le questionnaire et rendre les champs selon leur type.
4. Ajouter la navigation par domaine, le préremplissage et les sauvegardes `pending`.
5. Ajouter la finalisation et le retour vers une question invalide.
6. Construire les scores, la couverture et les cas sans score.
7. Afficher l’interprétation, ses états, l’actualisation et la relance.
8. Vérifier le parcours complet sur mobile et ordinateur avec le backend réel.

## 8. Critères de validation du frontend

- [ ] L’inscription crée une session et ouvre le diagnostic sans création supplémentaire.
- [ ] La connexion permet de reprendre les valeurs réellement sauvegardées.
- [ ] Les domaines et questions respectent l’ordre fourni par l’API.
- [ ] Général est obligatoire mais n’apparaît pas comme un domaine noté.
- [ ] Les options affichent leur libellé et envoient leur valeur exacte.
- [ ] Le nombre zéro est accepté et les choix multiples sont des tableaux sans doublons.
- [ ] Une erreur de sauvegarde conserve les saisies et empêche d’annoncer un faux succès.
- [ ] Une erreur finale dans une ancienne étape ramène à la bonne question.
- [ ] Le double clic et les sauvegardes concurrentes sont maîtrisés.
- [ ] Après une finalisation à réponse réseau incertaine, le front vérifie l’état serveur.
- [ ] Un diagnostic terminé n’offre pas de modification ni de redémarrage non pris en charge.
- [ ] Un score `null` est distingué de zéro et les exclusions sont expliquées sans pénalité inventée.
- [ ] Les scores restent visibles pendant l’attente ou l’échec de l’IA.
- [ ] Une interprétation échouée peut être relancée ; une interprétation en cours ne l’est pas.
- [ ] Les forces vides et les limites du diagnostic ont un affichage cohérent.
- [ ] Les erreurs 401, 403, 404, 409 et 422 ont été vérifiées.
- [ ] Les champs, la navigation et les erreurs sont utilisables au clavier et sur mobile.
- [ ] L’intégration réelle vérifie l’URL API, les autorisations CORS si les origines diffèrent, et le worker d’interprétation. La disponibilité effective de l’IA ne doit pas être déduite de tests avec réponses simulées.

## Sources du contrat

- [Routes API](routes/api.php)
- [Authentification](app/Http/Controllers/Api/AuthController.php)
- [Questionnaire, réponses, résultat et relance](app/Http/Controllers/Api/DiagnosticController.php)
- [Validation des réponses](app/Http/Requests/AnswersRequest.php)
- [Validation des questions](app/Http/Requests/QuestionRequest.php)
- [Validation des domaines](app/Http/Requests/DomainRequest.php)
- [Modèle de résultat](app/Models/Result.php)
- [Service de scoring](app/Services/DiagnosticScoringService.php)
- [Service d’interprétation](app/Services/DiagnosticInterpretationService.php)
