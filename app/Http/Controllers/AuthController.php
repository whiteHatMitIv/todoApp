<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use App\Services\SocialAuthService;
use App\Services\PasswordResetService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService,
        private PasswordResetService $passwordResetService
    ) {}

    /**
     * Inscription standard
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Envoie l'email de vérification
        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        // Crée le token avec remember me
        $expiresAt = $request->remember ? now()->addDays(30) : now()->addHours(12);
        $token = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'auth_type' => 'standard',
                'email_verified' => false,
            ]
        ], 201);
    }

    /**
     * Connexion standard
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Vérifie que l'utilisateur n'a pas de compte social
        if ($user->hasSocialAccount()) {
            throw ValidationException::withMessages([
                'email' => ['Veuillez utiliser la connexion ' . $user->getSocialProvider() . '.'],
            ]);
        }

        // Vérifie que l'email a été vérifié
        if (!$user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Veuillez vérifier votre adresse e-mail avant de vous connecter.'],
            ]);
        }

        // Crée le token avec remember me
        $expiresAt = $request->remember ? now()->addDays(30) : now()->addHours(12);
        $token = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'auth_type' => 'standard',
                'email_verified' => $user->hasVerifiedEmail(),
            ]
        ]);
    }

    /**
     * Redirection vers le provider OAuth
     */
    public function redirectToProvider($provider, Request $request)
    {
        $this->validateProvider($provider);
        // Récupère l'URL de redirection depuis la requête
        $redirect = $request->query('redirect');
        
        if ($redirect) {
            // Passe l'URL de redirection à Socialite
            return Socialite::driver($provider)
                ->stateless()
                ->redirectUrl(env('GOOGLE_REDIRECT_URI') . '?redirect=' . urlencode($redirect))
                ->redirect();
        }
        
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Callback du provider OAuth
     */
    public function handleProviderCallback($provider, Request $request)
    {
        $this->validateProvider($provider);

        try {
            $user = $this->socialAuthService->handleProviderCallback($provider);
        } catch (\Exception $e) {
            // For API/XHR requests, return a JSON error so tests and frontends can handle it
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            $frontendUrl = env('FRONTEND_URL');

            return redirect($frontendUrl . '/auth/error?message=' . urlencode($e->getMessage()));
        }

        // Pour les comptes sociaux, on crée un token long (équivalent remember me)
        $token = $user->createToken('api-token', ['*'], now()->addDays(30));

        $redirect = $request->query('redirect', env('FRONTEND_URL') . '/auth/callback');

        $allowedHosts = [parse_url(env('FRONTEND_URL'), PHP_URL_HOST)];
        $host = parse_url($redirect, PHP_URL_HOST);

        if (!in_array($host, $allowedHosts)) {
            $redirect = env('FRONTEND_URL') . '/auth/callback';
        }

        // If the request expects JSON (tests or XHR), return the token and user JSON
        if ($request->wantsJson()) {
            return response()->json([
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'auth_type' => 'social',
                    'email_verified' => $user->hasVerifiedEmail(),
                ]
            ]);
        }

        return redirect($redirect . '?token=' . urlencode($token->plainTextToken));
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user && $user->currentAccessToken()) {
            $user->tokens()
                ->where('id', $user->currentAccessToken()->id)
                ->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Envoie l'email de réinitialisation de mot de passe
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Vérifie que l'utilisateur peut réinitialiser son mot de passe
        if ($user && !$user->canResetPassword()) {
            return response()->json([
                'error' => 'Cet email est associé à un compte ' . $user->getSocialProvider() . '. Veuillez utiliser la connexion sociale.'
            ], 422);
        }

        if ($user) {
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);

            return response()->json(['message' => 'Lien de réinitialisation envoyé']);
        }

        return response()->json(['message' => 'Lien de réinitialisation envoyé']);
    }

    /**
     * Réinitialise le mot de passe
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // Vérifie que l'utilisateur peut réinitialiser son mot de passe
        if ($user && !$user->canResetPassword()) {
            return response()->json([
                'error' => 'Cet email est associé à un compte ' . $user->getSocialProvider() . '. Veuillez utiliser la connexion sociale.'
            ], 422);
        }

        $status = $this->passwordResetService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return $status == Password::PASSWORD_RESET
            ? response()->json(['message' => 'Mot de passe réinitialisé avec succès'])
            : response()->json(['error' => 'Impossible de réinitialiser le mot de passe'], 422);
    }

    /**
     * Renvoie l'email de vérification
     */
    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Lien de vérification envoyé']);
    }

    /**
     * Vérifie l'email
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Helper to return or redirect based on request type
        $respond = function ($statusCode, $payload, $redirectParams = []) use ($request) {
            if ($request->wantsJson()) {
                return response()->json($payload, $statusCode);
            }

            // Redirect to frontend with status and optional message
            $frontend = rtrim(env('FRONTEND_URL', ''), '/');
            $redirectUrl = $frontend ? $frontend . '/auth/verify' : '/';

            $qs = http_build_query(array_merge(['status' => $statusCode === 200 ? 'success' : 'error'], $redirectParams));

            return redirect($redirectUrl . '?' . $qs);
        };

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $respond(403, ['error' => 'Lien de vérification invalide'], ['message' => 'Lien de vérification invalide']);
        }

        if ($user->hasVerifiedEmail()) {
            return $respond(200, ['message' => 'Email déjà vérifié'], ['message' => 'Email déjà vérifié']);
        }

        $user->markEmailAsVerified();

        return $respond(200, ['message' => 'Email vérifié avec succès'], ['message' => 'Email vérifié avec succès']);
    }

    /**
     * Validation du provider
     */
    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['google', 'github'])) {
            abort(422, 'Provider non supporté');
        }
    }
}