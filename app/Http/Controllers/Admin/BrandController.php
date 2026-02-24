<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('auctions')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('admin.brands.create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $brand = Brand::create([
            'name'        => $validated['name'],
            'website'     => $validated['website'] ?? null,
            'is_verified' => $validated['is_verified'] ?? false,
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $brand->update(['logo_path' => $path]);
        }

        return redirect()->route('admin.brands.index')
            ->with('status', "Brand \"{$brand->name}\" created.");
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validated();

        $brand->update([
            'name'        => $validated['name'] ?? $brand->name,
            'website'     => $validated['website'] ?? $brand->website,
            'is_verified' => $validated['is_verified'] ?? $brand->is_verified,
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $brand->update(['logo_path' => $path]);
        }

        return redirect()->route('admin.brands.index')
            ->with('status', "Brand \"{$brand->name}\" updated.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $name = $brand->name;
        $brand->delete();

        return redirect()->route('admin.brands.index')
            ->with('status', "Brand \"{$name}\" deleted.");
    }
}
