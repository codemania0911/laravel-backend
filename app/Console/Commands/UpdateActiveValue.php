<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vessel;
use App\Models\Company;
use App\Models\Plan;
ini_set('memory_limit', '-1');

class UpdateActiveValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:active-field';

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
        ini_set('memory_limit', '-1');
        $vessels = Vessel::all();
        $i = 1;
        foreach($vessels as $vessel)
        {
            switch($vessel->active_field_id) {
                case 0 : 
                    $vessel->active_field_id = 1;
                break;
                case 1 : 
                    $vessel->active_field_id = 2;
                break;
                case 2 : 
                    $vessel->active_field_id = 3;
                break;
                case 3 : 
                    $vessel->active_field_id = 4;
                break;
            }
            $vessel->save();
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }

        $companies = Company::all();
        $i = 1;
        foreach($companies as $company)
        {
            switch($company->active_field_id) {
                case 0 : 
                    $company->active_field_id = 1;
                break;
                case 1 : 
                    $company->active_field_id = 2;
                break;
                case 2 : 
                    $company->active_field_id = 3;
                break;
                case 3 : 
                    $company->active_field_id = 4;
                break;
            }
            $company->save();
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }

        $plans = Plan::all();
        $i = 1;
        foreach($plans as $plan)
        {
            switch($plan->active_field_id) {
                case 0 : 
                    $plan->active_field_id = 1;
                break;
                case 1 : 
                    $plan->active_field_id = 2;
                break;
                case 2 : 
                    $plan->active_field_id = 3;
                break;
                case 3 : 
                    $plan->active_field_id = 4;
                break;
            }
            $plan->save();
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
