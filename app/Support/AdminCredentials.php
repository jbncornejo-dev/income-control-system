<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class AdminCredentials
{
    public const USERNAME = 'regex:/^(?=.{6,40}$)(?=.*[a-z])[a-z0-9]+(?:[._-][a-z0-9]+)*$/';

    public static function password(): Password
    {
        return Password::min(14)->mixedCase()->numbers()->symbols();
    }
}
