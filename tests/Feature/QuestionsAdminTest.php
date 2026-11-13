<?php

use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionTemplate;
use App\Models\Season;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Panel pytań: kolejki sezonu w przygotowaniu (przed startem) i poprawianie treści pytań z banku.

function qaAdmin(): User
{
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonEdit->value);

    return $admin;
}

test('questions can be set for a season that has not started yet', function () {
    $season = Season::create(['number' => 2, 'status' => SeasonStatus::Draft->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1, 'status' => MatchdayStatus::Planned->value]);
    $template = QuestionTemplate::create(['text' => 'Czy Lech strzeli pierwszy?']);

    Livewire::actingAs(qaAdmin())->test('pages::dashboard.questions')
        ->assertSet('seasonNumber', 2)
        ->call('toggleTemplate', $template->id);

    expect(MatchdayQuestion::where('matchday_id', $matchday->id)->pluck('text')->all())->toBe(['Czy Lech strzeli pierwszy?']);
});

test('each set is built in its own tab and a question used in one set is refused in another', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1, 'status' => MatchdayStatus::Planned->value]);
    $template = QuestionTemplate::create(['text' => 'Czy Lech strzeli pierwszy?']);

    Livewire::actingAs(qaAdmin())->test('pages::dashboard.questions')
        ->set('set', 'cup')
        ->call('toggleTemplate', $template->id)
        ->set('set', 'league')
        ->call('toggleTemplate', $template->id)
        ->assertDispatched('ui-toast');

    expect(MatchdayQuestion::where('matchday_id', $matchday->id)->pluck('score_set')->all())->toBe(['cup']);
});

test('a bank question can be corrected and the change reaches unanswered matchday questions', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1, 'status' => MatchdayStatus::Planned->value]);
    $template = QuestionTemplate::create(['text' => 'Czy Lech strzeli pierwszi?']);
    $question = MatchdayQuestion::create(['matchday_id' => $matchday->id, 'question_template_id' => $template->id, 'position' => 1, 'text' => $template->text]);

    Livewire::actingAs(qaAdmin())->test('pages::dashboard.questions')
        ->call('editTemplate', $template->id)
        ->set('editText', 'Czy Lech strzeli pierwszy?')
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($template->fresh()->text)->toBe('Czy Lech strzeli pierwszy?')
        ->and($question->fresh()->text)->toBe('Czy Lech strzeli pierwszy?');
});

test('a question can be edited right from the bank tile', function () {
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $template = QuestionTemplate::create(['text' => 'Czy Lech wygra pierwszą połowe?']);

    Livewire::actingAs(qaAdmin())->test('pages::dashboard.questions')
        ->call('editTemplate', $template->id, 'bank')
        ->assertSet('editingWhere', 'bank')
        ->set('editText', 'Czy Lech wygra pierwszą połowę?')
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($template->fresh()->text)->toBe('Czy Lech wygra pierwszą połowę?');
});
