<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Vessel;

class ImportArdentVesselFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-vessel-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Goolge Storage Ardent Vessel file to CDT Storage';

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
        $vessels = Vessel::whereNotNull('old_vessel_id')->get();

        $i = 1;
        foreach($vessels as $vessel)
        {
            $vesselDirectory = 'objects/' . $vessel->old_vessel_id . '/';
            $directories = Storage::disk('gcs')->directories('objects/' . $vessel->old_vessel_id . '/');
            foreach($directories as $directory)
            {
                $filesInFolder = Storage::disk('gcs')->files($vesselDirectory . pathinfo($directory)['basename']);
                $copyingRoute = '';
                switch(pathinfo($directory)['basename']) {
                    case 'capacity plan' : 
                    case 'general arrangement' : 
                        $copyingRoute = 'files/new/drawings/' . $vessel->id . '/';
                    break;
                    case 'drills and exercises' : 
                        $copyingRoute = 'files/new/drills-and-exercises/' . $vessel->id . '/';
                    break;
                    case 'solas fire plan' : 
                    case 'solas firefighting training manual' : 
                        $copyingRoute = 'files/new/prefire_plans/' . $vessel->id . '/';
                    break;
                    case 'stability booklet (optional)' : 
                        // $copyingRoute = 'files/new/drills-and-exercises/' . $vessel->id . '/';
                        $copyingRoute = 'files/new/stability-booklet/' . $vessel->id . '/';
                    break;
                    case 'vessel details' : 
                        $copyingRoute = 'files/Documents/' . $vessel->company_id . '/' . 'smff-verification-statement' . '/';
                    break;
                }

                if($copyingRoute) {
                    foreach($filesInFolder as $path)
                    {
                        if (!(Storage::disk('gcs')->exists($copyingRoute . pathinfo($path)['basename']))) {
                            Storage::disk('gcs')->copy($path, $copyingRoute . pathinfo($path)['basename']);   
                        }
                    }
                }
            }
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
