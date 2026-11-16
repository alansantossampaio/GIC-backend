<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuthenticationService;

class AuthController extends Controller
{
    public function __construct(private AuthenticationService $authentication) {}

    // Your authentication methods (login, register, logout, etc.) will go here
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $this->authentication->token($user);
            return response()->json(['access_token' => $token, 'token_type' => 'Bearer']);
        }
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    /**
     * Summary of register a new user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:15',
            'tipe_user_id' => 'required',
        ]);

        $user = $this->authentication->register([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
            'tipe_user_id' => $request->tipe_user_id,
        ]);

        $token = $this->authentication->token($user);
        return response()->json(['access_token' => $token, 'token_type' => 'Bearer']);
    }

    /**
     * Summary of get the authenticated user's information
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }
}
