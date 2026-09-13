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

class AuthController extends Controller
{
    use TraitsApiResponseTrait;

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

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }
}
