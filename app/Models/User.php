<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    public const ROLE_USER      = 'user';
    public const ROLE_ADMIN     = 'admin';
    public const ROLE_MODERATOR = 'moderator';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_banned',
        'banned_at',
        'ban_reason',
        'wallet_balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_banned'         => 'boolean',
            'banned_at'         => 'datetime',
            'wallet_balance'    => 'decimal:2',
        ];
    }

    // ── Role Checks ─────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MODERATOR]);
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }


    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function autoBids(): HasMany
    {
        return $this->hasMany(AutoBid::class);
    }

    public function watchedAuctions(): HasMany
    {
        return $this->hasMany(AuctionWatcher::class);
    }

    public function wonAuctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'winner_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Scopes ──────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_banned', false);
    }

    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('is_banned', true);
    }

    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Whether the user is watching a given auction.
     */
    public function isWatching(Auction $auction): bool
    {
        return $this->watchedAuctions()->where('auction_id', $auction->id)->exists();
    }

    /**
     * Whether the user has an active auto-bid on a given auction.
     */
    public function hasAutoBidOn(Auction $auction): bool
    {
        return $this->autoBids()->where('auction_id', $auction->id)->exists();
    }

    /**
     * Get the user's active auto-bid for an auction (if any).
     */
    public function autoBidFor(Auction $auction): ?AutoBid
    {
        return $this->autoBids()->where('auction_id', $auction->id)->first();
    }

    /**
     * Whether the user can afford to bid at the given amount (wallet check).
     */
    public function canAfford(float $amount): bool
    {
        return (float) $this->wallet_balance >= $amount;
    }
}
