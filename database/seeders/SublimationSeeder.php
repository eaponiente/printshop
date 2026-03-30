<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class SublimationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Pluck IDs once to avoid repeated queries in the loop
        $tagIds = Tag::pluck('id');
        $customerIds = Customer::pluck('id');
        // Corrected where clause: use whereIn for arrays
        $userIds = User::whereIn('role', ['staff', 'admin'])->pluck('id');
        $branchIds = Branch::pluck('id');

        // Realistic data sets for sublimation business
        $products = [
            'Full Sublimation Jersey' => [
                'Basketball set (Liga style)',
                'Volleyball uniform',
                'eSports jersey (Team Gaming)',
                'Badminton shirt (Inter-agency)',
            ],
            'Corporate Lanyard' => [
                '1-inch nylon (Official ID)',
                'Smooth polyester with G-hook',
                'Digital print (Event access)',
            ],
            'Custom Hoodie' => [
                'Pullover with back print',
                'Zip-up with chest logo',
                'Premium thick cotton (Media Jacket)',
            ],
            'Drifit Shirt' => [
                'Marathon event shirt',
                'Company outing giveaway',
                'Barangay Health Worker (BHW) uniform',
            ],
            'Tote Bag' => [
                'Canvas material with full color print',
                'Non-woven eco-bag (Grocery size)',
            ],
            'Banner/Flag' => [
                'Teardrop flag',
                'Street banner for fiesta',
                'Retractable Pull-up banner (Presscon)',
            ],
            // --- New Political & Campaign Specific Items ---
            'Campaign Paraphernalia' => [
                'Vinyl Sticker (Waterproof bumper)',
                'Foldable Hand Fan (Full color)',
                'Button Pins (Matte/Glossy)',
                'Baller Bands (Silicone debossed)',
            ],
            'Tactical/Campaign Vest' => [
                'Multi-pocket mesh vest',
                'Reflective safety vest (DPWH style)',
            ],
            'Headwear' => [
                'Embroidered Baseball Cap',
                'Sublimated Bucket Hat',
                'Custom Bandana/Buff',
            ],
            'Drinkware (Merch)' => [
                'Tumbler with UV Print',
                'Ceramic Mug (Masa giveaway)',
            ],
        ];

        foreach (range(1, 40) as $index) {
            $particular = $faker->randomElement(array_keys($products));
            // Pick a sub-description from that category
            $subDesc = $faker->randomElement($products[$particular]);

            $status = $faker->randomElement(['pending', 'active', 'finished', 'released']);

            $sublimation = Sublimation::create([
                'branch_id' => $branchIds->random(),
                'customer_id' => $customerIds->random(),
                'user_id' => $userIds->random(),
                'status' => $status,
                'notes' => fake()->paragraph,
                'description' => "Order for {$faker->numberBetween(10, 100)} pcs: $subDesc. ".$faker->sentence(),
                // Randomize dates: some due soon, some later
                'due_at' => now()->subDays(rand(2, 30)),
                'created_at' => now()->subDays(rand(30, 60)),
            ]);

            // Attach random tags
            if ($tagIds->isNotEmpty()) {
                $randomTags = $tagIds->random(rand(1, min(3, $tagIds->count())))->toArray();
                $sublimation->tags()->attach($randomTags);
            }
        }
    }
}
