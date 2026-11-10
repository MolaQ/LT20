<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\SeasonTeam;
use App\Models\User;

/** Rywal gracza w lidze (albo Lidze podwórkowej) w danej kolejce: drużyna z drugiej strony meczu tej rundy. */
final class LeagueRival
{
    public static function team(User $user, Matchday $matchday): ?SeasonTeam
    {
        $team = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $user->id)->first();
        if (! $team) {
            return null;
        }

        $entries = CompetitionEntry::where('season_team_id', $team->id)
            ->whereHas('competition', fn ($q) => $q->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value]))
            ->pluck('id');

        $fixture = Fixture::where('round', $matchday->number)
            ->where(fn ($q) => $q->whereIn('home_entry_id', $entries)->orWhereIn('away_entry_id', $entries))
            ->first();

        $otherEntry = $fixture ? ($entries->contains($fixture->home_entry_id) ? $fixture->away_entry_id : $fixture->home_entry_id) : null;
        $other = $otherEntry ? CompetitionEntry::with('seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name')->find($otherEntry)?->seasonTeam : null;

        return $other instanceof SeasonTeam ? $other : null;
    }
}
