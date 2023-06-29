<?php

namespace macropage\laravel_daparto\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Daparto
 * @package macropage\laravel_daparto
 * @method static setCustomerConfig(string $customer)
 * @method static getDistinctShippingDescr(array $orders)
 * @method static getCustomerConfig()
 * @method static setDone(string $file)
 * @method static getSingleXMLOrder(string $orderId, bool $useCache = false)
 * @method static uploadShippingData(string $order_number, string $carrier, string $shipping_number)
 * @method static getXMLOrders(bool $useCache = false)
 */
class Daparto extends Facade {

    public static function getFacadeAccessor(): string {
        return 'Daparto';
    }
}
