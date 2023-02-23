<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vessel;
use App\Models\VesselClass;
use App\Models\Cdt_Ardent\Vessel as ArdentVessel;
use App\Models\Cdt_Ardent\VesselClass as ArdentVesselClass;

class ImportArdentVesselClassId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-vesselclassid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Ardent Vessel table vessel_class_id with updated vessel_class_id';

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
        $vessels = ArdentVessel::where('vessel_class_id', '<>', NULL)->get();
        $i = 1;
        foreach($vessels as $vessel)
        {
            $newVesselClassId = VesselClass::where('old_vessel_class_id', $vessel->vessel_class_id)->first()->id;
            $vesselClass = Vessel::where('old_vessel_id', $vessel->id)->update([
                'vessel_class_id' => $newVesselClassId
            ]);

            if($vesselClass) {
                echo ('added: ' . $i);
                echo PHP_EOL;
                $i++;
            }
        }
    }
}
