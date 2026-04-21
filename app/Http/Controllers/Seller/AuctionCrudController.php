<?php

namespace App\Http\Controllers\Seller;

use App\Contracts\BiddingStrategy;
use App\Events\AuctionCancelled;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuctionRequest;
use App\Http\Requests\UpdateAuctionRequest;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Services\AttributeService;
use App\Services\CategoryService;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AuctionCrudController extends Controller
{
    public function __construct(
        private readonly BiddingStrategy $biddingStrategy,
        private readonly CategoryService $categoryService,
        private readonly TagService $tagService,
        private readonly AttributeService $attributeService,
    ) {}

    public function index(Request $request)
    {
        $seller = $request->user();
        $status = $request->string('status')->toString();

        $query = Auction::query()
            ->where('user_id', $seller->id)
            ->withCount('bids')
            ->with('media')
            ->latest('updated_at');

        if ($status !== '' && in_array($status, [
            Auction::STATUS_ACTIVE,
            Auction::STATUS_DRAFT,
            Auction::STATUS_COMPLETED,
            Auction::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $auctions = $query->paginate(12)->withQueryString();

        $counts = Auction::query()
            ->where('user_id', $seller->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('seller.auctions.index', [
            'auctions' => $auctions,
            'status' => $status,
            'counts' => [
                'all' => (int) Auction::query()->where('user_id', $seller->id)->count(),
                Auction::STATUS_ACTIVE => (int) ($counts[Auction::STATUS_ACTIVE] ?? 0),
                Auction::STATUS_DRAFT => (int) ($counts[Auction::STATUS_DRAFT] ?? 0),
                Auction::STATUS_COMPLETED => (int) ($counts[Auction::STATUS_COMPLETED] ?? 0),
                Auction::STATUS_CANCELLED => (int) ($counts[Auction::STATUS_CANCELLED] ?? 0),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Auction::class);

        return view('seller.auctions.create', [
            'defaultCurrency' => config('auction.currency', 'USD'),
            'supportedCurrencies' => config('auction.supported_currencies', ['USD']),
            'categoryOptions' => $this->categoryService->getNestedSelectOptions(),
            'brands' => Brand::orderBy('name')->get(),
            'conditions' => Auction::CONDITIONS,
        ]);
    }

    public function store(StoreAuctionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $auction = Auction::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'starting_price' => $validated['starting_price'],
            'current_price' => $validated['starting_price'],
            'reserve_price' => $validated['reserve_price'] ?? null,
            'reserve_met' => false,
            'min_bid_increment' => $validated['min_bid_increment'] ?? config('auction.min_bid_increment', 1.0),
            'snipe_threshold_seconds' => $validated['snipe_threshold_seconds'] ?? config('auction.snipe.threshold_seconds', 30),
            'snipe_extension_seconds' => $validated['snipe_extension_seconds'] ?? config('auction.snipe.extension_seconds', 30),
            'max_extensions' => $validated['max_extensions'] ?? config('auction.snipe.max_extensions', 10),
            'currency' => $validated['currency'] ?? config('auction.currency', 'USD'),
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'],
            'video_url' => $validated['video_url'] ?? null,
            'status' => Auction::STATUS_DRAFT,
            'condition' => $validated['condition'],
            'brand_id' => $validated['brand_id'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
        ]);

        // Sync categories
        if (! empty($validated['categories'])) {
            $primaryId = $validated['primary_category_id'] ?? $validated['categories'][0];
            $syncData = [];
            foreach ($validated['categories'] as $catId) {
                $syncData[$catId] = ['is_primary' => $catId == $primaryId];
            }
            $auction->categories()->sync($syncData);
        }

        // Sync tags
        if (! empty($validated['tags'])) {
            $tagIds = $this->tagService->findOrCreateMany($validated['tags']);
            $auction->tags()->sync($tagIds);
        }

        // Sync attribute values
        if (! empty($validated['attributes'])) {
            $categoryIds = $validated['categories'] ?? [];
            $validatedAttrs = $this->attributeService->validateValues($validated['attributes'], $categoryIds);
            $this->attributeService->syncAuctionAttributes($auction, $validatedAttrs);
        }

        AuditLog::record('auction.created.draft', Auction::class, $auction->id, [
            'title' => $auction->title,
        ]);

        return redirect()->route('seller.auctions.edit', $auction)
            ->with('status', 'Draft saved. You can now upload images and publish.');
    }

    public function edit(Auction $auction)
    {
        $this->authorize('update', $auction);

        $auction->load(['media', 'categories', 'tags', 'brand', 'attributeValues.attribute'])->loadCount('bids');

        return view('seller.auctions.edit', [
            'auction' => $auction,
            'supportedCurrencies' => config('auction.supported_currencies', ['USD']),
            'imageMaxCount' => (int) config('auction.images.max_per_auction', 10),
            'imageMaxSizeMb' => ((int) config('auction.images.max_size_kb', 5120)) / 1024,
            'acceptedTypes' => config('auction.images.allowed_types', []),
            'categoryOptions' => $this->categoryService->getNestedSelectOptions(),
            'brands' => Brand::orderBy('name')->get(),
            'conditions' => Auction::CONDITIONS,
            'categoryAttributes' => $auction->categories->isNotEmpty()
                ? $this->attributeService->getForCategories($auction->categories->pluck('id')->all())
                : collect(),
        ]);
    }

    public function update(UpdateAuctionRequest $request, Auction $auction): RedirectResponse
    {
        $validated = $request->validated();

        $fillable = Arr::only($validated, [
            'title',
            'description',
            'starting_price',
            'reserve_price',
            'buy_it_now_price',
            'buy_it_now_enabled',
            'buy_it_now_expires_at',
            'min_bid_increment',
            'start_time',
            'end_time',
            'currency',
            'snipe_threshold_seconds',
            'snipe_extension_seconds',
            'max_extensions',
            'video_url',
            'condition',
            'brand_id',
            'sku',
            'serial_number',
        ]);

        if (array_key_exists('starting_price', $fillable) && $auction->isDraft()) {
            $fillable['current_price'] = $fillable['starting_price'];
        }

        $auction->update($fillable);

        // Sync categories
        if (! empty($validated['categories'])) {
            $primaryId = $validated['primary_category_id'] ?? $validated['categories'][0];
            $syncData = [];
            foreach ($validated['categories'] as $catId) {
                $syncData[$catId] = ['is_primary' => $catId == $primaryId];
            }
            $auction->categories()->sync($syncData);
        }

        // Sync tags
        if (array_key_exists('tags', $validated)) {
            $tagIds = $this->tagService->findOrCreateMany($validated['tags'] ?? []);
            $auction->tags()->sync($tagIds);
        }

        // Sync attribute values
        if (! empty($validated['attributes'])) {
            $categoryIds = $validated['categories'] ?? $auction->categories->pluck('id')->all();
            $validatedAttrs = $this->attributeService->validateValues($validated['attributes'], $categoryIds);
            $this->attributeService->syncAuctionAttributes($auction, $validatedAttrs);
        }

        AuditLog::record('auction.updated', Auction::class, $auction->id, [
            'fields' => array_keys($fillable),
        ]);

        return back()->with('status', 'Auction updated successfully.');
    }

    public function publish(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('publish', $auction);

        $missing = [];

        if (empty($auction->title)) {
            $missing[] = 'title';
        }

        if (empty($auction->description)) {
            $missing[] = 'description';
        }

        if ((float) $auction->starting_price <= 0) {
            $missing[] = 'starting_price';
        }

        if ($auction->end_time === null) {
            $missing[] = 'end_time';
        }

        if ($auction->getMedia('images')->isEmpty()) {
            $missing[] = 'images';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'auction' => 'Auction is incomplete: '.implode(', ', $missing).'.',
            ]);
        }

        DB::transaction(function () use ($auction) {
            if ($auction->start_time === null) {
                $auction->start_time = now();
            }

            $auction->status = Auction::STATUS_ACTIVE;
            $auction->current_price = $auction->starting_price;
            $auction->save();

            $this->biddingStrategy->initializePrice($auction);

            AuditLog::record('auction.published', Auction::class, $auction->id);
        });

        return redirect()->route('auctions.show', $auction)
            ->with('status', 'Auction published successfully.');
    }

    public function cancel(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('cancel', $auction);

        $auction->update([
            'status' => Auction::STATUS_CANCELLED,
            'closed_at' => now(),
        ]);

        $this->biddingStrategy->cleanup($auction);

        AuditLog::record('auction.cancelled', Auction::class, $auction->id);

        AuctionCancelled::dispatch($auction->fresh(), 'Cancelled by seller.');

        return redirect()->route('seller.auctions.index')
            ->with('status', 'Auction cancelled.');
    }

    public function destroy(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('delete', $auction);

        $auctionId = $auction->id;
        $auction->clearMediaCollection('images');
        $auction->clearMediaCollection('cover');
        $auction->delete();

        AuditLog::record('auction.deleted.draft', Auction::class, $auctionId);

        return redirect()->route('seller.auctions.index')
            ->with('status', 'Draft deleted.');
    }

    public function uploadImage(Request $request, Auction $auction): JsonResponse
    {
        $this->authorize('uploadMedia', $auction);

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('auction.images.allowed_types', [])),
                'max:'.(int) config('auction.images.max_size_kb', 5120),
            ],
        ]);

        $currentCount = $auction->getMedia('images')->count();
        $max = (int) config('auction.images.max_per_auction', 10);
        if ($currentCount >= $max) {
            throw ValidationException::withMessages([
                'file' => "You can upload up to {$max} images per auction.",
            ]);
        }

        $media = $auction
            ->addMediaFromRequest('file')
            ->toMediaCollection('images');

        AuditLog::record('auction.image.uploaded', Auction::class, $auction->id, [
            'media_id' => $media->id,
        ]);

        return response()->json([
            'id' => $media->id,
            'url' => $media->getUrl('gallery'),
            'thumbnail_url' => $media->getUrl('thumbnail'),
        ]);
    }

    public function deleteImage(Request $request, Auction $auction, Media $media): JsonResponse
    {
        $this->authorize('uploadMedia', $auction);

        if ($media->model_type !== Auction::class || (int) $media->model_id !== $auction->id) {
            abort(404);
        }

        $mediaId = $media->id;
        $media->delete();

        if ($auction->getMedia('images')->isEmpty()) {
            $auction->clearMediaCollection('cover');
        }

        AuditLog::record('auction.image.deleted', Auction::class, $auction->id, [
            'media_id' => $mediaId,
        ]);

        return response()->json(['success' => true]);
    }

    public function reorderImages(Request $request, Auction $auction): JsonResponse
    {
        $this->authorize('uploadMedia', $auction);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        Media::setNewOrder($validated['order']);

        AuditLog::record('auction.images.reordered', Auction::class, $auction->id, [
            'order' => $validated['order'],
        ]);

        return response()->json(['success' => true]);
    }
}
auction->id, [
            'order' => $validated['order'],
        ]);

        return response()->json(['success' => true]);
    }
}
