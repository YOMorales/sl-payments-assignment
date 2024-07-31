<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\StripeClient;
use Throwable;

class CreateStripeTestClock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sl:create-stripe-test-clock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test clock for Stripe';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /*
        YOM: Test clocks can be created in other ways, such as in the Stripe Dashboard. But I will be using commands to do
        all the work, mostly to showcase my expertise with Laravel and other libraries, plus this can be rerun as many
        times as needed (like when removing all test clocks and recreating them). Also, I like to use commands for
        these sort of tasks because they offer other possibilities such as scheduling them, queueing them, and more.
        Finally, I want to make use of custom helpers functions that I saw, like writeNewEnvironmentFileWithClock().
        */
        try {
            $stripeClient = new StripeClient(config('stripe.secret_key'));

            $testClock = $stripeClient->testHelpers->testClocks->create([
                'frozen_time' => Carbon::now()->startOfDay(1)->timestamp,
                'name' => 'StreamLabs Test Clock',
            ]);

            /*
            YOM: test clock can be cached, stored to a db, etc., but I will use a custom helper function that I saw
            to write it to .env file, as this will be needed by the fixtures.
            */
            writeNewEnvironmentFileWithClock($testClock->id);

            return Command::SUCCESS;
        } catch (Throwable $th) {
            // YOM: this will log to file as default behavior, but in a real application, this could log to Sentry or something like that
            report($th);
            return Command::FAILURE;
        }
    }
}
