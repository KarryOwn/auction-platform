<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Apple', 'website' => 'https://apple.com', 'is_verified' => true],
            ['name' => 'Samsung', 'website' => 'https://samsung.com', 'is_verified' => true],
            ['name' => 'Sony', 'website' => 'https://sony.com', 'is_verified' => true],
            ['name' => 'Nike', 'website' => 'https://nike.com', 'is_verified' => true],
            ['name' => 'Adidas', 'website' => 'https://adidas.com', 'is_verified' => true],
            ['name' => 'Canon', 'website' => 'https://canon.com', 'is_verified' => true],
            ['name' => 'Nikon', 'website' => 'https://nikon.com', 'is_verified' => true],
            ['name' => 'Dell', 'website' => 'https://dell.com', 'is_verified' => true],
            ['name' => 'HP', 'website' => 'https://hp.com', 'is_verified' => true],
            ['name' => 'Lenovo', 'website' => 'https://lenovo.com', 'is_verified' => true],
            ['name' => 'Microsoft', 'website' => 'https://microsoft.com', 'is_verified' => true],
            ['name' => 'Nintendo', 'website' => 'https://nintendo.com', 'is_verified' => true],
            ['name' => 'Rolex', 'website' => 'https://rolex.com', 'is_verified' => true],
            ['name' => 'Omega', 'website' => 'https://omegawatches.com', 'is_verified' => true],
            ['name' => 'Louis Vuitton', 'website' => 'https://louisvuitton.com', 'is_verified' => true],
            ['name' => 'Gucci', 'website' => 'https://gucci.com', 'is_verified' => true],
            ['name' => 'Bose', 'website' => 'https://bose.com', 'is_verified' => true],
            ['name' => 'LG', 'website' => 'https://lg.com', 'is_verified' => true],
            ['name' => 'Toyota', 'website' => 'https://toyota.com', 'is_verified' => true],
            ['name' => 'Honda', 'website' => 'https://honda.com', 'is_verified' => true],
            ['name' => 'BMW', 'website' => 'https://bmw.com', 'is_verified' => true],
            ['name' => 'Panasonic', 'website' => 'https://panasonic.com', 'is_verified' => true],
            ['name' => 'Puma', 'website' => 'https://puma.com', 'is_verified' => true],
            ['name' => 'Lego', 'website' => 'https://lego.com', 'is_verified' => true],
            ['name' => 'Gibson', 'website' => 'https://gibson.com', 'is_verified' => true],
            ['name' => 'Fender', 'website' => 'https://fender.com', 'is_verified' => true],
            ['name' => 'Tiffany & Co.', 'website' => 'https://tiffany.com', 'is_verified' => true],
            ['name' => 'Cartier', 'website' => 'https://cartier.com', 'is_verified' => true],
            ['name' => 'ASUS', 'website' => 'https://asus.com', 'is_verified' => true],
            ['name' => 'Google', 'website' => 'https://store.google.com', 'is_verified' => true],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['name' => $brand['name']],
                [
                    ...$brand,
                    'slug' => Str::slug($brand['name']),
                ]
            );
        }

        $this->command->info('Seeded ' . Brand::count() . ' brands.');
    }
}
