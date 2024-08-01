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

            /*
            YOM: given sufficient time, I could refactor most of the code here to move code to other methods
            or classes. I personally don't like extensive methods with a lot of procedural code.
            Also, I started using Laravel collections above but inadvertedly switched back to arrays, so
            in a refactor, I would keep using Collection methods such as each(), map(), reduce(), etc.
            */
            $allTablesData = [];
            $rowTemplate = [
                'Customer Email' => '',
                'Product Name' => '',
                'endOfMonth 1' => null,
                'endOfMonth 2' => null,
                'endOfMonth 3' => null,
                'endOfMonth 4' => null,
                'endOfMonth 5' => null,
                'endOfMonth 6' => null,
                'endOfMonth 7' => null,
                'endOfMonth 8' => null,
                'endOfMonth 9' => null,
                'endOfMonth 10' => null,
                'endOfMonth 11' => null,
                'endOfMonth 12' => null,
                'Life Time Value' => null,
            ];

            foreach ($subscriptionsByProduct as $productName => $subscriptionGroup) {
                foreach ($subscriptionGroup as $key => $subscription) {
                    $allTablesData[$productName][$key] = $rowTemplate;
                    $allTablesData[$productName][$key]['Customer Email'] = $subscription['customer']['email'];
                    $allTablesData[$productName][$key]['Product Name'] = $productName;

                    // get invoices for this subscription
                    $subscriptionId = $subscription['id'];
                    $invoices = $stripeClient->invoices->search([
                        'query' => "subscription:'$subscriptionId'",
                        // grabbing 100 invoices to keep this simple and avoid paginating
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


                    $subscriptionLifeTimeValue = 0;

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

                        $allTablesData[$productName][$key]["endOfMonth " . $invoiceIndex+1] = '$' . bcdiv($amountInUSD, 100, 2);
                        $subscriptionLifeTimeValue = bcadd($subscriptionLifeTimeValue, $amountInUSD, 2);
                    }

                    $allTablesData[$productName][$key]['Life Time Value'] = '$' . bcdiv($subscriptionLifeTimeValue, 100, 2);
                }

                // add one last row to $allTablesData[$productName] that sums up all the values in 'endOfMonth' columns
                $totalRevenueRow = $rowTemplate;
                $totalRevenueRow['Customer Email'] = 'Total';
                $totalRevenueRow = array_merge(
                    $totalRevenueRow,
                    array_reduce(
                        $allTablesData[$productName],
                        function ($carry, $item) {
                            foreach ($item as $key => $value) {
                                if ($key === 'Customer Email' || $key === 'Product Name') {
                                    continue;
                                }
                                // YOM: somewhat hacky, definitely could use a refactor
                                $value = str_replace('$', '', $value);
                                $previousValue = $carry[$key] ?? 0;
                                $previousValue = str_replace('$', '', $previousValue);
                                $carry[$key] = '$' . bcadd($previousValue, $value, 2);
                            }

                            return $carry;
                        },
                        []
                    )
                );

                $allTablesData[$productName][] = $totalRevenueRow;
            }

            // generate table headers, including those with end of month dates
            $endOfMonthDates = [];
            $currentMonth = Carbon::now();
            for ($i = 0; $i < 12; $i++) {
                $endOfMonthDates[] = $currentMonth->endOfMonth()->format('Y-m-d');
                $currentMonth->addMonthNoOverflow();
            }
            $tableHeaders = array_merge(
                ['Customer Email', 'Product Name'],
                $endOfMonthDates,
                ['Life Time Value']
            );

            foreach ($allTablesData as $tableData) {
                $this->table(
                    $tableHeaders,
                    $tableData
                );
                // adds a blank line between tables
                $this->info("\r\n");
            }

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
