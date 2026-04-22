<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image_path',
        'sort_order',
        'commission_rate',
        'is_active',
        'is_featured',
        'featured_until',
        'featured_sort_order',
        'featured_banner_path',
        'featured_tagline',
        'depth',
        'path',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'commission_rate' => 'decimal:4',
        'is_active'  => 'boolean',
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
        'featured_sort_order' => 'integer',
        'depth'      => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            $category->computeDepthAndPath();
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('parent_id')) {
                $category->computeDepthAndPath();
            }
        });
    }

    /**
     * Compute depth and materialized path based on parent.
     */
    public function computeDepthAndPath(): void
    {
        if ($this->parent_id) {
            $parent = self::find($this->parent_id);
            if ($parent) {
                $this->depth = $parent->depth + 1;
                $this->path = $parent->path ? $parent->path . '/' . $this->parent_id : (string) $this->parent_id;
            }
        } else {
            $this->depth = 0;
            $this->path = null;
        }
    }

    // ── Relationships ─────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function auctions(): BelongsToMany
    {
        return $this->belongsToMany(Auction::class, 'auction_category')
            ->withPivot('is_primary');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attribute')
            ->withPivot('is_required');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
            ->where(function (Builder $q) {
                $q->whereNull('featured_until')
                    ->orWhere('featured_until', '>', now());
            })
            ->orderBy('featured_sort_order')
            ->orderBy('name');
    }

    // ── Accessors / Helpers ───────────────────────────────

    /**
     * Get all ancestor categories (from root to parent).
     */
    public function getAncestorsAttribute(): Collection
    {
        if (empty($this->path)) {
            return new Collection();
        }

        $ancestorIds = explode('/', $this->path);

        $positionMap = array_flip($ancestorIds);

        return self::whereIn('id', $ancestorIds)
            ->get()
            ->sortBy(fn (Category $category) => $positionMap[(string) $category->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Breadcrumb: ancestors + self.
     */
    public function getBreadcrumbAttribute(): Collection
    {
        return $this->ancestors->push($this);
    }

    public function getEffectiveCommissionRateAttribute(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        foreach ($this->ancestors->reverse() as $ancestor) {
            if ($ancestor->commission_rate !== null) {
                return (float) $ancestor->commission_rate;
            }
        }

        return (float) config('auction.platform_fee_percent', 0.05);
    }

    public function getEffectiveCommissionPercentAttribute(): float
    {
        return round($this->effective_commission_rate * 100, 2);
    }

    public function getIsCurrentlyFeaturedAttribute(): bool
    {
        return $this->is_featured
            && ($this->featured_until === null || $this->featured_until->isFuture());
    }

    /**
     * Get all descendant category IDs recursively.
     */
    public function getDescendantIdsAttribute(): array
    {
        $ids = [];
        $parentIds = [$this->id];

        while (! empty($parentIds)) {
            $childIds = self::whereIn('parent_id', $parentIds)->pluck('id')->all();

            if (empty($childIds)) {
                break;
            }

            $ids = array_merge($ids, $childIds);
            $parentIds = $childIds;
        }

        return $ids;
    }

    /**
     * Check if this is a leaf category (no children).
     */
    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
    }

    /**
     * Build a nested tree of categories.
     */
    public static function buildTree(?int $parentId = null, bool $activeOnly = true): Collection
    {
        $query = self::where('parent_id', $parentId)->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(function (Category $category) use ($activeOnly) {
            $category->setRelation('children', self::buildTree($category->id, $activeOnly));
            return $category;
        });
    }

    /**
     * Get all attributes for this category, including inherited from ancestors.
     */
    public function getAllAttributes(): Collection
    {
        $categoryIds = array_merge(
            explode('/', $this->path ?? ''),
            [(string) $this->id]
        );

        $categoryIds = array_filter($categoryIds);

        return Attribute::whereHas('categories', function (Builder $q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        })->orderBy('sort_order')->get();
    }
}
