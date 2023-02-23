<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\Cdt_Ardent\Vessel as ArdentVessel;
use App\Models\Plan;
use App\Models\Cdt_Ardent\QiServedCompany as ArdentQiServedCompany;
use App\Models\VesselVendor;
use App\Models\Cdt_Ardent\VesselData as ArdentVesselData;
use App\Models\Cdt_Ardent\VesselPIData as ArdentVesselPIData;
use App\Models\Cdt_Ardent\VesselHMData as ArdentVesselHMData;

class ImportArdentVessels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-vessels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cdt_ardent vessels(objects) table data to cdt vessels one.';

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
        $vessels = ArdentVessel::all();
        $i = 1;
        foreach($vessels as $vessel)
        {
            $cdtVessel = new Vessel();
            $companyId = Company::where('old_company_id', $vessel->company_id)->first()->id;
            $cdtVessel->company_id = $companyId;
            $cdtVessel->imo = $vessel->imono;
            $cdtVessel->official_number = $vessel->official_number;
            // $cdtVessel->active = $vessel->active ? 2 : 3;
            // $cdtVessel->tanker = $vessel->vrptype_entry_id = 287 ? 1 : 0;
            if($vessel->vrptype_entry_id == 287) {
                $cdtVessel->tanker = 1;
            } else {
                $cdtVessel->tanker = 0;
            }
            $cdtVessel->name = $vessel->name;
            // 2 : active, 3: inactive
            $cdtVessel->active = $vessel->deactivated ? 3 : 2;
            $cdtVessel->sat_phone_primary = $vessel->phone;
            $cdtVessel->email_primary = $vessel->email;
            $cdtVessel->sat_phone_secondary = $vessel->secondary_phone;
            $cdtVessel->email_secondary = $vessel->secondary_email;
            $cdtVessel->old_vessel_id = $vessel->id;

            if($vessel->plan_id) {
                $plan = ArdentQiServedCompany::where('id', $vessel->plan_id)->first();
                if($plan->planno) {
                    $currentCompanyId = Company::where('old_company_id', $plan->qi_served_company_id)->first()->id;
                    $planId = Plan::where([['plan_number', $plan->planno], ['company_id', $currentCompanyId]])->first()->id;
                    $cdtVessel->plan_id = $planId;
                }
            }

            $vesselQIDatas = ArdentVesselData::where('object_id', $vessel->id)->get();

            // QI Company
            foreach($vesselQIDatas as $vesselQIData)
            {
                switch($vesselQIData->data_id) {
                    case 1 : 
                        $cdtVessel->wcd = $vesselQIData->value;
                    break;
                    case 3 : 
                        $cdtVessel->mmsi = $vesselQIData->value;
                    break;
                    case 5 : 
                        $cdtVessel->dead_weight = $vesselQIData->value;
                    break;
                    case 6 : 
                        $cdtVessel->construction_length_overall = $vesselQIData->value;
                    break;
                    case 7 : 
                        $cdtVessel->construction_breadth_extreme = $vesselQIData->value;
                    break;
                    case 8 : 
                        $cdtVessel->construction_draught = $vesselQIData->value;
                    break;
                    case 11 : 
                        $cdtVessel->gross_tonnage = $vesselQIData->value;
                    break;
                    case 12 : 
                        $cdtVessel->oil_tank_volume = $vesselQIData->value;
                    break;
                    case 13 : 
                        $cdtVessel->construction_built = $vesselQIData->value;
                    break;
                }
            }

            switch($vessel->shiptype_entry_id) {
                case 265 : 
                    $cdtVessel->vessel_type_id = 75;
                break;
                case 266 :
                    $cdtVessel->vessel_type_id = 146;
                break;
                case 267 :
                    $cdtVessel->vessel_type_id = 79;
                break;
                case 268 :
                    $cdtVessel->vessel_type_id = 41;
                break;
                case 269 :
                    $cdtVessel->vessel_type_id = 75;
                break;
                case 270 :
                    $cdtVessel->vessel_type_id = 331;
                break;
                case 271 :
                    $cdtVessel->vessel_type_id = 329;
                break;
                case 272 :
                    $cdtVessel->vessel_type_id = 128;
                break;
                case 273 :
                    $cdtVessel->vessel_type_id = 332;
                break;
                case 274 :
                    $cdtVessel->vessel_type_id = 50;
                break;
                case 275 :
                    $cdtVessel->vessel_type_id = 18;
                break;
                case 276 :
                    $cdtVessel->vessel_type_id = 228;
                break;
                case 277 :
                    $cdtVessel->vessel_type_id = 208;
                break;
                case 278 :
                    $cdtVessel->vessel_type_id = 222;
                break;
                case 279 :
                    $cdtVessel->vessel_type_id = 253;
                break;
                case 280 :
                    $cdtVessel->vessel_type_id = 189;
                break;
                case 281 :
                    $cdtVessel->vessel_type_id = 136;
                break;
                case 282 :
                    $cdtVessel->vessel_type_id = 194;
                break;
                case 283 :
                    $cdtVessel->vessel_type_id = 78;
                break;
                case 284 :
                    $cdtVessel->vessel_type_id = 220;
                break;
                case 285 :
                    $cdtVessel->vessel_type_id = 126;
                break;
                case 416 :
                    $cdtVessel->vessel_type_id = 84;
                break;
                case 417 :
                    $cdtVessel->vessel_type_id = 254;
                break;
                case 381 :
                    $cdtVessel->vessel_type_id = 126;
                break;
                case 382 :
                    $cdtVessel->vessel_type_id = 314;
                break;
            }

            if($cdtVessel->save()) {
                if($vessel->qi_company_id) {
                    $vesselVendor = new VesselVendor();
                    $vesselVendor->vessel_id = $cdtVessel->id;
                    switch($vessel->qi_company_id) {
                        case 763 : 
                            $vesselVendor->company_id = 4417;
                        break;
                        case 296 : 
                            $vesselVendor->company_id = 4419;
                        break;
                        case 454 : 
                            $vesselVendor->company_id = 4420;
                        break;
                        case 370 : 
                            $vesselVendor->company_id = 4418;
                        break;
                    }
                    $vesselVendor->save();
                }

                // Classificationsociety
                if($vessel->classificationsociety_id && $vessel->classificationsociety_id !== 299) {
                    $vesselClassificationSocietyVendor = new VesselVendor();
                    $vesselClassificationSocietyVendor->vessel_id = $cdtVessel->id;
                    switch($vessel->classificationsociety_id) {
                        case 288 : 
                            $vesselClassificationSocietyVendor->company_id = 3916;
                        break;
                        case 289 : 
                            $vesselClassificationSocietyVendor->company_id = 3932;
                        break;
                        case 291 : 
                            $vesselClassificationSocietyVendor->company_id = 3924;
                        break;
                        case 300 : 
                            $vesselClassificationSocietyVendor->company_id = 3961;
                        break;
                        case 314 : 
                            $vesselClassificationSocietyVendor->company_id = 3955;
                        break;
                        case 315 : 
                            $vesselClassificationSocietyVendor->company_id = 3934;
                        break;
                        case 316 : 
                            $vesselClassificationSocietyVendor->company_id = 3931;
                        break;
                        case 319 : 
                            $vesselClassificationSocietyVendor->company_id = 3951;
                        break;
                        case 380 : 
                            $vesselClassificationSocietyVendor->company_id = 3964;
                        break;
                        case 388 : 
                            $vesselClassificationSocietyVendor->company_id = 3928;
                        break;
                        case 390 : 
                            $vesselClassificationSocietyVendor->company_id = 5133;
                        break;
                        case 410 : 
                            $vesselClassificationSocietyVendor->company_id = 3963;
                        break;
                    }
                    $vesselClassificationSocietyVendor->save();
                }

                // P & I Club Company
                $vesselPIDatas = ArdentVesselPIData::where('object_id', $vessel->id)->get();

                foreach($vesselPIDatas as $vesselPIData)
                {
                    $vesselPIVendor = new VesselVendor();
                    $vesselPIVendor->vessel_id = $cdtVessel->id;
                    switch($vesselPIData->pi_id) {
                        case 292 : 
                            $vesselPIVendor->company_id = 3903;
                        break;
                        case 296 : 
                            $vesselPIVendor->company_id = 3855;
                        break;
                        case 297 : 
                            $vesselPIVendor->company_id = 5158;
                        break;
                        case 298 : 
                            $vesselPIVendor->company_id = 5159;
                        break;
                        case 301 : 
                            $vesselPIVendor->company_id = 3886;
                        break;
                        case 304 : 
                            $vesselPIVendor->company_id = 4884;
                        break;
                        case 305 : 
                            $vesselPIVendor->company_id = 3763;
                        break;
                        case 306 : 
                            $vesselPIVendor->company_id = 3820;
                        break;
                        case 307 : 
                            $vesselPIVendor->company_id = 5160;
                        break;
                        case 308 : 
                            $vesselPIVendor->company_id = 3874;
                        break;
                        case 309 : 
                            $vesselPIVendor->company_id = 4935;
                        break;
                        case 310 : 
                            $vesselPIVendor->company_id = 3909;
                        break;
                        case 324 : 
                            $vesselPIVendor->company_id = 5161;
                        break;
                        case 325 : 
                            $vesselPIVendor->company_id = 4701;
                        break;
                        case 332 : 
                            $vesselPIVendor->company_id = 5162;
                        break;
                        case 336 : 
                            $vesselPIVendor->company_id = 5163;
                        break;
                        case 341 : 
                            $vesselPIVendor->company_id = 3901;
                        break;
                        case 353 : 
                            $vesselPIVendor->company_id = 3803;
                        break;
                        case 354 : 
                            $vesselPIVendor->company_id = 5164;
                        break;
                        case 356 : 
                            $vesselPIVendor->company_id = 5165;
                        break;
                        case 357 : 
                            $vesselPIVendor->company_id = 4704;
                        break;
                        case 358 : 
                            $vesselPIVendor->company_id = 3766;
                        break;
                        case 359 : 
                            $vesselPIVendor->company_id = 3877;
                        break;
                        case 360 : 
                            $vesselPIVendor->company_id = 3886;
                        break;
                        case 362 : 
                            $vesselPIVendor->company_id = 5166;
                        break;
                        case 364 : 
                            $vesselPIVendor->company_id = 4704;
                        break;
                        case 358 : 
                            $vesselPIVendor->company_id = 3766;
                        break;
                        case 359 : 
                            $vesselPIVendor->company_id = 3877;
                        break;
                        case 360 : 
                            $vesselPIVendor->company_id = 3886;
                        break;
                        case 362 : 
                            $vesselPIVendor->company_id = 5166;
                        break;
                        case 364 : 
                            $vesselPIVendor->company_id = 4704;
                        break;
                        case 371 : 
                            $vesselPIVendor->company_id = 3909;
                        break;
                        case 373 : 
                            $vesselPIVendor->company_id = 5167;
                        break;
                        case 375 : 
                            $vesselPIVendor->company_id = 5168;
                        break;
                        case 377 : 
                            $vesselPIVendor->company_id = 5169;
                        break;
                        case 379 : 
                            $vesselPIVendor->company_id = 5170;
                        break;
                        case 385 : 
                            $vesselPIVendor->company_id = 3903;
                        break;
                        case 386 : 
                            $vesselPIVendor->company_id = 5171;
                        break;
                        case 397 : 
                            $vesselPIVendor->company_id = 3890;
                        break;
                        case 403 : 
                            $vesselPIVendor->company_id = 3820;
                        break;
                        case 405 : 
                            $vesselPIVendor->company_id = 3778;
                        break;
                        case 414 : 
                            $vesselPIVendor->company_id = 5172;
                        break;
                    }
                    $vesselPIVendor->save();
                }

                // H & M Company
                $vesselHMDatas = ArdentVesselHMData::where('object_id', $vessel->id)->get();

                foreach($vesselHMDatas as $vesselHMData)
                {
                    $vesselHMVendor = new VesselVendor();
                    $vesselHMVendor->vessel_id = $cdtVessel->id;
                    switch($vesselHMData->hm_id) {
                        case 294 : 
                            $vesselHMVendor->company_id = 5134;
                        break;
                        case 295 : 
                            $vesselHMVendor->company_id = 5135;
                        break;
                        case 302 : 
                            $vesselHMVendor->company_id = 4022;
                        break;
                        case 303 : 
                            $vesselHMVendor->company_id = 4285;
                        break;
                        case 311 : 
                            $vesselHMVendor->company_id = 4832;
                        break;
                        case 317 : 
                            $vesselHMVendor->company_id = 4125;
                        break;
                        case 318 : 
                            $vesselHMVendor->company_id = 4895;
                        break;
                        case 320 : 
                            $vesselHMVendor->company_id = 5136;
                        break;
                        case 321 : 
                            $vesselHMVendor->company_id = 4180;
                        break;
                        case 323 : 
                            $vesselHMVendor->company_id = 4871;
                        break;
                        case 326 : 
                            $vesselHMVendor->company_id = 4250;
                        break;
                        case 327 : 
                            $vesselHMVendor->company_id = 4206;
                        break;
                        case 328 : 
                            $vesselHMVendor->company_id = 4041;
                        break;
                        case 329 : 
                            $vesselHMVendor->company_id = 5137;
                        break;
                        case 330 : 
                            $vesselHMVendor->company_id = 4368;
                        break;
                        case 331 : 
                            $vesselHMVendor->company_id = 4898;
                        break;
                        case 333 : 
                            $vesselHMVendor->company_id = 5138;
                        break;
                        case 334 : 
                            $vesselHMVendor->company_id = 5139;
                        break;
                        case 335 : 
                            $vesselHMVendor->company_id = 5140;
                        break;
                        case 337 : 
                            $vesselHMVendor->company_id = 5141;
                        break;
                        case 338 : 
                            $vesselHMVendor->company_id = 3863;
                        break;
                        case 340 : 
                            $vesselHMVendor->company_id = 5142;
                        break;
                        case 343 : 
                            $vesselHMVendor->company_id = 5143;
                        break;
                        case 351 : 
                            $vesselHMVendor->company_id = 5144;
                        break;
                        case 352 : 
                            $vesselHMVendor->company_id = 4708;
                        break;
                        case 355 : 
                            $vesselHMVendor->company_id = 5164;
                        break;
                        case 365 : 
                            $vesselHMVendor->company_id = 4704;
                        break;
                        case 366 : 
                            $vesselHMVendor->company_id = 4260;
                        break;
                        case 367 : 
                            $vesselHMVendor->company_id = 4020;
                        break;
                        case 368 : 
                            $vesselHMVendor->company_id = 5145;
                        break;
                        case 369 : 
                            $vesselHMVendor->company_id = 5146;
                        break;
                        case 370 : 
                            $vesselHMVendor->company_id = 3995;
                        break;
                        case 372 : 
                            $vesselHMVendor->company_id = 5147;
                        break;
                        case 374 : 
                            $vesselHMVendor->company_id = 3877;
                        break;
                        case 376 : 
                            $vesselHMVendor->company_id = 4404;
                        break;
                        case 378 : 
                            $vesselHMVendor->company_id = 4915;
                        break;
                        case 383 : 
                            $vesselHMVendor->company_id = 5148;
                        break;
                        case 384 : 
                            $vesselHMVendor->company_id = 5149;
                        break;
                        case 391 : 
                            $vesselHMVendor->company_id = 4130;
                        break;
                        case 392 : 
                            $vesselHMVendor->company_id = 5150;
                        break;
                        case 393 : 
                            $vesselHMVendor->company_id = 4218;
                        break;
                        case 394 : 
                            $vesselHMVendor->company_id = 5151;
                        break;
                        case 395 : 
                            $vesselHMVendor->company_id = 5152;
                        break;
                        case 396 : 
                            $vesselHMVendor->company_id = 5153;
                        break;
                        case 404 : 
                            $vesselHMVendor->company_id = 4728;
                        break;
                        case 406 : 
                            $vesselHMVendor->company_id = 4073;
                        break;
                        case 407 : 
                            $vesselHMVendor->company_id = 4161;
                        break;
                        case 408 : 
                            $vesselHMVendor->company_id = 5154;
                        break;
                        case 409 : 
                            $vesselHMVendor->company_id = 4197;
                        break;
                        case 411 : 
                            $vesselHMVendor->company_id = 4363;
                        break;
                        case 412 : 
                            $vesselHMVendor->company_id = 4350;
                        break;
                        case 415 : 
                            $vesselHMVendor->company_id = 5156;
                        break;
                    }
                    $vesselHMVendor->save();
                }

                echo ('added: ' . $i);
                echo PHP_EOL;
                $i++;
            }
        }
    }
}
