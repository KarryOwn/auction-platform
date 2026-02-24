<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Attribute extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_SELECT = 'select';
    public const TYPE_BOOLEAN = 'boolean';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'unit',
        'options',
        'is_filterable',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'options'       => 'array',
        'is_filterable' => 'boolean',
        'is_required'   => 'boolean',
        'sort_order'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Attribute $attribute) {
            if (empty($attribute->slug)) {
                $attribute->slug = Str::slug($attribute->name);
            }
        });
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute')
            ->withPivot('is_required');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AuctionAttributeValue::class);
    }

    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Validate a value against this attribute's type and options.
     */
    public function isValidValue(mixed $value): bool
    {
        return match ($this->type) {
            self::TYPE_NUMBER  => is_numeric($value),
            self::TYPE_BOOLEAN => in_array($value, ['0', '1', 'true', 'false', true, false, 0, 1], true),
            self::TYPE_SELECT  => is_array($this->options) && in_array($value, $this->options, true),
            default            => is_string($value) && strlen($value) <= 255,
        };
    }
}
