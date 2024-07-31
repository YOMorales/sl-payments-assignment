<?php

namespace App\Console\Commands;

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

            $price = $stripeClient->prices->search([
                'query' => "lookup_key:'monthly_crossclip_basic'",
            ]);

            $customer = $stripeClient->customers->create([
                'email' => 'yamir@example.com',
                'test_clock' => config('stripe.test_clock'),
                'payment_method' => 'pm_card_visa',
                'invoice_settings' => ['default_payment_method' => 'pm_card_visa'],
            ]);

            $subscription = $stripeClient->subscriptions->create([
                'customer' => $customer->id,
                'items' => [['price' => $price->data[0]->id]],
                'currency' => 'gbp',
                'trial_period_days' => 30,
                // YOM: using `discounts` because the top-level `coupon` param is deprecated
                'discounts' => [['coupon' => $coupon->id]],
            ]);

            dump($subscription->id);

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
