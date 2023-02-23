<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Config;

class GSAController extends Controller
{
    //
    public function getGSALists(Request $request)
    {
        $userRole = Auth::user()->role_id;

        $results = [];
        if($userRole == 1 && request('admin')) {
            $sectors = Sector::groupBy('object_id')->get();
            // DJS : request('djs') ? djs gsa files : djs a gsa files
            $storagePath = request('djs') ? 'DJ-S' : 'DJ-S_A';
            foreach($sectors as $sector)
            {
                $gsaDirectory = 'gsa/' . $storagePath . '/' . $sector->atu . '/' . $sector->object_id . '/';
                $filesInFolder = Storage::disk('gcs')->files($gsaDirectory);
                $count = count($filesInFolder);

                $results[$sector->atu ? $sector->atu : 'all_areas'][] = Sector::where('object_id', $sector->object_id)->select('id', 'object_id', 'atu', 'opfac', 'area_name', 'name', 'sector_name', DB::raw($count . ' AS count'))->first();
                
            }
            return $results;
        }

        $atlanticAtus = [1, 5, 7, 8, 9];
        $pacificAtus = [11, 13, 14, 17];

        foreach($atlanticAtus as $atlanticAtu)
        {
            $results['atlantic_area'][$atlanticAtu] = Sector::where([['area_name', 'Atlantic Area'], ['atu', $atlanticAtu]])->get();

        }
        
        foreach($pacificAtus as $pacificAtu)
        {
            $results['pacific_area'][$pacificAtu] = Sector::where([['area_name', 'Pacific Area'], ['atu', $pacificAtu]])->get();
        }

        $results['all_areas'] = Sector::where('object_id', 0)->get();

        return $results;
    }

    // Files
    public function getFilesDOC($atu, $objectId, $djs)
    {
        $files = [];
        $directory = 'gsa/' . $djs . '/' . $atu . '/' . $objectId . '/';
        $filesInFolder = Storage::disk('gcs')->files($directory);
        foreach ($filesInFolder as $path) {
            $files[] = [
                'name' => pathinfo($path)['basename'],
                'size' => $this->formatBytes(Storage::disk('gcs')->size($directory . pathinfo($path)['basename'])),
                'ext' => pathinfo($path)['extension'] ?? null,
                'created_at' => date("Y-m-d", Storage::disk('gcs')->lastModified($directory . pathinfo($path)['basename']))
            ];
        }
        return $files;
    }

