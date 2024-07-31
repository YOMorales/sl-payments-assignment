<?php

namespace App\Console\Commands;

use App\Util\ExchangeRate;
use Carbon\Carbon;
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
                'status' => 'all',
                'test_clock' => config('stripe.test_clock'),
                'expand' => ['data.customer', 'data.plan.product'],
            ]);

            // using toArray() to weed out a lot of metadata in the Stripe response
            $subscriptions = $subscriptions->toArray()['data'];
            $subscriptionsByProduct = collect($subscriptions)->groupBy('plan.product.name');

            $tableData = [];

            // TODO: keep using collections
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
                        'limit' => 100,
                    ]);

                    /*
                    From the Stripe docs: "The invoices are returned sorted by creation date, with the most recently created
                    invoices appearing first."
                    So we need to reverse the order here, with oldest appearing first.
                    Then truncate to 12 invoices, as this report only needs the next 12 months.
                    */
                    $invoices = array_reverse($invoices->toArray()['data']);
                    $invoices = array_slice($invoices, 0, 12);


                    $lifeTimeValue = 0;

                    foreach ($invoices as $invoiceIndex => $invoice) {
                        /*
                        does currency conversion using ExchangeRate class
                        YOM: this amount may not be 100% accurate due to variances in actual market exchange rates
                        used by Stripe vs static rates in ExchangeRate class.
                        */
                        $amountInUSD = match ($invoice['currency']) {
                            'gbp' => bcmul($invoice['amount_paid'], ExchangeRate::$GBP_TO_USD, 2),
                            'eur' => bcmul($invoice['amount_paid'], ExchangeRate::$GBP_TO_USD, 2),
                            default => $invoice['amount_paid'],
                        };

                        $tableData[$productName][$key]["endOfMonth $invoiceIndex"] = bcdiv($amountInUSD, 100, 2);
                        $lifeTimeValue += $amountInUSD;
                    }

                    $tableData[$productName][$key]['Life Time Value'] = bcdiv($lifeTimeValue, 100, 2);
                }

                // add one last row to $tableData[$productName] that sums up all the values in endOfMonth columns
                $tableData[$productName]['Total'] = array_reduce(
                    $tableData[$productName],
                    function ($carry, $item) {
                        foreach ($item as $key => $value) {
                            if ($key === 'Customer Email' || $key === 'Product Name') {
                                continue;
                            }

                            $carry[$key] = ($carry[$key] ?? 0) + $value;
                        }

                        return $carry;
                    },
                    []
                );
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
