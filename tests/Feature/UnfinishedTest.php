<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\DesignatedPlayer;
use App\Models\LechPlayer;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\EveReminder;
use App\Notifications\PushMessage;
use App\Support\EveMail;
use App\Support\Push;
use App\Support\Unfinished;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

// Przypomnienie o składzie i pytaniach: gracz z typem, ale z pustym składem albo bez odpowiedzi w którymś typie rozgrywek.

function unfinishedMatchday(): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addHours(24)->subMinutes(10),
        'status' => MatchdayStatus::Planned->value,
    ]);
}

/** Gracz w lidze i, opcjonalnie, w innych rozgrywkach, z typem w kolejce. */
function unfinishedPlayer(Matchday $matchday, array $types = []): User
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['email_reminder_hours' => 24])->save();
    $user->assignRole(RoleName::User->value);
    $team = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $user->id, 'user_id' => $user->id]);
    foreach (['league', ...$types] as $type) {
        $competition = Competition::firstOrCreate(['season_id' => $matchday->season_id, 'type' => $type], ['name' => ucfirst($type), 'tier' => 1]);
        CompetitionEntry::create(['competition_id' => $competition->id, 'season_team_id' => $team->id, 'seed' => $user->id]);
    }
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => now()]);

    return $user;
}

test('a set without players or with unanswered questions counts as unfinished', function () {
    $matchday = unfinishedMatchday();
    $user = unfinishedPlayer($matchday, ['cup']);
    $player = LechPlayer::create(['name' => 'Mikael Ishak', 'number' => 9, 'position' => 'forward']);
    $questions = collect(range(1, 2))->map(fn ($n) => MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => 'cup', 'position' => $n, 'text' => 'Pytanie '.$n]));
    DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'score_set' => 'league', 'lech_player_id' => $player->id]);
    QuestionAnswer::create(['matchday_question_id' => $questions[0]->id, 'user_id' => $user->id, 'score_set' => 'cup', 'answer' => true, 'side' => 'attack']);

    $missing = Unfinished::for($user->id, $matchday);

    expect(array_map(fn ($row) => [$row['set'], $row['squad'], $row['questions']], $missing))->toBe([['cup', true, 1]])
        ->and(Unfinished::sentence($missing))->toContain('1 question unanswered');
});

test('a tipped player with an empty squad gets the email about it, a finished one does not', function () {
    Notification::fake();
    $matchday = unfinishedMatchday();
    $empty = unfinishedPlayer($matchday);
    $done = unfinishedPlayer($matchday);
    $player = LechPlayer::create(['name' => 'Mikael Ishak', 'number' => 9, 'position' => 'forward']);
    DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $done->id, 'score_set' => 'league', 'lech_player_id' => $player->id]);

    expect(EveMail::tick())->toBe(1);

    Notification::assertSentTo($empty, EveReminder::class, fn (EveReminder $mail) => $mail->missing !== null);
    Notification::assertNotSentTo($done, EveReminder::class);
});

test('the last call push tells a tipped player what is missing', function () {
    config(['webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);
    Notification::fake();
    $matchday = unfinishedMatchday();
    $matchday->update(['kickoff_at' => now()->addHour()]);
    $user = unfinishedPlayer($matchday);
    $user->updatePushSubscription('https://push.example/1', 'key', 'token', 'aes128gcm');

    Push::tick();

    Notification::assertSentTo($user, PushMessage::class, fn (PushMessage $message) => $message->tag === 'lineup-'.$matchday->id
        && str_contains($message->bodyParams['missing'], 'no squad'));
});
