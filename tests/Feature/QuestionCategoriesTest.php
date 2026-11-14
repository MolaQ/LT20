<?php

use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionTemplate;
use App\Models\Season;
use App\Models\User;
use App\Support\Questions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Kategorie pytań: automat bierze z jednej kategorii najwyżej jedno pytanie i pomija pytania z innych zestawów kolejki.

function categoryMatchday(): Matchday
{
    // Migracja zasiewa bank przykładowymi pytaniami bez kategorii: testy zaczynają od pustego banku.
    QuestionTemplate::query()->delete();
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create(['season_id' => $season->id, 'number' => 1, 'status' => MatchdayStatus::Planned->value]);
}

test('auto fill takes at most one question from a category', function () {
    $matchday = categoryMatchday();
    foreach (['Kartki', 'Kartki', 'Kartki', 'Podania', 'Podania', 'Faule'] as $i => $category) {
        QuestionTemplate::create(['text' => "Pytanie numer {$i}?", 'category' => $category]);
    }

    expect(Questions::autoFill($matchday, 'league'))->toBe(3);

    $categories = QuestionTemplate::whereIn('id', MatchdayQuestion::where('matchday_id', $matchday->id)->pluck('question_template_id'))
        ->pluck('category')->sort()->values()->all();
    expect($categories)->toBe(['Faule', 'Kartki', 'Podania']);
});

test('auto fill counts questions already in the set and skips questions from other sets', function () {
    $matchday = categoryMatchday();
    $cards = QuestionTemplate::create(['text' => 'Czy Lech dostanie kartkę?', 'category' => 'Kartki']);
    QuestionTemplate::create(['text' => 'Czy będzie czerwona kartka?', 'category' => 'kartki']);
    $passes = QuestionTemplate::create(['text' => 'Czy Lech poda 500 razy?', 'category' => 'Podania']);
    $free = QuestionTemplate::create(['text' => 'Czy Lech strzeli pierwszy?']);

    Questions::setForMatchday($matchday, 'league', [$cards->id]);
    Questions::setForMatchday($matchday, 'cup', [$passes->id]);

    expect(Questions::autoFill($matchday, 'league'))->toBe(1)
        ->and(MatchdayQuestion::where('matchday_id', $matchday->id)->where('score_set', 'league')->pluck('question_template_id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toBe([$cards->id, $free->id]);
});

test('an admin adds a question with a category and fills the set from the panel', function () {
    $matchday = categoryMatchday();
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonEdit->value);

    Livewire::actingAs($admin)->test('pages::dashboard.questions')
        ->set('newText', 'Czy Lech będzie dłużej przy piłce?')
        ->set('newCategory', ' Posiadanie   piłki ')
        ->call('addTemplate')
        ->assertSet('newCategory', ' Posiadanie   piłki ')
        ->call('autoFill')
        ->assertDispatched('ui-toast');

    expect(QuestionTemplate::value('category'))->toBe('Posiadanie piłki')
        ->and(MatchdayQuestion::where('matchday_id', $matchday->id)->count())->toBe(1);
});
