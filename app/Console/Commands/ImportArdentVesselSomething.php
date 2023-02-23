<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cdt_Ardent\Vessel as ArdentVessel;
use App\Models\Vessel;

class ImportArdentVesselSomething extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:vessel';

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
        $vessels = ArdentVessel::whereNotNull('deletedAt')->get();
        $i = 1;
        foreach($vessels as $vessel)
        {
            $cdtVessel = Vessel::where('old_vessel_id', $vessel->id)->first();
            $cdtVessel->active = 3;
            $cdtVessel->company_id = 352;
            $cdtVessel->plan_id = NULL;

            $cdtVessel->save();
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
