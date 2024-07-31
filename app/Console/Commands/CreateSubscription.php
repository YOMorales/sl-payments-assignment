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

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
