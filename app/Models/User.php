<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'karma_points',
        'reputation_badge',
        'avatar_path',
        'censor_contributor_name',
    ];

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/' . $this->avatar_path) : null;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'karma_points' => 'integer',
            'censor_contributor_name' => 'boolean',
        ];
    }

    public function claimedPlaces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Place::class, 'claimed_by');
    }

    public function submittedPlaces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Place::class, 'submitted_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin || $this->role === 'admin';
    }

    public function isHeadAdmin(): bool
    {
        return strcasecmp(trim((string) $this->email), 'admin@bangued.ph') === 0;
    }

    public function isModerator(): bool
    {
        return $this->isAdmin();
    }

    public function hasRank(int $rank): bool
    {
        return $this->role instanceof UserRole && $this->role->rank() >= $rank;
    }
}