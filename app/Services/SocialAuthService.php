<?php

namespace App\Services;

use App\Models\User;
use App\Models\SocialAccount;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthService
{
    /**
     * Traite l'inscription/connexion sociale
     */
    public function handleProviderCallback(string $provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        return DB::transaction(function () use ($socialUser, $provider) {
            // Vérifie si ce compte social existe déjà
            $existingSocialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($existingSocialAccount) {
                return $this->updateSocialAccount($existingSocialAccount, $socialUser);
            }

            // Vérifie si l'email existe déjà
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // If an account with this email exists, do not silently link; throw
                throw new Exception('Un compte existe déjà avec cette adresse email.');
            }

            // Crée un nouvel utilisateur avec compte social
            return $this->createUserWithSocialAccount($provider, $socialUser);
        });
    }

    /**
     * Crée un nouvel utilisateur avec son compte social
     */
    private function createUserWithSocialAccount(string $provider, $socialUser): User
    {
        $user = User::create([
            'name' => $this->getName($socialUser),
            'email' => $socialUser->getEmail(),
            'password' => Hash::make(Str::random(24)),
            'avatar' => $socialUser->getAvatar(),
            'email_verified_at' => now(), // Email automatiquement vérifié pour les comptes sociaux
        ]);

        $this->createSocialAccount($user, $provider, $socialUser);

        return $user;
    }

    /**
     * Crée le compte social
     */
    private function createSocialAccount(User $user, string $provider, $socialUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);
    }

    /**
     * Met à jour un compte social existant
     */
    private function updateSocialAccount(SocialAccount $socialAccount, $socialUser): User
    {
        $socialAccount->update([
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        return $socialAccount->user;
    }

    /**
     * Extrait le nom de l'utilisateur
     */
    private function getName($socialUser): string
    {
        return $socialUser->getName() ?? $socialUser->getNickname() ?? 'User';
    }
}