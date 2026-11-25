<?php

use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\VerificationCode;
use App\Rules\RealEmailDomain;
use App\Support\Unverified;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Konta bez potwierdzonego maila: przypomnienie po dobie, porzucone konta znikają po tygodniu, admin kasuje od ręki.

test('a day after the sign-up an unconfirmed account gets one reminder with a new code', function () {
    Notification::fake();
    $late = User::factory()->unverified()->create(['created_at' => now()->subHours(30)]);
    $fresh = User::factory()->unverified()->create(['created_at' => now()->subHours(2)]);
    User::factory()->create(['created_at' => now()->subDays(2)]);

    expect(Unverified::remind())->toBe(1)
        ->and(Unverified::remind())->toBe(0);

    Notification::assertSentTo($late, VerificationCode::class, fn (VerificationCode $n) => $n->reminder && $n->code === $late->fresh()->verification_code);
    Notification::assertNotSentTo($fresh, VerificationCode::class);
    expect($late->fresh()->verification_reminded_at)->not->toBeNull();
});

test('abandoned sign-ups are deleted after a week but accounts in use are kept', function () {
    Role::findOrCreate(RoleName::User->value, 'web');
    $old = now()->subDays(Unverified::DELETE_AFTER_DAYS + 1);
    $abandoned = User::factory()->unverified()->create(['created_at' => $old]);
    $withRole = User::factory()->unverified()->create(['created_at' => $old]);
    $withRole->assignRole(RoleName::User->value);
    $withTip = User::factory()->unverified()->create(['created_at' => $old]);
    $matchday = Matchday::create(['season_id' => Season::create(['number' => 1, 'status' => SeasonStatus::Active->value])->id, 'number' => 1]);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $withTip->id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => now()]);
    $young = User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
    $confirmed = User::factory()->create(['created_at' => $old]);

    expect(Unverified::cleanup())->toBe(1);

    expect(User::find($abandoned->id))->toBeNull()
        ->and(User::whereKey([$withRole->id, $withTip->id, $young->id, $confirmed->id])->count())->toBe(4);
});

test('an admin deletes selected unconfirmed accounts at once', function () {
    Role::findOrCreate(RoleName::Admin->value, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $bounced = User::factory()->unverified()->create();
    $confirmed = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.users')
        ->set('checked', [(string) $bounced->id, (string) $confirmed->id])
        ->call('deleteUnverified');

    expect(User::find($bounced->id))->toBeNull()
        ->and(User::find($confirmed->id))->not->toBeNull();
});

test('a common typo in the email domain gets a hint', function () {
    $validator = Validator::make(['email' => 'kibic@gmial.com'], ['email' => [new RealEmailDomain]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('email'))->toContain('gmail.com');
    expect(Validator::make(['email' => 'kibic@gmail.com'], ['email' => [new RealEmailDomain]])->fails())->toBeFalse();
});
