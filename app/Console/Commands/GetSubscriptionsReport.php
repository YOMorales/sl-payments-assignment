<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;
use Throwable;

class GetSubscriptionsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sl:get-subscriptions-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a report of all subscriptions in Stripe.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            //$stripeClient = new StripeClient(config('stripe.secret_key'));

            $tableHeaders = [
                'Customer Email',
                'Product Name',
                // TODO: fill these later with the real end of month dates
                'endOfMonth 1',
                'endOfMonth 2',
                'endOfMonth 3',
                'endOfMonth 4',
                'endOfMonth 5',
                'endOfMonth 6',
                'endOfMonth 7',
                'endOfMonth 8',
                'endOfMonth 9',
                'endOfMonth 10',
                'endOfMonth 11',
                'endOfMonth 12',
                'Life Time Value',
            ];

            $tableData = [
                ['yomorales@gmail.com', 'Product 1', 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 1000],
            ];

            $this->table(
                $tableHeaders,
                $tableData
            );

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
