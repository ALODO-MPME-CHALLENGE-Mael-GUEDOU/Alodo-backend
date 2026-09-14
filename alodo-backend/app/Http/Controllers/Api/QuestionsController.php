<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainRequest;
use App\Http\Requests\QuestionRequest;
use App\Models\Domain;
use App\TraitsApiResponseTrait;
use OpenApi\Annotations as OA;

class QuestionsController extends Controller
{
    use TraitsApiResponseTrait;

    /**
     * @OA\Post(
     *     path="/api/store/domains",
     *     operationId="storeDomain",
     *     summary="Créer un domaine",
     *     description="QuestionsController::storeDomain. Rôle admin uniquement. ordre doit être unique entre 1 et 8 ; un intitulé déjà utilisé produit 400. Général doit rester is_scored=false conformément au barème. Aucune route de liste, modification ou suppression administrateur.",
     *     tags={"Administration"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DomainRequest")),
     *
     *     @OA\Response(response=201, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/Domain"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=422, ref="#/components/responses/Validation")
     * )
     */
    public function storeDomain(DomainRequest $request)
    {
        $domain_name = $request->input('intitule');
        // Vérifie si le domaine existe déjà

        $exist = Domain::where('intitule', $domain_name)->first();
        if ($exist) {
            return $this->errorResponse('Domain already exists', 400);
        }
        // crée le domaine si il n'existe pas

        $domain = Domain::create($request->validated());

        return $this->successResponse($domain, 'Domain created successfully', 201);
    }

    /**
     * @OA\Post(
     *     path="/api/store/questions",
     *     operationId="storeQuestion",
     *     summary="Créer une question",
     *     description="QuestionsController::storeQuestion. Rôle admin uniquement. Options requises pour les choix : value et label non vides, value distinctes. Ne pas envoyer d’options pour text/number. question_code n’est pas accepté par la validation actuelle. Ajouter une question sans adapter le code et le barème peut empêcher toute finalisation ; cet endpoint ne configure pas le scoring.",
     *     tags={"Administration"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionRequest")),
     *
     *     @OA\Response(response=201, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/Question"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/Validation")
     * )
     */
    public function storeQuestion(QuestionRequest $request)
    {
        $question = $request->validated();
        $domain_id = $question['domain_id'];

        // Vérifie si le domaine existe
        $domain = Domain::find($domain_id);
        if (! $domain) {
            return $this->errorResponse('Domain not found', 404);
        }

        // Crée la question
        $newQuestion = $domain->questions()->create($question);

        return $this->successResponse($newQuestion, 'Question created successfully', 201);
    }
}
