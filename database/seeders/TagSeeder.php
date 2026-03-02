<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Vintage', 'type' => 'general', 'color' => '#8B4513'],
            ['name' => 'Rare', 'type' => 'general', 'color' => '#DC143C'],
            ['name' => 'Limited Edition', 'type' => 'general', 'color' => '#FFD700'],
            ['name' => 'Sealed', 'type' => 'condition-detail', 'color' => '#228B22'],
            ['name' => 'Mint Condition', 'type' => 'condition-detail', 'color' => '#32CD32'],
            ['name' => 'Bundle', 'type' => 'general', 'color' => '#4169E1'],
            ['name' => 'Free Shipping', 'type' => 'promo', 'color' => '#FF6347'],
            ['name' => 'No Reserve', 'type' => 'promo', 'color' => '#FF4500'],
            ['name' => 'Estate Sale', 'type' => 'general', 'color' => '#8B7D6B'],
            ['name' => 'Handmade', 'type' => 'general', 'color' => '#DEB887'],
            ['name' => 'Authenticated', 'type' => 'general', 'color' => '#006400'],
            ['name' => 'With Box', 'type' => 'condition-detail', 'color' => '#808080'],
            ['name' => 'With Papers', 'type' => 'condition-detail', 'color' => '#696969'],
            ['name' => 'Collector Item', 'type' => 'general', 'color' => '#B8860B'],
            ['name' => 'Custom', 'type' => 'general', 'color' => '#9370DB'],
            ['name' => 'Wholesale', 'type' => 'promo', 'color' => '#20B2AA'],
            ['name' => 'Charity', 'type' => 'promo', 'color' => '#FF69B4'],
            ['name' => 'Import', 'type' => 'general', 'color' => '#4682B4'],
            ['name' => 'Restored', 'type' => 'condition-detail', 'color' => '#DAA520'],
            ['name' => 'One of a Kind', 'type' => 'general', 'color' => '#800080'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['name' => $tag['name']],
                [
                    ...$tag,
                    'slug' => Str::slug($tag['name']),
                ]
            );
        }

        $this->command->info('Seeded ' . Tag::count() . ' tags.');
    }
}
