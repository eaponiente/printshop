<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class SublimationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tagIds = Tag::pluck('id');
        $userIds = User::where('role', ['staff', 'admin'])->pluck('id');
        $branchIds = Branch::pluck('id');

        foreach (range(1, 40) as $index) {
            $sublimation = Sublimation::create([
                'name' => fake()->streetName,
                'branch_id' => $branchIds->random(),
                'user_id' => $userIds->random(),
            ]);

            $randomTags = $tagIds->random(rand(1, 3))->toArray();
            $sublimation->tags()->attach($randomTags);
        }
    }
}
