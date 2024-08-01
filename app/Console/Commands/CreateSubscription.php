<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\StripeClient;
use Throwable;

class CreateSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sl:create-subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a subscription in Stripe.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $stripeClient = new StripeClient(config('stripe.secret_key'));

            /*
            YOM: when we used the fixtures, we didnt save the coupon ids nor price ids in a database, so we
            have to fetch them from Stripe.
            Stripe API doesnt offer coupon search, so we have to fetch all coupons and filter them in PHP.
            */
            $coupon = collect($stripeClient->coupons->all())->firstWhere('name', '5 Dollar Off for 3 Months');

            $planMonthlyCrossclipBasic = $stripeClient->prices->search([
                'query' => "lookup_key:'monthly_crossclip_basic'",
            ]);

            $planMonthlyCrossclipPremium = $stripeClient->prices->search([
                'query' => "lookup_key:'monthly_crossclip_premium'",
            ]);

            $customer = $stripeClient->customers->create([
                'email' => 'yamir@example.com',
                'test_clock' => config('stripe.test_clock'),
                'payment_method' => 'pm_card_visa',
                'invoice_settings' => ['default_payment_method' => 'pm_card_visa'],
            ]);

            $subscription = $stripeClient->subscriptions->create([
                'customer' => $customer->id,
                'items' => [['price' => $planMonthlyCrossclipBasic->data[0]->id]],
                'currency' => 'gbp',
                'trial_period_days' => 30,
                // YOM: using `discounts` because the top-level `coupon` param is deprecated
                'discounts' => [['coupon' => $coupon->id]],
            ]);

            $this->info("Created subscription: {$subscription->id}");

            /*
            YOM: given sufficient time, I could refactor most of the code here to move code to other methods,
            or to even create helper classes for tasks such as advancing the clock used here. I personally don't
            like extensive methods with a lot of procedural code.
            */
            for ($months = 1; $months <= 12; $months++) {
                $oneMonthIncrement = Carbon::now()->startOfDay()->addMonthsNoOverflow($months);
                $this->info("Advancing clock to " . $oneMonthIncrement->format('Y-m-d'));

                $clock = $stripeClient->testHelpers->testClocks->advance(config('stripe.test_clock'), ['frozen_time' => $oneMonthIncrement->timestamp]);
                $clockStatus = $clock->status;

                // YOM: I know there are webhooks for this, but I will be pinging to keep this simple
                while ($clockStatus !== 'ready') {
                    $this->info('Waiting for clock to be ready...');
                    sleep(4);
                    $clockStatus = $stripeClient->testHelpers->testClocks->retrieve(config('stripe.test_clock'))->status;
                }

                if ($months === 5) {
                    $this->info("Performing mid-cycle upgrade with proration on the 15th of the 5th month...");

                    $fifteenthDay = $oneMonthIncrement->copy()->addDays(14);
                    $this->info("Advancing clock to..." . $fifteenthDay->format('Y-m-d'));

                    $clock = $stripeClient->testHelpers->testClocks->advance(config('stripe.test_clock'), ['frozen_time' => $fifteenthDay->timestamp]);
                    $clockStatus = $clock->status;

                    while ($clockStatus !== 'ready') {
                        $this->info('Waiting for clock to be ready...');
                        sleep(4);
                        $clockStatus = $stripeClient->testHelpers->testClocks->retrieve(config('stripe.test_clock'))->status;
                    }

                    $stripeClient->subscriptions->update(
                        $subscription->id,
                        [
                            'payment_behavior' => 'pending_if_incomplete',
                            'proration_behavior' => 'always_invoice',
                            'items' => [
                                [
                                    'id' => $subscription->items->data[0]->id,
                                    'price' => $planMonthlyCrossclipPremium->data[0]->id,
                                ],
                            ],
                        ]
                    );

                    $this->info("Updated subscription: {$subscription->id}");
                }
            }

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
