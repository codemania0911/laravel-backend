<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Models\Plan;

class ImportArdentCompanyFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-company-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Goolge Storage Ardent Company file to CDT Storage';

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
        $companies = Company::whereNotNull('old_company_id')->get();

        $i = 1;
        foreach($companies as $company)
        {
            $companyDirectory = 'companies/' . $company->old_company_id . '/';
            $directories = Storage::disk('gcs')->directories('companies/' . $company->old_company_id . '/');
            foreach ($directories as $directory) {
                $filesInFolder = Storage::disk('gcs')->files($companyDirectory . pathinfo($directory)['basename']);
                switch(pathinfo($directory)['basename']) {
                    case '[certificates]' : 
                        $plans = Plan::where('company_id', $company->id)->get();
                        foreach($plans as $plan)
                        {
                            $copyingRoute = 'files/plans/' . $plan->id . '/smff-coverage-certification' . '/';
                            foreach($filesInFolder as $path)
                            {
                                Storage::disk('gcs')->copy($path, $copyingRoute . pathinfo($path)['basename']);
                            }
                        }
                    break;
                    case 'drills and exercises' : 
                        $plans = Plan::where('company_id', $company->id)->get();
                        foreach($plans as $plan)
                        {
                            $copyingRoute = 'files/plans/' . $plan->id . '/drills-and-exercises' . '/';
                            foreach($filesInFolder as $path)
                            {
                                Storage::disk('gcs')->copy($path, $copyingRoute . pathinfo($path)['basename']);
                            }
                        }
                    break;
                    case 'funding agreement' : 
                        $copyingRoute = 'files/Documents/' . $company->id . '/' . 'contracts' . '/';
                        foreach($filesInFolder as $path)
                        {
                            Storage::disk('gcs')->copy($path, $copyingRoute . pathinfo($path)['basename']);
                        }
                    break;
                }
            }

            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
