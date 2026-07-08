<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillCustomerPhone extends Command
{
    /**
     * @var string
     */
    protected $signature = 'customers:backfill-phone';

    /**
     * @var string
     */
    protected $description = 'Populate customers.phone_normalized (E.164) from existing phone values.';

    public function handle(): int
    {
        $updated = 0;

        Customer::query()
            ->whereNotNull('phone')
            ->chunkById(200, function (Collection $customers) use (&$updated): void {
                /** @var Customer $customer */
                foreach ($customers as $customer) {
                    $normalized = PhoneNormalizer::e164($customer->phone);

                    if ($normalized !== $customer->phone_normalized) {
                        $customer->phone_normalized = $normalized;
                        $customer->saveQuietly();
                        $updated++;
                    }
                }
            });

        $this->info("Backfilled phone_normalized for {$updated} customer(s).");

        return self::SUCCESS;
    }
}
