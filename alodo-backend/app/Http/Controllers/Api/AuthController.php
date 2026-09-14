<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Diagnostic;
use App\Models\Role;
use App\Models\User;
use App\TraitsApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    use TraitsApiResponseTrait;

    /**
     * @OA\Post(
     *     path="/api/register",
     *     operationId="register",
     *     summary="Créer un compte entreprise",
     *     description="AuthController::register. Crée utilisateur, diagnostic baseline et token dans une transaction. Le rôle user est attribué par le serveur. Aucun paramètre de rôle accepté.",
     *     tags={"Authentification"},
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RegisterRequest")),
     *
     *     @OA\Response(response=201, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/AuthData"), required={"success", "message", "data"})),
     *     @OA\Response(response=422, ref="#/components/responses/Validation"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound")
     * )
     */
    public function register(RegisterRequest $request)
    {

        $validatedData = $request->validated();
        $roleId = Role::where('intitule', '=', 'user')->first();
        if (! $roleId) {
            return $this->errorResponse('Role not found', 404);
        }
        $validatedData['role_id'] = $roleId->id;

        $data = DB::transaction(function () use ($validatedData): array {
            $user = User::create($validatedData);
            Diagnostic::create([
                'user_id' => $user->id,
                'status' => 'baseline',
            ]);
            $user->load('role');

            return [
                'token' => $user->createToken('auth_token')->plainTextToken,
                'user' => $user,
            ];
        });

        return $this->successResponse($data, 'User registered successfully', 201);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     operationId="login",
     *     summary="Se connecter",
     *     description="AuthController::login. Une connexion réussie supprime tous les anciens tokens du compte avant de créer le nouveau.",
     *     tags={"Authentification"},
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/LoginRequest")),
     *
     *     @OA\Response(response=200, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/AuthData"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=422, ref="#/components/responses/Validation")
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse('Validation Error!', 422, $validator->errors());
        }
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $user->tokens()->delete();
        $user->load('role');
        $data['token'] = $user->createToken('auth_token')->plainTextToken;
        $data['user'] = $user;

        return $this->successResponse($data, 'User logged in successfully', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     summary="Se déconnecter",
     *     description="AuthController::logout. Révoque tous les tokens du compte, pas uniquement le token courant.",
     *     tags={"Authentification"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\Response(response=200, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", type="string", nullable=true, enum={null}), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/user",
     *     operationId="currentUser",
     *     summary="Lire le compte connecté",
     *     description="Closure routes/api.php. Réponse sans enveloppe success/message/data. La relation role n’est pas chargée.",
     *     tags={"Authentification"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\Response(response=200, description="Utilisateur directement, sans enveloppe.", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
     * )
     */
    public function currentUser(Request $request): User
    {
        return $request->user();
    }
}
