<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BiddingStrategy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuctionResource;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Auction Platform API",
 *     description="Public REST API for auction platform integration.",
 *     @OA\Contact(email="api@example.com")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="error", type="string"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="details", type="object")
 * )
 */
class AuctionController extends Controller
{
    public function __construct(
        protected BiddingStrategy $engine,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/auctions",
     *     summary="List active auctions",
     *     tags={"Auctions"},
     *     @OA\Parameter(name="q", in="query", description="Search keyword", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"ending_soon","newest","price_asc","price_desc"})),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=50)),
     *     @OA\Response(response=200, description="Paginated auction list",
     *         @OA\JsonContent(ref="#/components/schemas/AuctionCollection")
     *     )
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:ending_soon,newest,price_asc,price_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Auction::active()
            ->with(['seller:id,name,seller_slug', 'media', 'brand', 'categories'])
            ->withCount('bids');

        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $query->where(function ($searchQuery) use ($q) {
                $searchQuery->where('title', 'ilike', "%{$q}%")
                    ->orWhereHas('seller', fn ($sellerQuery) => $sellerQuery->where('name', 'ilike', "%{$q}%"));
            });
        }

        if ($categoryId = ($validated['category_id'] ?? null)) {
            $category = Category::query()->findOrFail($categoryId);
            $categoryIds = array_merge([$category->id], $category->descendant_ids);

            $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds));
        }

        if (($validated['min_price'] ?? null) !== null) {
            $query->where('current_price', '>=', (float) $validated['min_price']);
        }

        if (($validated['max_price'] ?? null) !== null) {
            $query->where('current_price', '<=', (float) $validated['max_price']);
        }

        $sort = $validated['sort'] ?? 'ending_soon';
        $query->when($sort === 'ending_soon', fn ($builder) => $builder->orderBy('end_time'))
            ->when($sort === 'newest', fn ($builder) => $builder->orderByDesc('created_at'))
            ->when($sort === 'price_asc', fn ($builder) => $builder->orderBy('current_price'))
            ->when($sort === 'price_desc', fn ($builder) => $builder->orderByDesc('current_price'));

        $auctions = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();
        $auctions->getCollection()->transform(function (Auction $auction) {
            $auction->current_price = $this->engine->getCurrentPrice($auction);

            return $auction;
        });

        return AuctionResource::collection($auctions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auctions/{auction}",
     *     summary="Get auction details",
     *     tags={"Auctions"},
     *     @OA\Parameter(name="auction", in="path", required=true, description="Auction ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Auction details",
     *         @OA\JsonContent(ref="#/components/schemas/AuctionResource")
     *     ),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show(Auction $auction): AuctionResource
    {
        $auction->loadCount('bids');
        $auction->load(['seller:id,name,seller_slug', 'media', 'brand', 'categories', 'attributeValues.attribute']);
        $auction->current_price = $this->engine->getCurrentPrice($auction);

        return new AuctionResource($auction);
    }
}
