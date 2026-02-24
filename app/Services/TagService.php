<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TagService
{
    /**
     * Find existing tags or create new ones from an array of names.
     *
     * @return array<int> Tag IDs
     */
    public function findOrCreateMany(array $names): array
    {
        $tagIds = [];

        foreach ($names as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $slug = Str::slug($name);
            $tag = Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );

            $tagIds[] = $tag->id;
        }

        return array_unique($tagIds);
    }

    /**
     * Get popular tags (most used).
     */
    public function getPopular(int $limit = 20): Collection
    {
        return Cache::remember("tags:popular:{$limit}", 1800, function () use ($limit) {
            return Tag::withCount('auctions')
                ->orderByDesc('auctions_count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Search tags by name (for autocomplete).
     */
    public function search(string $query, int $limit = 10): Collection
    {
        return Tag::where('name', 'ilike', "%{$query}%")
            ->withCount('auctions')
            ->orderByDesc('auctions_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Merge source tags into target tag.
     */
    public function merge(Tag $target, array $sourceIds): int
    {
        $affected = 0;

        foreach ($sourceIds as $sourceId) {
            if ($sourceId == $target->id) {
                continue;
            }

            $source = Tag::find($sourceId);
            if (! $source) {
                continue;
            }

            // Move auctions from source to target (skip duplicates)
            $existingAuctionIds = $target->auctions()->pluck('auctions.id')->all();
            $auctionsToMove = $source->auctions()
                ->whereNotIn('auctions.id', $existingAuctionIds)
                ->pluck('auctions.id')
                ->all();

            $target->auctions()->attach($auctionsToMove);
            $affected += count($auctionsToMove);

            $source->delete();
        }

        $this->invalidateCache();

        return $affected;
    }

    /**
     * Invalidate tag caches.
     */
    public function invalidateCache(): void
    {
        Cache::forget('tags:popular:20');
        Cache::forget('tags:popular:10');
    }
}
