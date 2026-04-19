<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmCache extends Command
{
    protected $signature = 'cache:warm {--key=* : Cache key(s) to warm: category_tree, featured_auctions, root_categories, live_auction_count}';

    protected $description = 'Warm up application caches for performance';

    public function __construct(private readonly CategoryService $categoryService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $requestedKeys = collect($this->option('key'))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values();

        $warmers = [
            'category_tree' => fn () => Cache::remember('category_tree', 3600, fn () => $this->categoryService->getTree()),
            'featured_auctions' => fn () => Cache::remember('featured_auctions', 300, fn () => Auction::featured()->with('media')->take(8)->get()),
            'root_categories' => fn () => Cache::remember('root_categories', 1800, fn () => Category::root()->with('children')->withCount('auctions')->get()),
            'live_auction_count' => fn () => Cache::remember('live_auction_count', 60, fn () => Auction::active()->count()),
        ];

        $keys = $requestedKeys->isEmpty() ? collect(array_keys($warmers)) : $requestedKeys;

        foreach ($keys as $key) {
            if (! array_key_exists($key, $warmers)) {
                $this->error("Unknown cache key: {$key}");
                continue;
            }

            $startedAt = microtime(true);
            $warmers[$key]();
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->info("Cache warmed: {$key} ({$elapsedMs}ms)");
        }

        return self::SUCCESS;
    }
}
