<?php

namespace macropage\laravel_daparto\Console;

use Illuminate\Console\Command;
use Daparto;

class DapartoCommandListOrders extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daparto:list-orders {account_name} {orderid?} {--cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all or individual Orders from Daparto';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        Daparto::setCustomerConfig($this->argument('account_name'));
        if ($this->argument('orderid')) {
            $OrderXMLFiles = Daparto::getSingleXMLOrder($this->argument('orderid'),$this->option('cache'));
        } else {
            $OrderXMLFiles = Daparto::getXMLOrders($this->option('cache'));
        }
        /** @noinspection ForgottenDebugOutputInspection */
        dump($OrderXMLFiles);
    }
}
