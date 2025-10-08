<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Envoie l'email de réinitialisation
     */
    
    public function sendResetLink(array $credentials)
    {
        return Password::sendResetLink($credentials);
    }

    /**
     * Réinitialise le mot de passe
     */
    public function resetPassword(array $credentials)
    {
        return Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->setRememberToken(Str::random(60));

            $user->save();
        });
    }
}
