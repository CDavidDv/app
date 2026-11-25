<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guest extends Model
{
    protected $fillable = ['name', 'relationship', 'contact', 'notes'];

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function inviteToken(): HasOne
    {
        return $this->hasOne(AccessToken::class)
            ->where('type', AccessToken::GUEST_INVITE)
            ->where('active', true)
            ->latestOfMany();
    }
}
