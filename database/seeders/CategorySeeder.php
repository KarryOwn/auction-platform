<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'icon' => 'fas fa-laptop',
                'children' => [
                    ['name' => 'Smartphones', 'icon' => 'fas fa-mobile-alt'],
                    ['name' => 'Laptops & Computers', 'icon' => 'fas fa-laptop'],
                    ['name' => 'Audio & Headphones', 'icon' => 'fas fa-headphones'],
                    ['name' => 'Cameras & Photography', 'icon' => 'fas fa-camera'],
                    ['name' => 'Gaming', 'icon' => 'fas fa-gamepad'],
                    ['name' => 'Tablets', 'icon' => 'fas fa-tablet-alt'],
                    ['name' => 'Wearable Tech', 'icon' => 'fas fa-clock'],
                ],
            ],
            [
                'name' => 'Collectibles & Art',
                'icon' => 'fas fa-palette',
                'children' => [
                    ['name' => 'Coins & Currency', 'icon' => 'fas fa-coins'],
                    ['name' => 'Trading Cards', 'icon' => 'fas fa-id-card'],
                    ['name' => 'Antiques', 'icon' => 'fas fa-hourglass'],
                    ['name' => 'Fine Art', 'icon' => 'fas fa-paint-brush'],
                    ['name' => 'Stamps', 'icon' => 'fas fa-stamp'],
                    ['name' => 'Memorabilia', 'icon' => 'fas fa-trophy'],
                ],
            ],
            [
                'name' => 'Fashion',
                'icon' => 'fas fa-tshirt',
                'children' => [
                    ['name' => "Men's Clothing", 'icon' => 'fas fa-male'],
                    ['name' => "Women's Clothing", 'icon' => 'fas fa-female'],
                    ['name' => 'Watches', 'icon' => 'fas fa-clock'],
                    ['name' => 'Jewelry', 'icon' => 'fas fa-gem'],
                    ['name' => 'Shoes', 'icon' => 'fas fa-shoe-prints'],
                    ['name' => 'Bags & Accessories', 'icon' => 'fas fa-shopping-bag'],
                ],
            ],
            [
                'name' => 'Home & Garden',
                'icon' => 'fas fa-home',
                'children' => [
                    ['name' => 'Furniture', 'icon' => 'fas fa-couch'],
                    ['name' => 'Tools', 'icon' => 'fas fa-tools'],
                    ['name' => 'Appliances', 'icon' => 'fas fa-blender'],
                    ['name' => 'Décor', 'icon' => 'fas fa-vase'],
                    ['name' => 'Garden & Outdoor', 'icon' => 'fas fa-seedling'],
                ],
            ],
            [
                'name' => 'Vehicles',
                'icon' => 'fas fa-car',
                'children' => [
                    ['name' => 'Cars', 'icon' => 'fas fa-car'],
                    ['name' => 'Motorcycles', 'icon' => 'fas fa-motorcycle'],
                    ['name' => 'Parts & Accessories', 'icon' => 'fas fa-cogs'],
                    ['name' => 'Boats', 'icon' => 'fas fa-ship'],
                ],
            ],
            [
                'name' => 'Sports & Outdoors',
                'icon' => 'fas fa-football-ball',
                'children' => [
                    ['name' => 'Fitness Equipment', 'icon' => 'fas fa-dumbbell'],
                    ['name' => 'Cycling', 'icon' => 'fas fa-bicycle'],
                    ['name' => 'Camping & Hiking', 'icon' => 'fas fa-campground'],
                    ['name' => 'Sports Memorabilia', 'icon' => 'fas fa-trophy'],
                ],
            ],
            [
                'name' => 'Books & Media',
                'icon' => 'fas fa-book',
                'children' => [
                    ['name' => 'Books', 'icon' => 'fas fa-book-open'],
                    ['name' => 'Vinyl & Records', 'icon' => 'fas fa-record-vinyl'],
                    ['name' => 'Movies & TV', 'icon' => 'fas fa-film'],
                    ['name' => 'Video Games', 'icon' => 'fas fa-gamepad'],
                ],
            ],
            [
                'name' => 'Toys & Hobbies',
                'icon' => 'fas fa-puzzle-piece',
                'children' => [
                    ['name' => 'Action Figures', 'icon' => 'fas fa-robot'],
                    ['name' => 'Model Kits', 'icon' => 'fas fa-plane'],
                    ['name' => 'Board Games', 'icon' => 'fas fa-chess'],
                    ['name' => 'RC Vehicles', 'icon' => 'fas fa-car'],
                ],
            ],
        ];

        $sortOrder = 0;
        foreach ($categories as $root) {
            $parent = Category::firstOrCreate(
                ['name' => $root['name'], 'parent_id' => null],
                [
                    'slug'       => Str::slug($root['name']),
                    'icon'       => $root['icon'],
                    'sort_order' => $sortOrder,
                    'is_active'  => true,
                ]
            );

            $parent->update([
                'icon'       => $root['icon'],
                'sort_order' => $sortOrder++,
                'is_active'  => true,
            ]);

            $childOrder = 0;
            foreach ($root['children'] as $child) {
                $childCategory = Category::firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $child['name']],
                    [
                        'slug'       => Str::slug($child['name']),
                        'icon'       => $child['icon'],
                        'sort_order' => $childOrder,
                        'is_active'  => true,
                        'depth'      => $parent->depth + 1,
                        'path'       => $parent->path ? $parent->path . '/' . $parent->id : (string) $parent->id,
                    ]
                );

                $childCategory->update([
                    'icon'       => $child['icon'],
                    'sort_order' => $childOrder++,
                    'is_active'  => true,
                    'depth'      => $parent->depth + 1,
                    'path'       => $parent->path ? $parent->path . '/' . $parent->id : (string) $parent->id,
                ]);
            }
        }

        $this->command->info('Seeded ' . Category::count() . ' categories.');
    }
}
