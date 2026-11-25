<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\RealEmailDomain;
use App\Support\Referrals;
use App\Support\TeamRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Rejestracja nowego konta (Fortify): walidacja, nazwa zespołu, zapis użytkownika i jego źródło (App\Support\Referrals).
 */
class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = TeamRules::normalize($input);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new RealEmailDomain, Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'team_name' => TeamRules::name(),
        ], [], [
            'team_name' => __('Team name'),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'team_name' => $input['team_name'],
        ]);

        // Skąd przyszło konto i kto je zaprosił (statystyki w panelu, nagrody za zaproszenia).
        Referrals::attach($user);

        return $user;
    }
}
