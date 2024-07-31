<?php

namespace App\Console\Commands;

use App\Util\ExchangeRate;
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
            $stripeClient = new StripeClient(config('stripe.secret_key'));

            // Stripe normally will not return subscriptions attached to a test clock, so we specify it here
            $subscriptions = $stripeClient->subscriptions->all([
                'test_clock' => config('stripe.test_clock'),
                'expand' => ['data.customer', 'data.plan.product'],
            ]);

            // using toArray() to weed out a lot of metadata in the Stripe response
            $subscriptions = $subscriptions->toArray()['data'];
            $subscriptionsByProduct = collect($subscriptions)->groupBy('plan.product.name');

            $tableData = [];

            foreach ($subscriptionsByProduct as $productName => $subscriptionGroup) {
                foreach ($subscriptionGroup as $key => $subscription) {
                    $tableData[$productName][$key] = [
                        'Customer Email' => $subscription['customer']['email'],
                        'Product Name' => $productName,
                    ];

                    // get invoices for this subscription
                    $subscriptionId = $subscription['id'];
                    $invoices = $stripeClient->invoices->search([
                        'query' => "subscription:'$subscriptionId'",
                    ]);

                    foreach ($invoices as $invoiceNumber => $invoice) {
                        // does currency conversion using ExchangeRate class
                        // YOM: this amount may not be 100% accurate due to variances in actual market exchange rates vs static rates in ExchangeRate class
                        $amountInUSD = match ($invoice->lines->data[0]->currency) {
                            'gbp' => $invoice->lines->data[0]->amount * ExchangeRate::$GBP_TO_USD,
                            'eur' => $invoice->lines->data[0]->amount * ExchangeRate::$EUR_TO_USD,
                            default => $invoice->lines->data[0]->amount,
                        };

                        $tableData[$productName][$key]["invoice_$invoiceNumber"] = sprintf("$%s", number_format($amountInUSD / 100, 2));
                    }
                }
            }

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

            $this->table(
                $tableHeaders,
                $tableData['Crossclip']
            );

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
