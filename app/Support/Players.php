<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\SeasonTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Kto może grać w sezonie?
 * Gracz to użytkownik z co najmniej jedną rolą (rolę User konto dostaje samo po potwierdzeniu adresu e-mail,
 * Players::approveVerified; admin może ją też nadać ręcznie), który NIE ma roli "Banned" ani "Inactive". Zbanowany użytkownik
 * nie bierze udziału w żadnych rozgrywkach, więc każdy kod wybierający uczestników
 * (lista, ligi, puchar, typowanie) powinien korzystać z Players::eligible().
 * Jeśli gracz nie ma nazwy zespołu, na liście pokazuje się jego nazwa wyświetlana
 * (patrz SeasonTeam::name).
 */
class Players
{
    /** Nazwa roli blokującej udział w zabawie. Rolę tworzysz w "Rolach i uprawnieniach". */
    public const BANNED_ROLE = RoleName::Banned->value;

    /** Role wykluczające z gry: ban i nieaktywność (5 kolejek z rzędu bez typu). */
    public const BLOCKING_ROLES = [RoleName::Banned->value, RoleName::Inactive->value];

    /** Użytkownicy z rolą i bez bana, czyli ci, którzy mogą być na liście. */
    public static function eligible(): Builder
    {
        return User::query()
            ->whereHas('roles')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', self::BLOCKING_ROLES));
    }

    /** Użytkownicy bez żadnej roli (jeszcze niezatwierdzeni). Zbanowani mają rolę, więc tu ich nie ma. */
    public static function withoutRole(): Builder
    {
        return User::query()->whereDoesntHave('roles');
    }

    /** Gracze z rolą (bez zbanowanych), których nie ma jeszcze na liście danego sezonu. */
    public static function unlisted(int $seasonId): Builder
    {
        return self::eligible()->whereNotIn(
            'id',
            SeasonTeam::where('season_id', $seasonId)->whereNotNull('user_id')->select('user_id'),
        );
    }

    /** Czy ten użytkownik może grać (rola jest, bana nie ma)? */
    public static function canPlay(User $user): bool
    {
        return $user->roles->isNotEmpty() && ! $user->hasAnyRole(self::BLOCKING_ROLES);
    }

    /**
     * Konto po potwierdzeniu adresu e-mail (13.11.2026): bez żadnej roli dostaje rolę User i trafia na listę
     * nieukończonych sezonów (Liga podwórkowa, Roster::syncUser). Konto z rolą (także zbanowane albo nieaktywne)
     * zostaje bez zmian. Zwraca true, gdy rola została nadana.
     */
    public static function approveVerified(User $user): bool
    {
        $user->unsetRelation('roles');

        if ($user->roles->isNotEmpty() || ! Role::where('name', RoleName::User->value)->where('guard_name', 'web')->exists()) {
            return false;
        }

        $user->assignRole(RoleName::User->value);
        $user->unsetRelation('roles');

        SystemFeed::record('players', ':name joined the game', ['name' => $user->team_name ?: $user->name], 'team.show', ['user' => $user->id], $user->id);
        Audit::log('roles.updated', $user, ['roles' => ''], ['roles' => RoleName::User->value], __('Email address confirmed'));
        Roster::syncUser($user);

        return true;
    }
}
