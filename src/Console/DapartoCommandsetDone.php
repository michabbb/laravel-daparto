<?php

namespace macropage\laravel_daparto\Console;

use Illuminate\Console\Command;
use Daparto;

class DapartoCommandsetDone extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daparto:set-done {account_name} {orderid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move order to "done" folder';

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
        Daparto::setDone($this->argument('orderid')); // throws exception if something goes wrong
        $this->info('DONE');
        return 0;
    }
}
