<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\VesselClass;
use App\Models\Vessel;
use App\Models\Cdt_Ardent\VesselClass as ArdentVesselClass;

class ImportArdentVesselClass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-vesselclass';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cdt_ardent vessel_class table data to cdt new vessel_class one.';

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
        $vesselClasses = ArdentVesselClass::all();
        $i = 1;
        foreach($vesselClasses as $vesselClass)
        {
            $companyId = Company::where('old_company_id', $vesselClass->company_id)->first()->id;
            $cdtVesselClass = new VesselClass();
            $cdtVesselClass->name = $vesselClass->name;
            $cdtVesselClass->company_id = $companyId;
            $cdtVesselClass->old_vessel_class_id = $vesselClass->id;

            if($cdtVesselClass->save()) {
                echo ('added: ' . $i);
                echo PHP_EOL;
                $i++;
            }
        }
    }
}
