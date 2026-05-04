<?php

namespace Database\Seeders;

use App\Enums\Sales\TransactionTypeOfPaymentEnum;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create();

        $users = User::whereIn('role', ['admin', 'staff'])->get();

        if ($users->isEmpty()) {
            $this->command->error('No users found to assign Purchase Orders. Seed users first.');
            return;
        }

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

        $paymentMethods = TransactionTypeOfPaymentEnum::cases();

        foreach (range(1, rand(30, 50)) as $index) {
            $user = $users->random();
            auth()->login($user);

            $orderedAt = Carbon::now()->subDays(rand(0, 30));
            $dueAt = Carbon::parse($orderedAt)->addDays(rand(4, 8));
            $status = $faker->randomElement(['pending', 'active', 'finished', 'released']);

            // CREATE PO — matches PurchaseOrderController@store
            $po = PurchaseOrder::create([
                'po_number' => 'PO-' . $faker->unique()->numerify('#####'),
                'description' => $faker->sentence(10),
                'status' => $status,
                'grand_total' => 0,
                'received_at' => $orderedAt,
                'due_at' => $dueAt,
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                'customer_id' => Customer::inRandomOrder()->first()->id,
                'transaction_id' => null,
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);

            $runningTotal = 0;
            $selectedItems = $faker->randomElements($inventoryItems, rand(2, 6));

            foreach ($selectedItems as $item) {
                $qty = rand(1, 10);
                $subTotal = $qty * $item['price'];

                DB::table('purchase_order_details')->insert([
                    'purchase_order_id' => $po->id,
                    'item_name' => $item['name'],
                    'quantity' => $qty,
                    'unit_price' => $item['price'],
                    'created_at' => $orderedAt,
                    'updated_at' => $orderedAt,
                ]);

                $runningTotal += $subTotal;
            }

            $po->update(['grand_total' => $runningTotal]);

            // CREATE LINKED TRANSACTION — matches PurchaseOrderController@createTransaction
            // Only for POs that have moved past "pending" status
            if (in_array($status, ['active', 'finished', 'released'])) {
                $transaction = app(SalesService::class)->createTransaction([
                    'description' => 'Purchase Order: ' . $po->po_number,
                    'branch_id' => $po->branch_id,
                    'customer_id' => $po->customer_id,
                    'invoice_number' => Transaction::generateNumber(),
                    'amount_total' => $runningTotal,
                    'particular' => 'Purchase Order',
                    'staff_id' => $po->user_id,
                    'transaction_date' => $po->created_at,
                    'created_at' => $po->created_at,
                    'updated_at' => $po->created_at,
                ]);

                $po->update(['transaction_id' => $transaction->id]);

                // RECORD PAYMENTS — matches SaleController@updatePayment flow
                // Finished/released: pay in full. Active: pay partial.
                if ($status === 'finished' || $status === 'released') {
                    $paymentType = collect($paymentMethods)->random()->value;

                    $transaction->recordPayment($runningTotal, $paymentType);

                    if ($paymentType === TransactionTypeOfPaymentEnum::CASH->value) {
                        app(CashOnHandService::class)->adjustBalance(
                            $transaction->branch_id,
                            $runningTotal,
                            'revenue'
                        );
                    }
                } elseif ($status === 'active') {
                    // Partial payment (30-70% of total)
                    $partialAmount = round($runningTotal * (rand(30, 70) / 100), 2);
                    $paymentType = collect($paymentMethods)->random()->value;

                    $transaction->recordPayment($partialAmount, $paymentType);

                    if ($paymentType === TransactionTypeOfPaymentEnum::CASH->value) {
                        app(CashOnHandService::class)->adjustBalance(
                            $transaction->branch_id,
                            $partialAmount,
                            'revenue'
                        );
                    }
                }
            }
        }

        $this->command->info('PurchaseOrderSeeder completed successfully.');
    }
}
