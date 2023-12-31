<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller {
    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::where('username', $request->username)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            return response()->json(
                ['token' => $user->api_token, 'id' => $user->id, 'username' => $user->username],
                200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function logout(Request $request) {
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    public function validateToken(Request $request) {
        Log::debug('Validating token');
        return response()->json(['message' => 'Token is valid'], 200);
    }
}
