<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Plan;

class UpdatePlanPreparer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:plan-preparer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'We won\'t use plan_preparer table anymore and will use company id like qi.';

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
        $companies = Company::where('vendor_type', 7)
                        ->update([
                            'vendor_type' => 3
                        ]);

        $plans = Plan::whereNotNull('plan_preparer_id')->get();
        $i = 1;
        foreach($plans as $plan)
        {
            switch($plan->plan_preparer_id) {
                case 1 : 
                    $plan->plan_preparer_id = 4417;
                break;
                case 2 : 
                    $plan->plan_preparer_id = 4418;
                break;
                case 3 : 
                    $plan->plan_preparer_id = 4419;
                break;
                case 4 : 
                    $plan->plan_preparer_id = 4420;
                break;
                case 5 : 
                    $plan->plan_preparer_id = 6082;
                break;
                case 6 : 
                    $plan->plan_preparer_id = 4424;
                break;
            }

            $plan->save();
            echo ('updated: ' . $i);
            echo PHP_EOL;
            $i++;
        }

    }
}
