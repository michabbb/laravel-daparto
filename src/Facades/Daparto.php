<?php

namespace macropage\laravel_daparto\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Daparto
 * @package macropage\laravel_daparto
 * @method static void setCustomerConfig(string $customer)
 * @method static array getDistinctShippingDescr(array $orders)
 * @method static array getCustomerConfig()
 * @method static bool  setDone(string $file)
 * @method static array getSingleXMLOrder(string $orderId, bool $useCache = false)
 * @method static bool|string uploadShippingData(string $order_number, string $carrier, string $shipping_number)
 * @method static array getXMLOrders(bool $useCache = false)
 */
class Daparto extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return daparto::class;
    }
}
