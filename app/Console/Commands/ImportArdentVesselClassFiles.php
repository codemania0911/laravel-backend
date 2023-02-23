<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\VesselClass;

class ImportArdentVesselClassFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-vesselClassFiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Goolge Storage Ardent Vessel Class file to CDT Storage';

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
        $vesselClasses = VesselClass::get();

        $i = 1;
        foreach($vesselClasses as $vesselClass)
        {
            $vesselClassDirectory = 'vessel_classes/' . $vesselClass->old_vessel_class_id . '/';
            $directories = Storage::disk('gcs')->directories('vessel_classes/' . $vesselClass->old_vessel_class_id . '/');
            foreach($directories as $directory)
            {
                $filesInFolder = Storage::disk('gcs')->files($vesselClassDirectory . pathinfo($directory)['basename']);

                $copyingRoute = '';
                switch(pathinfo($directory)['basename']) {
                    case 'capacity plan' : 
                    case 'general arrangement' : 
                        $copyingRoute = 'files/vessel_classes/' . $vesselClass->id . '/' . 'drawings/';
                    break;
                    case 'drills and exercises' : 
                        $copyingRoute = 'files/vessel_classes/' . $vesselClass->id . '/' . 'drills-and-exercises/';
                    break;
                    case 'solas fire plan' : 
                    case 'solas firefighting training manual' : 
                        $copyingRoute = 'files/vessel_classes/' . $vesselClass->id . '/' . 'prefire_plans/';
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
