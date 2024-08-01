<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://cdn.streamlabs.com/static/imgs/identity/streamlabs-logo-thumb.png" alt="Streamlabs Kevin"></a></p>

# Streamlabs Senior Payments Assignment

## Instructions

Note: Even though a docker container has been provided for this exercise, it is not necessary as everything is done by way of artisan commands (which you can run outside of the container). However, you will still need to use the Stripe CLI.

1. Copy the `.env.example` file and rename it to `.env`. Make sure you fill the following variables with *test keys* from your Stripe account (although STRIPE_PUBLIC_KEY is not truly needed in this exercise).

```
STRIPE_PUBLIC_KEY
STRIPE_SECRET_KEY
```


2. Run the following artisan command: `php artisan sl:create-stripe-test-clock`.
This command will create a test clock on your account and will overwrite the `.env` variable of `STRIPE_TEST_CLOCK` with
your new clock's id.

3. Run the Stripe fixtures by using the following Stripe CLI command: `stripe fixtures ./fixtures/seed.json`

4. Run the command: `php artisan sl:create-subscription`.
IMPORTANT: I found that Stripe can sometimes take several seconds to update the data generated in their API, so run the next two commands with some seconds in between.
This command will create a new extra subscription (for price: monthly_crossclip_basic) in addition to the ones added by the fixtures. The command will also advance the test clock made on step #2 in order to upgrade this extra subscription and to generate future test invoices for all subscriptions. So you will need to wait a while for Stripe to progressively advance the clock 12 months.

5. Finally, run `php artisan sl:get-subscriptions-report`.
This command will crunch all the test invoices data and will generate tables in the terminal with projected subscription revenue, as per the original instructions of this exercise.


## Notes:

- I have included a txt file called `subscription-report-results.txt` with the results of my exercise (Im including it as txt file and not a screenshot because the results are output in the terminal and they look garbled due to screen size).

- Why commands? For these sort of backend-heavy tasks (which do not necessarily require output to a browser), I like to use custom commands (though not for all scenarios) because they offer other possibilities such as scheduling them, queueing them, executing them directly when ssh'ing in a server, and more.

- Why separate commands? The exercise had two main goals: to generate a report table and also to create a subscription and upgrade it midway. It was not entirely clear if the two goals were dependent (i.e. whether the report table needed to include the revenue of the extra subscription created/upgraded... or if the revenue table was meant to use only the fixtures' data with a separate test clock). So I decided to create two commands where each one would handle a different goal. However, in the instructions of this README, Im stating that the command `php artisan sl:create-subscription` should be run before the `GetSubscriptionsReport` command, because `CreateSubscription` command is the one that advances the clock 12 months ahead. If it were the case that the two goals were not dependent on each other, then I would have created a separate clock in `GetSubscriptionsReport` command and would have advanced it 12 months, independently of the clock in `CreateSubscription` command.

- I added other explanations throughout the code to explain my thought process or give reasons as to why something was done. These commends are prefixed with `// YOM`

- I only used PHP and Laravel as per the original instructions, so I avoided extra stuff not asked in the exercise such as VueJS components or mysql databases, etc.

- The exercise took me roughly 5.5 hours to complete, of which some were reading API documentation, as I have never used test clocks before (and I also needed to review a few things regarding the invoices' data structure).

## Improvements for the Future:

There are several things I'm aware of that could have been implemented given more time and scope for this exercise (also, I'm following the instruction of NOT implementing unasked features):

- PHPUnit tests

- I don't like the procedural logic that I did in the custom commands. Given time, it all could be encapsulated in other classes or at least other methods. In particular, I would have liked to use a Builder design pattern to create the final object (perhaps a DTO) with the revenue table's data. This way, such Builder class could have been reused both in my custom commands and in another interface (for example, an actual controller and view that would render html revenue tables).

- As I mentioned above, another improvement could be to do a controller and a view to render the revenue data in a html table, possibly a JS datatable too.

- Test clock Factory for creating test clock objects that emit events. So, these clock objects would be wrappers for Stripe clock responses that could also implement other Laravel features like emitting events. So, a lot of the clock-related logic in `CreateSubscription` command could be moved to these new objects. Also, when the clock advances by one time interval, it can emit a hypothetical `ClockAdvanced` event that a Listener can catch and thus perform the 'subscription-updating' logic that presently is on `CreateSubscription`, line 86. This way, we separate concerns and can reuse code.

- Cache or summary table: in a real application, the final revenue values can be cached or written in a mysql table, so that they could be referenced later without crunching the data again.

- The class `ExchangeRate` could be changed to use constants for the rates, or even to be an Enum type.
