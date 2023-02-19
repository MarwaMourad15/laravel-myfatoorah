# MyFatoorah Laravel

## Description
This is the MyFatoorah Payment Gateway Laravel package. 
MyFatoorah Laravel is based on [myfatoorah](https://packagist.org/packages/myfatoorah/laravel-package) composer package. 

## Main Features

* Create MyFatoorah invoices.
* Check the MyFatoorah payment status for invoice/payment.

## Installation
1. Install the package via [MarwaMourad15/laravel-myfatoorah](https://github.com/MarwaMourad15/laravel-myfatoorah) composer.

```bash
composer require MarwaMourad15/laravel-myfatoorah
```

2. Publish the **MyFatoorah** provider using the following CLI command.

```bash
php artisan vendor:publish --provider="MarwaMourad15\LaravelMyfatoorah\MyFatoorahServiceProvider" --tag="myfatoorah"
```

3. To test the payment cycle, type the below URL onto your browser. Replace only the `{example.com}` with your site domain.

```bash
https://{example.com}/myfatoorah
```

4. Customize the **app/Http/Controllers/MyFatoorahController.php** file as per your site needs.

<hr>

## Merchant Configurations

Edit the **config/myfatoorah.php** file with your correct vendor data.

**Demo configuration**
1. You can use the test API token key mentioned [here](https://myfatoorah.readme.io/docs/test-token).
2. Make sure the test mode is true.
3. You can use one of [the test cards](https://myfatoorah.readme.io/docs/test-cards).

**Live Configuration**
1. You can use the live API token key mentioned [here](https://myfatoorah.readme.io/docs/live-token).
2. Make sure the test mode is false.
3. Make sure to set the country ISO code as mentioned in [this link](https://myfatoorah.readme.io/docs/iso-lookups).
