<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Storage;

test('category seeder generates public category images', function () {
    Storage::fake('public');

    $this->seed(CategorySeeder::class);

    $categories = Category::all();

    expect($categories)->not->toBeEmpty();

    foreach ($categories as $category) {
        expect($category->image_path)->not->toBeNull();
        Storage::disk('public')->assertExists($category->image_path);
    }
});
