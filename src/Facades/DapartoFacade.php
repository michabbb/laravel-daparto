<?php

namespace macropage\laravel_daparto\Facades;

use Illuminate\Support\Facades\Facade;
use macropage\laravel_daparto\Daparto;

/**
 * @method static void setCustomerConfig(string $customer)
 * @method static array getDistinctShippingDescr(array $orders)
 * @method static array getCustomerConfig()
 * @method static bool setDone(string $file)
 * @method static array getSingleXMLOrder(string $orderId, bool $useCache = false)
 * @method static bool|string uploadShippingData(string $order_number, string $carrier, string $shipping_number)
 * @method static array getXMLOrders(bool $useCache = false)
 * @see Daparto
 */
class DapartoFacade extends Facade {

    protected static function getFacadeAccessor(): string {
        return 'dapartoclass';
    }
}
