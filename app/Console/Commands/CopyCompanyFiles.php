<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use Illuminate\Support\Facades\Storage;

class CopyCompanyFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copy:company-documents';

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
        $plans = Plan::where('id', '<', 2098)->get();
        $i = 0;
        foreach($plans as $plan)
        {
            $companyDirectory = 'files/Documents/' . $plan->company_id . '/';
            $directories = Storage::disk('gcs')->directories('files/Documents/' . $plan->company_id . '/');

            foreach($directories as $directory)
            {
                $filesInFolder = Storage::disk('gcs')->files($companyDirectory . pathinfo($directory)['basename']);
                $copyingRoute = '';
                switch(pathinfo($directory)['basename']) {
                    case 'schedule-a-non-tank' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/schedule-a-non-tank' . '/';
                    break;
                    case 'schedule-a-tanker' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/schedule-a-tanker' . '/';
                    break;
                    case 'schedule-a-combined' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/schedule-a-combined' . '/';
                    break;
                    case 'multiple-vessels-pre-fire-plan-certification' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/multiple-vessels-pre-fire-plan-certification' . '/';
                    break;
                    case 'single-vessel-pre-fire-plan-certification' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/single-vessel-pre-fire-plan-certification' . '/';
                    break;
                    case 'smff-coverage-certification' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/smff-coverage-certification' . '/';
                    break;
                    case 'tank-smff-annex' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/tank-smff-annex' . '/';
                    break;
                    case 'nt-smff-annex' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/nt-smff-annex' . '/';
                    break;
                    case 'combined-smff-annex' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/combined-smff-annex' . '/';
                    break;
                    case 'aa-vessel-specific' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/aa-vessel-specific' . '/';
                    break;
                    case 'shipboard-spill-mitigation-procedures' : 
                        $copyingRoute = 'files/plans/' . $plan->id . '/shipboard-spill-mitigation-procedures' . '/';
                    break;
                }

                if($copyingRoute) {
                    foreach($filesInFolder as $path)
                    {
                        Storage::disk('gcs')->copy($path, $copyingRoute . pathinfo($path)['basename']); 
                    }
                }
            
            }
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
