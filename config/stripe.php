<?php
/*
YOM: I could have stored this on services.php, but I wanted to show how to create a new config file.
Also, I created a custom config file for new env variables as that is a usual Laravel practice.
*/
return [
    'public_key' => env('STRIPE_PUBLIC_KEY'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'test_clock' => env('STRIPE_TEST_CLOCK'),
];
