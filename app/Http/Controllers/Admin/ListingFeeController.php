<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingFeeTier;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListingFeeController extends Controller
{
    public function index()
    {
        $tiers = ListingFeeTier::with('category')->orderBy('sort_order')->get();
        return view('admin.listing-fees.index', compact('tiers'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.listing-fees.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starting_price_min' => ['nullable', 'numeric', 'min:0'],
            'starting_price_max' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        ListingFeeTier::create($validated);

        return redirect()->route('admin.listing-fees.index')->with('success', 'Tier created.');
    }

    public function edit(ListingFeeTier $listingFee)
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.listing-fees.edit', ['tier' => $listingFee, 'categories' => $categories]);
    }

    public function update(Request $request, ListingFeeTier $listingFee)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starting_price_min' => ['nullable', 'numeric', 'min:0'],
            'starting_price_max' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $listingFee->update($validated);

        return redirect()->route('admin.listing-fees.index')->with('success', 'Tier updated.');
    }

    public function destroy(ListingFeeTier $listingFee)
    {
        $listingFee->delete();
        return redirect()->route('admin.listing-fees.index')->with('success', 'Tier deleted.');
    }
}
