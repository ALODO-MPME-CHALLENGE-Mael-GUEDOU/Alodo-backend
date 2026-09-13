<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainRequest;
use App\Http\Requests\QuestionRequest;
use App\Models\Domain;
use App\TraitsApiResponseTrait;

class QuestionsController extends Controller
{
    use TraitsApiResponseTrait;

    /**
     * Store a new domain.
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
     * Store a new question.
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
