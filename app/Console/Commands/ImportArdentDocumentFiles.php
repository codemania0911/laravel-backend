<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Vessel;
use App\Models\Cdt_Ardent\Document;

class ImportArdentDocumentFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-document-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Goolge Storage Ardent Document file to CDT Storage';

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
        $documents = Document::whereNotNull('object_id')->where('document_type_entry_id', '<>', 4)->get();
        
        $i = 1;
        foreach($documents as $document)
        {
            // $documentDirectory = 'documents/' . $document->id . '/';
            $documentDirectory = 'documents/10508/';
            // $vesselId = Vessel::where('old_vessel_id', $document->object_id)->first()->id;
            $vesselId = Vessel::where('old_vessel_id', 10704)->first()->id;
            $filesInFolder = Storage::disk('gcs')->files($documentDirectory);
            $copyingRoute = '';
            switch($document->document_type_entry_id) {
                case 3 : 
                    $copyingRoute = 'files/new/prefire_plans/' . $vesselId . '/';
                break;
                case 2 : 
                    $copyingRoute = 'files/new/prefire_plan_certification/' . $vesselId . '/';
                break;
            }

            if($copyingRoute) {
                foreach($filesInFolder as $path)
                {
                    $aa = pathinfo($path)['basename'];
                    if (!(Storage::disk('gcs')->exists($copyingRoute . pathinfo($path)['basename']))) {
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
