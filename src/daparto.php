<?php

namespace macropage\laravel_daparto;

use Arr;
use Cache;
use Exception;
use Gaarf\XmlToPhp\Convertor;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use RuntimeException;
use Storage;
use XMLReader;

class daparto {

    private array            $customerConfig;
    private string           $customer;
    private XMLReader        $xmlReader;


    public function __construct() {
        $this->xmlReader = new XMLReader();
    }

    public function setCustomerConfig(string $customer): void {
        if (!config()->has('daparto.accounts.' . $customer)) {
            throw new RuntimeException('missing customer "' . $customer . '" in your darpato config');
        }
        $this->customer       = $customer;
        $this->customerConfig = config('daparto.accounts.' . $customer);
        config(['filesystems.disks.ftp.' . $customer . '.orders' => $this->customerConfig['orders']['ftp']]);
    }

    public function getDistinctShippingDescr($Orders): array {
        $existingShippings = [];
        foreach ($Orders as $order) {
            $shipping                                                        = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), fn($value, $key) => ($value['LINE_ITEM_ID'] === 'shipping'));
            $existingShippings[$shipping['ARTICLE_ID']['DESCRIPTION_SHORT']] = 1;
        }

        return $existingShippings;
    }

    public function getDistinctPaymentDescr($Orders): array {
        $existingPayments = [];
        foreach ($Orders as $order) {
            $shipping                                                       = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), fn($value, $key) => ($value['LINE_ITEM_ID'] === 'payment'));
            $existingPayments[$shipping['ARTICLE_ID']['DESCRIPTION_SHORT']] = 1;
        }

        return $existingPayments;
    }

    /**
     * @return array
     */
    public function getCustomerConfig(): array {
        return $this->customerConfig;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getXMLOrdersCached(): array {
        $onlyFiles = cache()->tags(['daparto.' . $this->customer])->get('daparto.orders.' . $this->customer);
        $xmlOrders = [];
        foreach ($onlyFiles as $file) {
            $xmlOrders[$file['path']] = cache()->tags(['daparto.' . $this->customer])->get('daparto.orders.' . $this->customer . '.' . $file['path']);
        }

        return $xmlOrders;
    }

    public function setDone($orderIdXML) {
        return Storage::disk('ftp.' . $this->customer . '.orders')->move($orderIdXML, 'done/'.$orderIdXML);
    }

    /**
     * @param      $orderId
     *
     * @param bool $useCache
     *
     * @return array|bool|mixed[]
     * @throws FileNotFoundException
     */
    public
    function getSingleXMLOrder($orderId, $useCache = false) {
        $OrderFileName = 'ORDER_' . $orderId . '.xml';

        if ($useCache) {
            if (!Cache::tags(['daparto.' . $this->customer])->has('daparto.orders.' . $this->customer . '.' . $OrderFileName)) {
                return ['state' => false, 'msg' => 'order not found in cache'];
            }

            return ['state' => true, 'order' => Cache::tags(['daparto.' . $this->customer])->get('daparto.orders.' . $this->customer . '.' . $OrderFileName)];
        }

        if (!Storage::disk('ftp.' . $this->customer . '.orders')->exists($OrderFileName)) {
            return ['state' => false, 'msg' => 'file not found on server: ' . $OrderFileName];
        }
        $file_contents = Storage::disk('ftp.' . $this->customer . '.orders')->get($OrderFileName);

        $OrderArray = $this->xml2array($file_contents, $OrderFileName);
        Cache::tags(['daparto.' . $this->customer])->put('daparto.orders.' . $this->customer . '.' . $OrderFileName, $OrderArray);

        return ['state' => true, 'order' => $OrderArray];
    }

    /**
     * @param bool $useCache
     *
     * @return array
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function getXMLOrders(bool $useCache = false): array {

        if ($useCache) {
            return $this->getXMLOrdersCached();
        }

        Cache::tags('daparto.' . $this->customer)->flush();

        /**
         * Get Files if not existent in cache
         */
        $remoteContents = Storage::disk('ftp.' . $this->customer . '.orders')->listContents();
        // filter only files and only take order from within this year
        $onlyFiles = array_filter($remoteContents, fn($var) => ($var['type'] === 'file'));
        // https://github.com/thephpleague/flysystem/issues/1161
        foreach ($onlyFiles as $i => $file) {
            $timestamp = Storage::disk('ftp.' . $this->customer . '.orders')->getTimestamp($file['path']);
            if ($timestamp) {
                $onlyFiles[$i]['timestamp'] = $timestamp;
            } else {
                throw new RuntimeException('unable to get timestamp of: ' . $file['path']);
            }
        }
        usort($onlyFiles, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        Cache::tags(['daparto.' . $this->customer])->put('daparto.orders.' . $this->customer, $onlyFiles);

        /**
         * Parse Each XML to Array
         */
        foreach ($onlyFiles as $file) {
            $file_contents = Storage::disk('ftp.' . $this->customer . '.orders')->get($file['path']);
            $OrderArray    = $this->xml2array($file_contents, $file['path']);
            Cache::tags(['daparto.' . $this->customer])->put('daparto.orders.' . $this->customer . '.' . $file['path'], $OrderArray);
        }

        $xmlOrders = [];

        foreach ($onlyFiles as $file) {
            $xmlOrders[$file['path']] = cache()->tags(['daparto.' . $this->customer])->get('daparto.orders.' . $this->customer . '.' . $file['path']);
        }

        return $xmlOrders;

    }

    private function checkXMLisValid(string $string) {
        $this->xmlReader->XML($string);
        $this->xmlReader->setParserProperty(XMLReader::VALIDATE, true);

        return $this->xmlReader->isValid();
    }

    /**
     * @param $xml
     * @param $filename
     *
     * @return array
     */
    private function xml2array($xml, $filename): array {
        if (!$this->checkXMLisValid($xml)) {
            throw new RuntimeException('xml invalid: ' . $filename);
        }
        $OrderArray = Convertor::covertToArray($xml);
        if (!is_array($OrderArray)) {
            throw new RuntimeException('unable to convert to array: ' . $filename);
        }
        $OrderArray['source'] = $filename;

        return $OrderArray;
    }

}
