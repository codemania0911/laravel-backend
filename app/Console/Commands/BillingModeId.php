<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillingInformation;

class BillingModeId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:billing-mode-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $modes = BillingInformation::all();

        $i = 1;
        foreach($modes as $mode)
        {
            if($mode->billing_mode == "client") {
                $mode->billing_mode_id = 1;
            } else if($mode->billing_mode == "group") {
                $mode->billing_mode_id = 3;
            } else if($mode->billing_mode == "vessel") {
                $mode->billing_mode_id = 2;
            }

            $mode->save();
            echo ('updated: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
