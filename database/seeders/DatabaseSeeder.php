<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Temp;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    // public function run(): void
    // {
    //     // User::factory(10)->create();

    //     User::factory()->create([
    //         'name' => 'Test User',
    //         'email' => 'test@example.com',
    //     ]);
    // }

     public function run(): void
    {
        $this->call(PermissionSeeder::class);

        foreach (Temp::mock() as $item) {
            Product::create([
                'code'             => $item->code,
                'name'             => $item->name,
                'description'      => $item->description,
                'sell_price'       => $item->price ?? 0,
                'cost'             => $item->cost ?? 0,
                'vat'              => $item->vat ?? 0,
                'discount_percent' => $item->discount_percent ?? 0,
                'image'            => $item->image ?? null,
                'unit'             => $item->unit ?? null,

                // Fields you want fixed/default
                'track_stock'      => true,
                'status'           => 1,
                'Tax'              => 10,
                'category_id'      => 1,   // you can randomize 1 or 2 if you want
                'category_name'    => $item->category ?? null,
                'allow_discount'   => 1,
                'allow_return'     => 1,
                'max_stock'        => 500,
                'min_stock'        => 50,

                // Optional / null
                'bar_code'         => null,
                'variant'          => null,
                'last_purchase_price' => null,
            ]);
        }
    }
}
