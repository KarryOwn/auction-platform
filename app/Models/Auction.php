<?php

namespace App\Models;

use App\Services\VideoEmbedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Auction extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;


    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';


    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_path',
        'video_url',
        'starting_price',
        'current_price',
        'reserve_price',
        'reserve_met',
        'min_bid_increment',
        'snipe_threshold_seconds',
        'snipe_extension_seconds',
        'extension_count',
        'max_extensions',
        'currency',
        'is_featured',
        'featured_until',
        'start_time',
        'end_time',
        'status',
        'winner_id',
        'winning_bid_amount',
        'bid_count',
        'unique_bidder_count',
        'closed_at',
        'payment_status',
        'payment_deadline',
        'ending_soon_notified',
    ];


    protected $casts = [
        'start_time'               => 'datetime',
        'end_time'                 => 'datetime',
        'closed_at'                => 'datetime',
        'featured_until'           => 'datetime',
        'starting_price'           => 'decimal:2',
        'current_price'            => 'decimal:2',
        'reserve_price'            => 'decimal:2',
        'min_bid_increment'        => 'decimal:2',
        'winning_bid_amount'       => 'decimal:2',
        'reserve_met'              => 'boolean',
        'is_featured'              => 'boolean',
        'bid_count'                => 'integer',
        'unique_bidder_count'      => 'integer',
        'extension_count'          => 'integer',
        'max_extensions'           => 'integer',
        'snipe_threshold_seconds'  => 'integer',
        'snipe_extension_seconds'  => 'integer',
        'payment_deadline'         => 'datetime',
        'ending_soon_notified'     => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $allowed = config('auction.images.allowed_types', ['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('images')
            ->acceptsMimeTypes($allowed)
            ->useDisk('public')
            ->withResponsiveImages();

        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes($allowed)
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        [$thumbWidth, $thumbHeight] = config('auction.images.conversions.thumbnail', [300, 300]);
        [$galleryWidth, $galleryHeight] = config('auction.images.conversions.gallery', [800, 600]);
        [$fullWidth, $fullHeight] = config('auction.images.conversions.full', [1920, 1080]);

        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, $thumbWidth, $thumbHeight)
            ->nonQueued();

        $this->addMediaConversion('gallery')
            ->fit(Fit::Contain, $galleryWidth, $galleryHeight)
            ->nonQueued();

        $this->addMediaConversion('full')
            ->fit(Fit::Contain, $fullWidth, $fullHeight)
            ->nonQueued();
    }


    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function latestBid(): HasOne
    {
        return $this->hasOne(Bid::class)->latestOfMany();
    }

    public function highestBid(): HasOne
    {
        return $this->hasOne(Bid::class)->ofMany('amount', 'max');
    }

    public function autoBids(): HasMany
    {
        return $this->hasMany(AutoBid::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(AuctionWatcher::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AuctionSnapshot::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReportedAuction::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(AuctionView::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }


    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->where('end_time', '>', now());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeEndingSoon(Builder $query, int $minutes = 15): Builder
    {
        return $query->active()
                     ->where('end_time', '<=', now()->addMinutes($minutes))
                     ->orderBy('end_time', 'asc');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
                     ->where(function (Builder $q) {
                         $q->whereNull('featured_until')
                           ->orWhere('featured_until', '>', now());
                     });
    }

    public function scopeReserveMet(Builder $query): Builder
    {
        return $query->where('reserve_met', true);
    }

    public function scopeReserveNotMet(Builder $query): Builder
    {
        return $query->where('reserve_met', false)
                     ->whereNotNull('reserve_price');
    }


    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->end_time->isFuture();
    }

    public function hasEnded(): bool
    {
        return $this->end_time->isPast();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Whether the auction has a reserve price set.
     */
    public function hasReserve(): bool
    {
        return $this->reserve_price !== null && (float) $this->reserve_price > 0;
    }

    /**
     * Check whether the current price meets the reserve.
     */
    public function isReserveMet(): bool
    {
        if (! $this->hasReserve()) {
            return true; // no reserve = always met
        }

        return (float) $this->current_price >= (float) $this->reserve_price;
    }

    /**
     * Calculate minimum next bid amount.
     */
    public function minimumNextBid(): float
    {
        return round((float) $this->current_price + (float) $this->min_bid_increment, 2);
    }

    /**
     * Whether a bid arriving now should trigger anti-snipe extension.
     */
    public function isInSnipeWindow(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->end_time->diffInSeconds(now(), absolute: true) <= $this->snipe_threshold_seconds;
    }

    /**
     * Whether more anti-snipe extensions are allowed.
     */
    public function canExtend(): bool
    {
        return $this->extension_count < $this->max_extensions;
    }

    /**
     * Apply a snipe extension if within the window and under the limit.
     * Returns true if the extension was applied.
     */
    public function applySnipeExtension(): bool
    {
        if (! $this->isInSnipeWindow() || ! $this->canExtend()) {
            return false;
        }

        $this->end_time = $this->end_time->addSeconds($this->snipe_extension_seconds);
        $this->extension_count++;
        $this->save();

        return true;
    }

    /**
     * Increment the cached bid counters (call after placing a bid).
     */
    public function incrementBidCounters(int $userId): void
    {
        $this->increment('bid_count');

        // Only bump unique count if this user hasn't bid before
        $existingBids = $this->bids()->where('user_id', $userId)->count();
        if ($existingBids <= 1) {
            $this->increment('unique_bidder_count');
        }
    }

    /**
     * Time remaining as a human-readable string.
     */
    public function timeRemaining(): string
    {
        if ($this->hasEnded()) {
            return 'Ended';
        }

        return $this->end_time->diffForHumans(parts: 2, short: true);
    }

    public function getCoverImageUrl(string $conversion = 'thumbnail'): ?string
    {
        $media = $this->getFirstMedia('cover') ?? $this->getFirstMedia('images');

        if ($media) {
            $conversionPath = $media->getPath($conversion);

            if (is_string($conversionPath) && file_exists($conversionPath)) {
                return $media->getUrl($conversion);
            }

            return $media->getUrl();
        }

        return $this->image_path;
    }

    public function getGalleryImages(string $conversion = 'gallery'): array
    {
        return $this->getMedia('images')->map(function (Media $media) use ($conversion) {
            $conversionUrl = file_exists($media->getPath($conversion))
                ? $media->getUrl($conversion)
                : $media->getUrl();

            $fullUrl = file_exists($media->getPath('full'))
                ? $media->getUrl('full')
                : $media->getUrl();

            $thumbnailUrl = file_exists($media->getPath('thumbnail'))
                ? $media->getUrl('thumbnail')
                : $media->getUrl();

            return [
                'id' => $media->id,
                'name' => $media->name,
                'url' => $conversionUrl,
                'full_url' => $fullUrl,
                'thumbnail_url' => $thumbnailUrl,
                'order' => $media->order_column,
            ];
        })->all();
    }

    public function hasVideo(): bool
    {
        return ! empty($this->video_url);
    }

    public function getVideoEmbedUrl(): ?string
    {
        if (! $this->hasVideo()) {
            return null;
        }

        $parsed = app(VideoEmbedService::class)->parse((string) $this->video_url);

        return $parsed['embed_url'] ?? null;
    }
}