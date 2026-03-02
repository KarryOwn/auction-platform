<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        // Global attributes
        $weight = Attribute::updateOrCreate(['name' => 'Weight'], [
            'name' => 'Weight', 'type' => 'number', 'unit' => 'kg',
            'is_filterable' => true, 'sort_order' => 100,
            'slug' => Str::slug('Weight'),
        ]);
        $dimensions = Attribute::updateOrCreate(['name' => 'Dimensions'], [
            'name' => 'Dimensions', 'type' => 'text',
            'is_filterable' => false, 'sort_order' => 101,
            'slug' => Str::slug('Dimensions'),
        ]);
        $color = Attribute::updateOrCreate(['name' => 'Color'], [
            'name' => 'Color', 'type' => 'select',
            'options' => ['Black', 'White', 'Silver', 'Gold', 'Blue', 'Red', 'Green', 'Pink', 'Purple', 'Other'],
            'is_filterable' => true, 'sort_order' => 1,
            'slug' => Str::slug('Color'),
        ]);

        // Electronics attributes
        $screenSize = Attribute::updateOrCreate(['name' => 'Screen Size'], [
            'name' => 'Screen Size', 'type' => 'number', 'unit' => 'inches',
            'is_filterable' => true, 'sort_order' => 2,
            'slug' => Str::slug('Screen Size'),
        ]);
        $storage = Attribute::updateOrCreate(['name' => 'Storage'], [
            'name' => 'Storage', 'type' => 'select',
            'options' => ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB', '2TB'],
            'is_filterable' => true, 'sort_order' => 3,
            'slug' => Str::slug('Storage'),
        ]);
        $ram = Attribute::updateOrCreate(['name' => 'RAM'], [
            'name' => 'RAM', 'type' => 'select',
            'options' => ['2GB', '4GB', '6GB', '8GB', '12GB', '16GB', '32GB', '64GB'],
            'is_filterable' => true, 'sort_order' => 4,
            'slug' => Str::slug('RAM'),
        ]);
        $processor = Attribute::updateOrCreate(['name' => 'Processor'], [
            'name' => 'Processor', 'type' => 'text',
            'is_filterable' => false, 'sort_order' => 5,
            'slug' => Str::slug('Processor'),
        ]);
        $batteryLife = Attribute::updateOrCreate(['name' => 'Battery Life'], [
            'name' => 'Battery Life', 'type' => 'text',
            'is_filterable' => false, 'sort_order' => 6,
            'slug' => Str::slug('Battery Life'),
        ]);

        // Fashion attributes
        $size = Attribute::updateOrCreate(['name' => 'Size'], [
            'name' => 'Size', 'type' => 'select',
            'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
            'is_filterable' => true, 'sort_order' => 1,
            'slug' => Str::slug('Size'),
        ]);
        $material = Attribute::updateOrCreate(['name' => 'Material'], [
            'name' => 'Material', 'type' => 'text',
            'is_filterable' => true, 'sort_order' => 2,
            'slug' => Str::slug('Material'),
        ]);
        $shoeSize = Attribute::updateOrCreate(['name' => 'Shoe Size'], [
            'name' => 'Shoe Size', 'type' => 'select',
            'options' => ['5', '6', '7', '8', '9', '10', '11', '12', '13', '14'],
            'is_filterable' => true, 'sort_order' => 1,
            'slug' => Str::slug('Shoe Size'),
        ]);

        // Vehicle attributes
        $mileage = Attribute::updateOrCreate(['name' => 'Mileage'], [
            'name' => 'Mileage', 'type' => 'number', 'unit' => 'miles',
            'is_filterable' => true, 'sort_order' => 1,
            'slug' => Str::slug('Mileage'),
        ]);
        $year = Attribute::updateOrCreate(['name' => 'Year'], [
            'name' => 'Year', 'type' => 'number',
            'is_filterable' => true, 'sort_order' => 2,
            'slug' => Str::slug('Year'),
        ]);
        $fuelType = Attribute::updateOrCreate(['name' => 'Fuel Type'], [
            'name' => 'Fuel Type', 'type' => 'select',
            'options' => ['Gasoline', 'Diesel', 'Electric', 'Hybrid', 'Other'],
            'is_filterable' => true, 'sort_order' => 3,
            'slug' => Str::slug('Fuel Type'),
        ]);
        $transmission = Attribute::updateOrCreate(['name' => 'Transmission'], [
            'name' => 'Transmission', 'type' => 'select',
            'options' => ['Automatic', 'Manual', 'CVT'],
            'is_filterable' => true, 'sort_order' => 4,
            'slug' => Str::slug('Transmission'),
        ]);

        // Watch attributes
        $caseMaterial = Attribute::updateOrCreate(['name' => 'Case Material'], [
            'name' => 'Case Material', 'type' => 'select',
            'options' => ['Stainless Steel', 'Gold', 'Titanium', 'Ceramic', 'Platinum', 'Carbon'],
            'is_filterable' => true, 'sort_order' => 1,
            'slug' => Str::slug('Case Material'),
        ]);
        $movementType = Attribute::updateOrCreate(['name' => 'Movement Type'], [
            'name' => 'Movement Type', 'type' => 'select',
            'options' => ['Automatic', 'Quartz', 'Manual Wind', 'Solar'],
            'is_filterable' => true, 'sort_order' => 2,
            'slug' => Str::slug('Movement Type'),
        ]);

        // Assign attributes to categories
        $this->assignToCategory('Electronics', [$color, $weight, $dimensions]);
        $this->assignToCategory('Smartphones', [$screenSize, $storage, $ram, $processor, $batteryLife]);
        $this->assignToCategory('Laptops & Computers', [$screenSize, $storage, $ram, $processor]);
        $this->assignToCategory('Tablets', [$screenSize, $storage, $ram]);
        $this->assignToCategory('Audio & Headphones', [$color]);
        $this->assignToCategory('Cameras & Photography', [$color]);
        $this->assignToCategory('Gaming', [$storage, $ram]);

        $this->assignToCategory('Fashion', [$color, $material]);
        $this->assignToCategory("Men's Clothing", [$size]);
        $this->assignToCategory("Women's Clothing", [$size]);
        $this->assignToCategory('Shoes', [$shoeSize, $color]);
        $this->assignToCategory('Watches', [$caseMaterial, $movementType]);
        $this->assignToCategory('Jewelry', [$material]);

        $this->assignToCategory('Vehicles', [$year, $mileage, $fuelType, $transmission, $color]);
        $this->assignToCategory('Cars', []);
        $this->assignToCategory('Motorcycles', []);

        $this->assignToCategory('Home & Garden', [$color, $weight, $dimensions]);

        $this->command->info('Seeded ' . Attribute::count() . ' attributes with category assignments.');
    }

    private function assignToCategory(string $categoryName, array $attributes): void
    {
        $category = Category::where('name', $categoryName)->first();

        if (! $category || empty($attributes)) {
            return;
        }

        foreach ($attributes as $attribute) {
            $category->attributes()->syncWithoutDetaching([
                $attribute->id => ['is_required' => false],
            ]);
        }
    }
}
