<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Vessel;
use App\Models\CompanyAddress;

class SplitCompanyTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'split:company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Split the companies table to companies and plans.';

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
        // $addresses = CompanyAddress::all();

        // $i = 1;
        // foreach($addresses as $address)
        // {
        //     $plan_id = Plan::where('company_id', $address->company_id)->first()->id;
        //     $address->plan_id = $plan_id;
        //     if($address->save()) {
        //         echo ('added: ' . $i);
        //         echo PHP_EOL;
        //         $i++;
        //     }
        // }

        $companies = Company::all();

        $i = 1;
        foreach($companies as $company)
        {
            $plan = new Plan();
            $plan->company_id = $company->id;
            $plan->plan_number = $company->plan_number;
            $plan->plan_holder_name = $company->name;
            $plan->active = $company->active;
            $plan->qi_id = $company->qi_id;
            $plan->plan_preparer_id = $company->plan_preparer_id;
            $existVessel = Vessel::where('company_id', $company->id)->first();
            $plan->deleted = !$existVessel && !$company->plan_number ? 1 : 0;
            $plan->save();
            if($existVessel) {
                Vessel::where('company_id', $company->id)->update([
                    'plan_id' => $plan->id,
                ]);
            }

            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
