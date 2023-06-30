<?php

namespace macropage\laravel_daparto;

use Arr;
use Cache;
use Exception;
use Gaarf\XmlToPhp\Convertor;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JetBrains\PhpStorm\Pure;
use League\Csv\CannotInsertRecord;
use League\Csv\EncloseField;
use League\Csv\Exception as CsVException;
use League\Csv\Writer;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Storage;
use XMLReader;

class Daparto {

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
        config(['filesystems.disks.ftp.' . $customer . '.shippinginfo' => $this->customerConfig['shippinginfo']['ftp']]);
    }

    public function getDistinctShippingDescr($Orders): array {
        $existingShippings = [];
        foreach ($Orders as $order) {
            $shipping                                                        = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), static fn($value, $key) => ($value['LINE_ITEM_ID'] === 'shipping'));
            $existingShippings[$shipping['ARTICLE_ID']['DESCRIPTION_SHORT']] = 1;
        }

        return $existingShippings;
    }

    public function getDistinctPaymentDescr($Orders): array {
        $existingPayments = [];
        foreach ($Orders as $order) {
            $shipping                                                       = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), static fn($value, $key) => ($value['LINE_ITEM_ID'] === 'payment'));
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

    public function setDone($orderIdXML): bool
    {
        return Storage::disk('ftp.' . $this->customer . '.orders')->move($orderIdXML, 'done/'.$orderIdXML);
    }

    /**
     * @throws FileNotFoundException
     */
    public function getSingleXMLOrder(string $orderId, bool $useCache = false): array {
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
     * @param $order_number
     * @param $carrier
     * @param $shipping_number
     *
     * @return bool|string
     * @throws CannotInsertRecord
     * @throws CsVException
     */
    public function uploadShippingData(string $order_number, string $carrier, string $shipping_number): bool|string
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter(';');
        EncloseField::addTo($csv, "\t\x1f");
        $csv->insertOne(['order_number', 'delivery_type', 'tracking_number']);
        $csv->insertOne([$order_number, $carrier, $shipping_number]);
        return Storage::disk('ftp.' . $this->customer . '.shippinginfo')->put($order_number.'.csv',$csv->getContent());
    }

    /**
     * @param bool $useCache
     *
     * @return array
     * @throws FileNotFoundException
     * @throws Exception
     * @throws FilesystemException
     */
    public function getXMLOrders(bool $useCache = false): array {

        if ($useCache) {
            return $this->getXMLOrdersCached();
        }

        Cache::tags('daparto.' . $this->customer)->flush();

        /**
         * Get Files if not existent in cache
         */
        $adapter = Storage::disk('ftp.' . $this->customer . '.orders')->getAdapter();

        $redo = 0;
        while ($redo<10) {
            try {
                $remoteContents = $adapter->listContents('.',false);
                $redo=10;
            } catch (FilesystemException $e) {
                $redo++;
                if ($redo>10) {
                    throw new $e;
                }

                echo 'connect failed: '.$this->customerConfig['orders']['ftp']['host'].' retry again in 10 seconds....'."\n";
                sleep(10);
            }
        }

        if (!isset($remoteContents)) {
            throw new RuntimeException('no files found on ftp server');
        }

        $remoteContents = iterator_to_array($remoteContents);

        // filter only files and only take order from within this year
        $onlyFiles = collect(array_filter($remoteContents, static fn($var) => ($var['type'] === 'file')))->map(fn(FileAttributes $item) => [
            'path' => $item->path(),
            'lastModified' => $item->lastModified()
        ])->pluck('lastModified', 'path');

        foreach ($onlyFiles as $file => $timestamp) {
            if (!$timestamp) {
                $timestamp = Storage::disk('ftp.' . $this->customer . '.orders')->lastModified($file['path']);
                if ($timestamp) {
                    $onlyFiles->put($file, $timestamp);
                } else {
                    throw new RuntimeException('unable to get timestamp of: ' . $file);
                }
            }
        }

        $onlyFiles = $onlyFiles->sort(static fn($a, $b) => $a['lastModified'] <=> $b['lastModified']);

        Cache::tags(['daparto.' . $this->customer])->put('daparto.orders.' . $this->customer, $onlyFiles);

        /**
         * Parse Each XML to Array
         */
        foreach ($onlyFiles->keys() as $file) {
            $file_contents = Storage::disk('ftp.' . $this->customer . '.orders')->get($file);
            $OrderArray    = $this->xml2array($file_contents, $file);
            Cache::tags(['daparto.' . $this->customer])->put('daparto.orders.' . $this->customer . '.' . $file, $OrderArray);
        }

        $xmlOrders = [];

        foreach ($onlyFiles->keys() as $file) {
            $xmlOrders[$file] = cache()->tags(['daparto.' . $this->customer])->get('daparto.orders.' . $this->customer . '.' . $file);
        }

        return $xmlOrders;

    }

    private function checkXMLisValid(string $string): bool
    {
        /** @noinspection StaticInvocationViaThisInspection */
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
        if (!count($OrderArray)) {
            throw new RuntimeException('unable to convert to array: ' . $filename);
        }
        $OrderArray['source'] = $filename;

        return $OrderArray;
    }

}
