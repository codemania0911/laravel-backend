<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vessel;
use App\Models\DrillsDocumentation;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;
use App\Models\Cdt_Ardent\DrillsDocumentation as ArdentDrillsDocumentation;

class ImportArdentDrillsDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-drills-documentation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Cdt Ardent drills_documentation table to cdt new drills_documentation table.';

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
        $allDatas = ArdentDrillsDocumentation::all();
        $i = 1;
        foreach($allDatas as $data)
        {
            $drill = new DrillsDocumentation();
            $vesselId = Vessel::where('old_vessel_id', $data->object_id)->first()->id;
            $drill->vessel_id = $vesselId;
            $drill->date = $data->date;
            $drill->check_type = $data->check_type;
            $drill->contact_method = $data->contact_method;
            $drill->contact_method_other = $data->contact_method_other;
            $drill->caller_name = $data->caller_name;
            $drill->ardent_contact_name = $data->ardent_contact_name;
            $drill->local_vessel_name = $data->local_object_name;
            $drill->local_imo = $data->local_imo;
            $drill->local_vessel_mail = $data->local_object_mail;
            $drill->local_vessel_phone = $data->local_object_phone;
            $drill->cargo_type = $data->cargo_type;
            $drill->next_port_of_call = $data->next_port_of_call;
            $drill->vessel_review_of_vrp = $data->objective_review_of_vrp;
            $drill->vessel_review_of_smff = $data->objective_review_of_smff;
            $drill->vessel_review_of_communication_schedule = $data->objective_review_of_communication_schedule;
            $drill->vessel_review_of_confirmation = $data->objective_review_of_confirmation;
            $drill->vessel_identification_of_salvage = $data->objective_identification_of_salvage;
            $data->local_object_class_society_id ? $localVesselClassSociety = ArdentEntry::where('id', $data->local_object_class_society_id)->first()->entry : $localVesselClassSociety = NULL;
            $drill->local_vessel_class_society = $localVesselClassSociety;
            $drill->draft = $data->draft;
            $drill->local_vessel_secondary_mail = $data->local_object_secondary_mail;
            $drill->local_vessel_secondary_phone = $data->local_object_secondary_phone;
            $data->drill_screnario_entry_id ? $drillScrenario = ArdentEntry::where('id', $data->drill_screnario_entry_id)->first()->entry : $drillScrenario = NULL;
            $drill->drill_screnario = $drillScrenario;
            $drill->local_classification_society_note = $data->local_classification_society_note;

            if($drill->save()) {
                echo ('added: ' . $i);
                echo PHP_EOL;
                $i++;
            }
        }
    }
}