    public function destroyFileDOC($atu, $objectId, $djs, $fileName)
    {
        $directory = 'gsa/' . $djs . '/' . $atu . '/' . $objectId . '/';
        if (Storage::disk('gcs')->delete($directory . $fileName)) {
            $fileName = Storage::disk('gcs')->files($directory) ? 
                        pathinfo(Storage::disk('gcs')->files($directory)[0])['basename'] 
                        : NULL;
            
            if($djs == 'DJ-S') {
                Sector::where([['object_id', $objectId], ['atu', $atu]])
                        ->update([
                            'djs_url' => $fileName
                        ]);
            } else {
                Sector::where([['object_id', $objectId], ['atu', $atu]])
                        ->update([
                            'djs_a_url' => $fileName
                        ]);
            }

            return response()->json(['success' => true, 'message' => 'File deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function bulkDestroy($atu, $objectId, $djs, Request $request)
    {
        $removeData = $request->all();
        for($i = 0; $i < count($removeData); $i ++) {
            $directory = 'gsa/' . $djs . '/' . $atu . '/' . $objectId . '/';
            Storage::disk('gcs')->delete($directory . $removeData[$i]['name']);
        }
        return response()->json(['success' => true, 'message' => 'File deleted.']);
    }

    public function downloadFileDOCForce($atu, $objectId, $djs, $fileName)
    {
        ini_set('memory_limit', '-1');

        $directory = 'gsa/' . $djs . '/' . $atu . '/' . $objectId . '/';
        return response()->streamDownload(function() use ($directory, $fileName) {
            echo Storage::disk('gcs')->get($directory . $fileName);
        }, $fileName, [
                'Content-Type' => 'application/octet-stream'
            ]);
    }

    public function uploadFileDOC($atu, $objectId, $djs, Request $request)
    {

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        //  add_cors_headers_group_cdt_individual($request);
        $directory = 'gsa/' . $djs . '/' . $atu . '/' . $objectId . '/';
        
        $fileName = Storage::disk('gcs')->exists($directory . $request->file->getClientOriginalName()) ? 
                        date('m-d-Y_h:ia - ') . $request->file->getClientOriginalName()
                        : $request->file->getClientOriginalName();

        if($djs == 'DJ-S') {
            Sector::where([['object_id', $objectId], ['atu', $atu]])
                    ->update([
                        'djs_url' => $fileName
                    ]);
        } else {
            Sector::where([['object_id', $objectId], ['atu', $atu]])
                    ->update([
                        'djs_a_url' => $fileName
                    ]);
        }

        if (Storage::disk('gcs')->putFileAs($directory, request('file'), $fileName)) {
            return response()->json(['success' => true, 'message' => 'File uploaded.', 'name' => $fileName]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    private function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int)$size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(1024 ** ($base - floor($base)), $precision) . $suffixes[floor($base)];
        }

        return $size;
    }

    public function getGSACompaniesReport()
    {
        $cdtDBName = Config::get('database.connections')['mysql']['database'];

        return DB::select(DB::raw("SELECT 
                companies.name,
                network_companies.unique_identification_number_djs AS djs,
                network_companies.unique_identification_number_ardent AS djs_a,
                concat(company_addresses.street, ', ', company_addresses.city, ', ', company_addresses.state, ', ', company_addresses.zip) AS address,
                companies.phone,
                companies.website,
                zones.name AS cotp_zone,
                capabilities_fields.label AS primary_smff_service,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=1 THEN 'YES' ELSE NULL END) AS remote_assessment_and_consultation,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=3 THEN 'YES' ELSE NULL END) AS begin_assessment_of_structural_stability,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=4 THEN 'YES' ELSE NULL END) AS on_site_salvage_assessment,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=5 THEN 'YES' ELSE NULL END) AS assessment_of_structural_stability,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=6 THEN 'YES' ELSE NULL END) AS hull_and_bottom_survey,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=7 THEN 'YES' ELSE NULL END) AS emergency_towing,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=8 THEN 'YES' ELSE NULL END) AS salvage_plan,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=9 THEN 'YES' ELSE NULL END) AS external_emergency_transfer_operations,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=10 THEN 'YES' ELSE NULL END) AS emergency_lightering,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=13 THEN 'YES' ELSE NULL END) AS other_refloating_methods,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=14 THEN 'YES' ELSE NULL END) AS making_temporary_repairs,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=15 THEN 'YES' ELSE NULL END) AS diving_services_support,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=16 THEN 'YES' ELSE NULL END) AS special_salvage_operations_plan,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=17 THEN 'YES' ELSE NULL END) AS subsurface_product_removal,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=18 THEN 'YES' ELSE NULL END) AS heavy_lift,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=28 THEN 'YES' ELSE NULL END) AS remote_assessment_and_consultation_fire,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=29 THEN 'YES' ELSE NULL END) AS on_site_fire_assessment,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=30 THEN 'YES' ELSE NULL END) AS external_firefighting_teams,
                GROUP_CONCAT(CASE WHEN capabilities_values.field_id=31 THEN 'YES' ELSE NULL END) AS external_vessel_firefighting_systems
            
            FROM
                $cdtDBName.companies
                    LEFT OUTER JOIN
                $cdtDBName.company_addresses ON company_addresses.company_id = companies.id
                    LEFT OUTER JOIN
                $cdtDBName.network_companies ON network_companies.company_id = companies.id
                    LEFT OUTER JOIN
                $cdtDBName.zones ON zones.id = company_addresses.zone_id 
                    LEFT OUTER JOIN
                $cdtDBName.capabilities ON capabilities.id = companies.smff_service_id 
                    LEFT OUTER JOIN
                $cdtDBName.capabilities_fields ON capabilities_fields.id = capabilities.primary_service
                    LEFT OUTER JOIN
                $cdtDBName.capabilities_values ON capabilities_values.capabilities_id = capabilities.id
            WHERE
                network_companies.network_id = 1 AND companies.deleted_at IS NULL AND companies.networks_active = 1
            GROUP BY
                companies.name,
                network_companies.unique_identification_number_djs,
                network_companies.unique_identification_number_ardent,
                company_addresses.street,
                company_addresses.city,
                company_addresses.state,
                company_addresses.zip,
                companies.phone,
                companies.website,
                zones.name,
                capabilities_fields.label
            ORDER BY
                companies.name ASC;"));
    }

    public function getGSAVesselsReport()
    {
        $cdtDBName = Config::get('database.connections')['mysql']['database'];
        return DB::select(DB::raw("SELECT 
                vessels.name AS vessel_name,
                companies.name AS company_name,
                plans.plan_number AS plan_number,
                network_companies.unique_identification_number_djs AS djs,
                network_companies.unique_identification_number_ardent AS djs_a,
                zones.name AS cotp_zone,
                vessel_ais_positions.timestamp AS timestamp,
                capabilities_fields.label AS primary_smff_service,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 7 THEN 'YES'
                    ELSE NULL
                END) AS emergency_towing,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 19 THEN capabilities_values.value
                    ELSE NULL
                END) AS tug_type,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 20 THEN capabilities_values.value
                    ELSE NULL
                END) AS horsepower,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 21 THEN capabilities_values.value
                    ELSE NULL
                END) AS bollard_pull,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 31 THEN 'YES'
                    ELSE NULL
                END) AS external_vessel_firefighting_systems,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 32 THEN capabilities_values.value
                    ELSE NULL
                END) AS fifi_classification,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 33 THEN capabilities_values.value
                    ELSE NULL
                END) AS pumping_capacity,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 34 THEN capabilities_values.value
                    ELSE NULL
                END) AS foam_quantity,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 10 THEN 'YES'
                    ELSE NULL
                END) AS emergency_lightering,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 12 THEN 'YES'
                    ELSE NULL
                END) AS capacity_in_bbl,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 18 THEN capabilities_values.value
                    ELSE NULL
                END) AS heavy_left,
                GROUP_CONCAT(CASE
                    WHEN capabilities_values.field_id = 23 THEN capabilities_values.value
                    ELSE NULL
                END) AS lifting_gear_minimum_swl
            FROM
                $cdtDBName.vessels
                    LEFT OUTER JOIN
                $cdtDBName.companies ON vessels.company_id = companies.id
                    LEFT OUTER JOIN
                $cdtDBName.plans ON vessels.plan_id = plans.id
                    LEFT OUTER JOIN
                $cdtDBName.network_companies ON network_companies.company_id = companies.id
                    LEFT OUTER JOIN
                $cdtDBName.capabilities ON capabilities.id = vessels.smff_service_id
                    LEFT OUTER JOIN
                $cdtDBName.capabilities_fields ON capabilities_fields.id = capabilities.primary_service
                    LEFT OUTER JOIN
                $cdtDBName.capabilities_values ON capabilities_values.capabilities_id = capabilities.id
                    LEFT OUTER JOIN
                $cdtDBName.vessel_ais_positions ON vessels.id = vessel_ais_positions.vessel_id
                    AND vessel_ais_positions.id = (SELECT 
                        MAX(id)
                    FROM
                        vessel_ais_positions
                    WHERE
                        vessel_id = vessels.id)
                    LEFT OUTER JOIN
                $cdtDBName.zones ON zones.id = vessel_ais_positions.zone_id
            WHERE
                network_companies.network_id = 1
                    AND companies.networks_active = 1 AND companies.deleted_at IS NULL AND vessels.deleted_at IS NULL
            GROUP BY companies.name, vessels.name, network_companies.unique_identification_number_djs , network_companies.unique_identification_number_ardent , companies.phone , companies.website , capabilities_fields.label, zones.name, vessel_ais_positions.timestamp, plans.plan_number
            ORDER BY companies.name ASC;"));
    }

}
