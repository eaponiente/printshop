<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create();

        // 1. Get valid staff/admins for assignment
        $users = User::whereIn('role', ['admin', 'staff'])->get();

        if ($users->isEmpty()) {
            $this->command->error('No users found to assign Purchase Orders. Seed users first.');

            return;
        }

        // 2. Define realistic items for the Details table
        $inventoryItems = [
            ['name' => 'Drifit Fabric - White', 'unit' => 'Roll', 'price' => 4500],
            ['name' => 'Sublimation Ink - Cyan (1L)', 'unit' => 'Bottle', 'price' => 1200],
            ['name' => 'Sublimation Ink - Magenta (1L)', 'unit' => 'Bottle', 'price' => 1200],
            ['name' => 'Sublimation Ink - Yellow (1L)', 'unit' => 'Bottle', 'price' => 1200],
            ['name' => 'Sublimation Ink - Black (1L)', 'unit' => 'Bottle', 'price' => 1500],
            ['name' => 'Transfer Paper (100m)', 'unit' => 'Roll', 'price' => 2800],
            ['name' => 'Garter 1-inch', 'unit' => 'Roll', 'price' => 350],
            ['name' => 'Sewing Thread - Black', 'unit' => 'Box', 'price' => 180],
        ];

        // 3. Create 30-50 realistic orders
        foreach (range(1, rand(30, 50)) as $index) {

            $user = $users->random();
            $orderedAt = Carbon::now()->subDays(rand(0, 30));
            $dueAt = Carbon::parse($orderedAt)->addDays(rand(4,8));
            $status = $faker->randomElement(['pending', 'active', 'finished', 'released']);

            // Create the Parent PO
            $po = PurchaseOrder::create([
                'po_number' => 'PO-'.$faker->unique()->numerify('#####'),
                'description' => $faker->sentence(10),
                'status' => $status,
                'grand_total' => 0, // Placeholder
                'received_at' => $orderedAt,
                'due_at' => $dueAt,
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);

            $runningTotal = 0;

            // 4. Create 2-6 Details for each PO
            $selectedItems = $faker->randomElements($inventoryItems, rand(2, 6));

            foreach ($selectedItems as $item) {
                $qty = rand(1, 10);
                $unitPrice = $item['price'];
                $subTotal = $qty * $unitPrice;

                // Create the Detail row manually
                DB::table('purchase_order_details')->insert([
                    'purchase_order_id' => $po->id,
                    'item_name' => $item['name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'created_at' => $orderedAt,
                    'updated_at' => $orderedAt,
                ]);

                $runningTotal += $subTotal;
            }

            // 5. Finalize the Parent Total
            $po->update(['grand_total' => $runningTotal]);
        }

        $this->command->info('PurchaseOrderSeeder completed successfully.');
    }
}
