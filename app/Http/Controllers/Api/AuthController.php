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

/**
 * Controlador de autenticación para la API
 *
 * Maneja todas las operaciones relacionadas con la autenticación de usuarios:
 * - Registro de nuevos usuarios
 * - Inicio y cierre de sesión
 * - Verificación de email
 * - Gestión de perfil de usuario
 * - Manejo de avatares
 */
class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario en el sistema
     *
     * Proceso:
     * 1. Valida los datos del formulario
     * 2. Sube el avatar si se proporciona
     * 3. Crea el usuario con contraseña hasheada
     * 4. Envía email de verificación
     * 5. Genera token de autenticación
     *
     * @param RegisterUserRequest $request Datos validados del usuario
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterUserRequest $request)
    {
        $validated = $request->validated();

        // Manejar subida de avatar opcional
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->uploadAvatar($request->file('avatar'));
        }

        // Crear usuario con contraseña encriptada
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']), // Importante: siempre hashear contraseñas
            'avatar' => $avatarPath,
        ]);

        // Enviar notificación de verificación de email
        $user->notify(new VerifyEmailNotification());

        // Generar token de autenticación para uso inmediato
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente. Por favor verifica tu email.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verification_required' => true // Indica que necesita verificar email
            ]
        ], 201);
    }

    /**
     * Autentica un usuario existente
     *
     * Proceso:
     * 1. Valida credenciales
     * 2. Verifica que el email esté confirmado
     * 3. Revoca tokens existentes (logout automático otros dispositivos)
     * 4. Genera nuevo token de acceso
     *
     * @param Request $request Credenciales de login
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Buscar usuario por email
        $user = User::where('email', $request->email)->first();

        // Verificar credenciales (usuario existe y contraseña correcta)
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
                'errors' => [
                    'email' => ['Las credenciales proporcionadas no coinciden con nuestros registros.']
                ]
            ], 401);
        }

        // Verificar que el email esté confirmado antes de permitir acceso
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

        // Revocar todos los tokens existentes (logout forzado de otros dispositivos)
        $user->tokens()->delete();

        // Generar nuevo token de acceso
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

    /**
     * Verifica el email de un usuario mediante URL firmada
     *
     * Este endpoint se llama cuando el usuario hace clic en el enlace
     * de verificación enviado por email
     *
     * @param Request $request Contiene ID y hash de verificación
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        // Verificar que el hash de la URL coincida (seguridad)
        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'URL de verificación inválida'
            ], 400);
        }

        // Verificar si ya está verificado (evitar procesamiento innecesario)
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email ya verificado anteriormente'
            ]);
        }

        // Marcar email como verificado y disparar evento
        if ($user->markEmailAsVerified()) {
            event(new Verified($user)); // Evento para notificaciones adicionales
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verificado exitosamente'
        ]);
    }

    /**
     * Reenvía el email de verificación
     *
     * Útil cuando el usuario no recibió el email inicial o expiró
     *
     * @param Request $request Usuario autenticado
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        // Verificar que efectivamente necesite verificación
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'El email ya está verificado'
            ], 400);
        }

        // Enviar nueva notificación de verificación
        $user->notify(new VerifyEmailNotification());

        return response()->json([
            'success' => true,
            'message' => 'Email de verificación enviado'
        ]);
    }

    /**
     * Cierra sesión del dispositivo actual
     *
     * Solo revoca el token actual, mantiene sesiones en otros dispositivos
     *
     * @param Request $request Usuario autenticado
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revocar solo el token actual (del dispositivo actual)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ]);
    }

    /**
     * Cierra sesión en todos los dispositivos
     *
     * Revoca todos los tokens del usuario, forzando logout en todos los dispositivos
     *
     * @param Request $request Usuario autenticado
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutAll(Request $request)
    {
        // Revocar todos los tokens del usuario
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada en todos los dispositivos'
        ]);
    }

    /**
     * Obtiene información del usuario autenticado
     *
     * Endpoint para obtener datos del perfil actual
     *
     * @param Request $request Usuario autenticado
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user())
        ]);
    }

    /**
     * Actualiza el perfil del usuario autenticado
     *
     * Permite actualizar:
     * - Información personal (nombre, email)
     * - Contraseña
     * - Avatar (subir nuevo o eliminar existente)
     *
     * @param UpdateUserRequest $request Datos validados de actualización
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUserRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        // Manejar eliminación de avatar existente
        if ($request->input('remove_avatar', false)) {
            if ($user->avatar) {
                $this->deleteAvatar($user->avatar);
                $validated['avatar'] = null;
            }
        }

        // Manejar subida de nuevo avatar
        if ($request->hasFile('avatar')) {
            // Eliminar avatar anterior si existe
            if ($user->avatar) {
                $this->deleteAvatar($user->avatar);
            }
            $validated['avatar'] = $this->uploadAvatar($request->file('avatar'));
        }

        // Manejar actualización de contraseña
        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']); // Siempre hashear
        } else {
            unset($validated['password']); // No actualizar si no se proporciona
        }

        // Actualizar datos del usuario
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Elimina un archivo de avatar del almacenamiento
     *
     * Método privado para limpiar archivos de avatar
     *
     * @param string $fileName Nombre del archivo a eliminar
     * @return void
     */
    private function deleteAvatar(string $fileName): void
    {
        if (Storage::exists('public/avatars/' . $fileName)) {
            Storage::delete('public/avatars/' . $fileName);
        }
    }

    /**
     * Sube un nuevo archivo de avatar
     *
     * Proceso:
     * 1. Verifica/crea directorio de avatares
     * 2. Genera nombre único para evitar conflictos
     * 3. Almacena archivo en storage/app/public/avatars/
     *
     * @param \Illuminate\Http\UploadedFile $file Archivo de avatar
     * @return string Nombre del archivo almacenado
     */
    private function uploadAvatar($file): string
    {
        // Asegurar que existe el directorio de avatares
        if (!Storage::exists('public/avatars')) {
            Storage::makeDirectory('public/avatars');
        }

        // Generar nombre único para evitar conflictos
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Almacenar archivo
        $file->storeAs('public/avatars', $fileName);

        return $fileName;
    }
}
