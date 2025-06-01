<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        $validated = $request->validated();

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->uploadAvatar($request->file('avatar'));
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'avatar' => $avatarPath,
        ]);

        $user->notify(new VerifyEmailNotification());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente. Por favor verifica tu email.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verification_required' => true
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
                'errors' => [
                    'email' => ['Las credenciales proporcionadas no coinciden con nuestros registros.']
                ]
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Por favor verifica tu dirección de email antes de continuar.',
                'email_verification_required' => true,
                'data' => [
                    'user' => new UserResource($user)
                ]
            ], 403);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'URL de verificación inválida'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email ya verificado anteriormente'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verificado exitosamente'
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'El email ya está verificado'
            ], 400);
        }

        $user->notify(new VerifyEmailNotification());

        return response()->json([
            'success' => true,
            'message' => 'Email de verificación enviado'
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada en todos los dispositivos'
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user())
        ]);
    }

    public function update(UpdateUserRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->input('remove_avatar', false)) {
            if ($user->avatar) {
                $this->deleteAvatar($user->avatar);
                $validated['avatar'] = null;
            }
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $this->deleteAvatar($user->avatar);
            }
            $validated['avatar'] = $this->uploadAvatar($request->file('avatar'));
        }

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    private function deleteAvatar(string $fileName): void
    {
        if (Storage::exists('public/avatars/' . $fileName)) {
            Storage::delete('public/avatars/' . $fileName);
        }
    }

    private function uploadAvatar($file): string
    {
        if (!Storage::exists('public/avatars')) {
            Storage::makeDirectory('public/avatars');
        }

        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/avatars', $fileName);
        return $fileName;
    }
}
