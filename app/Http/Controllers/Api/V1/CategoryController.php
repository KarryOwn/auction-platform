<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/categories",
     *     summary="List categories",
     *     tags={"Categories"},
     *     @OA\Parameter(name="parent_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=50)),
     *     @OA\Response(response=200, description="Paginated category list")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categories = Category::query()
            ->active()
            ->when(
                array_key_exists('parent_id', $validated),
                fn ($query) => $query->where('parent_id', $validated['parent_id']),
                fn ($query) => $query->whereNull('parent_id')
            )
            ->withCount(['children', 'auctions'])
            ->ordered()
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{category}",
     *     summary="Get category details",
     *     tags={"Categories"},
     *     @OA\Parameter(name="category", in="path", required=true, description="Category ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category details"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show(Category $category): CategoryResource
    {
        $category->load([
            'parent',
            'children' => fn ($query) => $query->active()->ordered(),
        ]);
        $category->loadCount(['children', 'auctions']);

        return new CategoryResource($category);
    }
}
