# Import Orders from daparto.de

## Installation

You can install the package via composer:

```bash
composer require macropage/laravel-daparto
```

Publish config & migration using `php artisan vendor:publish --provider="macropage\laravel_daparto\DapartoServiveProvider"`  

Update your config `config/daparto.php`
```php
<?php

return [
    'accounts' => [
        'CUSTOMER1' => [
            'orders' => [
                'ftp' => [
                    'driver'   => 'ftp',
                    'host'     => 'ftp.daparto.de',
                    'username' => 'xxxxxxx',
                    'password' => 'xxxxxxx',
                ]
            ]
        ]
    ]

];
```
`CUSTOMER1` is just a placeholder, choose any name and as many you want.  
Create a folder named "done" in your ftp-home.

## Requirements
[A Cache-Provider](https://laravel.com/docs/7.x/cache#cache-tags) that supports "tagging".

## Facade
With the Facade `Daparto` you can call these methods:

- Daparto::setCustomerConfig('CUSTOMER1')
- Daparto::getXMLOrders (fetch orders via ftp or from cache)
- Daparto::getSingleXMLOrder($orderId) `$OrderFileName = 'ORDER_' . $orderId . '.xml';`
- Daparto::getXMLOrdersCached (same like getXMLOrders, but fetch data from cache)
- Daparto::getDistinctShippingDescr (for debugging: unique list of shipping-description within all orders)
- Daparto::getDistinctPaymentDescr (for debugging: unique list of payment-description within all orders)

**NOTICE:** using "getXMLOrders" without cache, flushes the whole cache for your CUSTOMER1  
in case you want to flush the cache manually: `Cache::tags('daparto.CUSTOMER1')->flush();` 

## Usage: Artisan Commands
- daparto:list-orders {account_name} {orderid?} {--cache}
- daparto:set-done {account_name} {orderid}

"list-orders" prints all orders as php-array  
"set-done" moves the xml-order-file into the folder named "done".

## Usage: in your code
```php
<?php
Daparto::setCustomerConfig($this->argument('customer'));
if ($this->argument('orderid')) {
    $singleOrder = Daparto::getSingleXMLOrder($this->argument('orderid'), $this->option('cache'));
} else {
    $OrderArrays = Daparto::getXMLOrders($this->option('cache'));
}
```

## Contributing

Help is appreciated :-)

## You need help?
_yes, you can hire me!_  
    
[![xing](https://i.imgur.com/V3RuEM7.png)](https://www.xing.com/profile/Michael_Bladowski/cv)
[![linkedin](https://i.imgur.com/UNH7YtM.png)](https://www.linkedin.com/in/macropage/)
[![twitter](https://i.imgur.com/iSv2xRb.png)](https://twitter.com/michabbb)

## Credits
- [Michael Bladowski](https://github.com/michabbb)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
