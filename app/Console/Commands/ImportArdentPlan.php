<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Cdt_Ardent\Company as ArdentCompany;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;
use App\Models\Cdt_Ardent\QiServedCompany as ArdentQiServedCompany;

class ImportArdentPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-plans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import plans of cdt_ardent companies table data to cdt plans one.';

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
        
        $allPlans = ArdentQiServedCompany::whereNotNull('planno')->get();
        $i = 1;
        foreach($allPlans as $plan)
        {
            $planInfo = ArdentCompany::where('id', $plan->qi_served_company_id)->first();

            $cdtPlan = new Plan();
            $companyId = Company::where('old_company_id', $plan->qi_served_company_id)->first()->id;
            $cdtPlan->company_id = $companyId;
            $cdtPlan->plan_number = $plan->planno;
            $cdtPlan->plan_holder_name = $planInfo->name;
            $cdtPlan->active = $planInfo->deactivated ? 3 : 2;
            $cdtPlan->deleted_at = $planInfo->deletedAt;
            $cdtPlan->save();

            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
