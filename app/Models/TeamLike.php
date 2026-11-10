<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polubienie klubu użytkownika (niebieskie serduszko na /teams/{user}): user_id = właściciel klubu, liker_id = kto polubił.
 *
 * @property int $user_id
 * @property int $liker_id
 */
class TeamLike extends Model
{
    protected $fillable = ['user_id', 'liker_id'];
}
