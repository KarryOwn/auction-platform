<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, InteractsWithMedia;

    public const DEFAULT_NOTIFICATION_PREFERENCES = [
        'outbid'          => ['email' => true, 'push' => true, 'database' => true],
        'auction_won'     => ['email' => true, 'push' => true, 'database' => true],
        'auction_lost'    => ['email' => true, 'push' => true, 'database' => true],
        'auction_ending'  => ['email' => false, 'push' => true, 'database' => true],
        'wallet'          => ['email' => true, 'push' => false, 'database' => true],
        'marketing'       => ['email' => false, 'push' => false, 'database' => false],
    ];

    public const ROLE_USER      = 'user';
    public const ROLE_ADMIN     = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_SELLER    = 'seller';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'avatar_path',
        'role',
        'google_id',
        'github_id',
        'is_banned',
        'banned_at',
        'ban_reason',
        'wallet_balance',
        'held_balance',
        'stripe_connect_account_id',
        'stripe_connect_onboarded',
        'notification_preferences',
        'seller_verified_at',
        'seller_application_status',
        'seller_application_note',
        'seller_bio',
        'seller_avatar_path',
        'seller_slug',
        'seller_applied_at',
        'seller_rejected_reason',
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
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'is_banned'                  => 'boolean',
            'banned_at'                  => 'datetime',
            'wallet_balance'             => 'decimal:2',
            'held_balance'               => 'decimal:2',
            'stripe_connect_onboarded'   => 'boolean',
            'notification_preferences'   => 'array',
            'seller_verified_at'         => 'datetime',
            'seller_applied_at'          => 'datetime',
        ];
    }

    // ── Media (Avatar) ──────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 128, 128)
            ->nonQueued();

        $this->addMediaConversion('profile')
            ->fit(Fit::Crop, 256, 256)
            ->nonQueued();
    }

    public function getAvatarUrl(string $conversion = 'profile'): ?string
    {
        $media = $this->getFirstMedia('avatar');

        if ($media) {
            $conversionPath = $media->getPath($conversion);
            if (is_string($conversionPath) && file_exists($conversionPath)) {
                return $media->getUrl($conversion);
            }
            return $media->getUrl();
        }

        return $this->avatar_path;
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

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function isVerifiedSeller(): bool
    {
        return $this->isSeller()
            && $this->seller_verified_at !== null
            && $this->seller_application_status === 'approved';
    }

    public function hasPendingSellerApplication(): bool
    {
        return $this->seller_application_status === 'pending';
    }

    public function canCreateAuctions(): bool
    {
        return $this->isVerifiedSeller();
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }


    public function getPreferencesAttribute()
    {
        return UserPreference::forUser($this->id);
    }

    public function userPreference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    
    public function keywordAlerts()
    {
        return $this->hasMany(KeywordAlert::class);
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

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function escrowHolds(): HasMany
    {
        return $this->hasMany(EscrowHold::class);
    }

    public function invoicesAsBuyer(): HasMany
    {
        return $this->hasMany(Invoice::class, 'buyer_id');
    }

    public function invoicesAsSeller(): HasMany
    {
        return $this->hasMany(Invoice::class, 'seller_id');
    }

    public function wonAuctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'winner_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(AuctionRating::class, 'ratee_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function sellerApplication(): HasOne
    {
        return $this->hasOne(SellerApplication::class)->latestOfMany();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id')
            ->where(function (Builder $query) {
                $query->where('buyer_id', $this->id)
                    ->orWhere('seller_id', $this->id);
            });
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
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

    public function scopePendingSellers(Builder $query): Builder
    {
        return $query->where('seller_application_status', 'pending');
    }

    public function scopeVerifiedSellers(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_SELLER)
            ->whereNotNull('seller_verified_at')
            ->where('seller_application_status', 'approved');
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
     * Get the user's available (non-held) balance.
     */
    public function availableBalance(): float
    {
        return round((float) $this->wallet_balance - (float) $this->held_balance, 2);
    }

    /**
     * Whether the user can afford a given amount from available (non-held) balance.
     */
    public function canAfford(float $amount): bool
    {
        return $this->availableBalance() >= $amount;
    }

    /**
     * Whether the user has connected a bank account via Stripe Connect.
     */
    public function hasConnectedBank(): bool
    {
        return $this->stripe_connect_onboarded
            && ! empty($this->stripe_connect_account_id);
    }

    /**
     * Get the user's notification preference for a given event and channel.
     */
    public function wantsNotification(string $event, string $channel): bool
    {
        $prefs = $this->notification_preferences ?? self::DEFAULT_NOTIFICATION_PREFERENCES;

        return $prefs[$event][$channel] ?? true;
    }

    /**
     * Get merged notification preferences (user overrides on top of defaults).
     */
    public function getNotificationPreferences(): array
    {
        return array_replace_recursive(
            self::DEFAULT_NOTIFICATION_PREFERENCES,
            $this->notification_preferences ?? []
        );
    }

    public function getAverageRatingAttribute(): ?float
    {
        $average = $this->ratingsReceived()->avg('score');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function getRatingCountAttribute(): int
    {
        return (int) $this->ratingsReceived()->count();
    }
}
