<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\TraitsApiResponseTrait;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use TraitsApiResponseTrait;

    public function Register(RegisterRequest $request){
    
        $validatedData = $request->validated();
        $roleId = Role::where('intitule', '=', 'user')->first();
        if (! $roleId) {
            return $this->errorResponse('Role not found', 404);
        }
        $validatedData['role_id'] = $roleId->id;

        $user = User::create($validatedData);
        $user->load('role');

        $data['token'] = $user->createToken('auth_token')->plainTextToken;
        $data['user'] = $user;

        return $this->successResponse($data, 'User registered successfully', 201);
    }

    public function Login(Request $request)
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
    public function Logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }
}
