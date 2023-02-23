<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\TrackChange;
use App\Models\ChangesTableName;
use App\Models\Action;
use App\Models\Frequency;
use App\Models\ReportSchedule;
use App\Models\Report;
use App\Models\ReportType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Laracsv\Export;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VesselReportExport;

class ReportsController extends Controller
{
    public function getNASAPotential()
    {
        $companies = Company::select('id', 'plan_number', 'name', 'email', 'fax', 'phone', 'website')->whereHas('networks', function ($q) {
            $q->where('networks.id', 4);
        })->withCount(['vessels' => function ($q1) {
            $q1->whereHas('networks', function ($q2) {
                $q2->where('networks.id', 4);
            });
        }])->orderBy('vessels_count', 'desc')->get();
        $csvExporter = new Export();
        $csvExporter->build($companies, ['vessels_count', 'name', 'id', 'plan_number', 'email', 'fax', 'phone', 'website'])->download();
    }

    public function getDJSVessels(Request $request)
    {
        $sort = $request->has('sortBy') ? $request->get('sortBy') : 'updated_at';
        $sortDir = $request->has('direction') ? $request->get('direction') : 'desc';

        $djs_vessels = Vessel::whereIn('active_field_id', [2, 3, 5])->with('vendors.hm', 'company.dpaContacts')->orderBy($sort, $sortDir)->get();
        //whereHas('company', function ($q) {
        //            $q->where('name', 'like', '%donjon%');
        //        })
        $report = [];
        foreach ($djs_vessels as $vessel) {
            $make['imo'] = $vessel->imo;
            $make['mmsi'] = $vessel->mmsi;
            $make['name'] = $vessel->name;
            $make['company'] = $vessel->company->name;
            $make['country'] = $vessel->company->addresses()->first() ? $vessel->company->addresses()->first()->country : '';
            foreach ($vessel->vendors as $vendor) {
                if ($vendor->hm) {
                    $make['hm'] = $vendor->name;
                }
            }
            if ($vessel->company->dpaContacts->count()) {
                $make['dpa'] = $vessel->company->dpaContacts[0]->prefix . ' ' . $vessel->company->dpaContacts[0]->first_name . ' ' . $vessel->company->dpaContacts[0]->last_name;
                $make['dpa_email'] = $vessel->company->dpaContacts[0]->email;
                $make['dpa_work_phone'] = $vessel->company->dpaContacts[0]->work_phone;
                $make['dpa_mobile_phone'] = $vessel->company->dpaContacts[0]->mobile_phone;
                $make['dpa_aoh_phone'] = $vessel->company->dpaContacts[0]->aoh_phone;
                $make['dpa_fax'] = $vessel->company->dpaContacts[0]->fax;
            }
            $report[] = $make;
        }

        $per_page = empty(request('per_page')) ? 10 : (int)request('per_page');

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($report);
        $currentPageItems = $itemCollection->slice(($currentPage * $per_page) - $per_page, $per_page)->all();

        $paginatedItems = new LengthAwarePaginator(array_values($currentPageItems), count($report), $per_page);
        return $paginatedItems;

        $csvExporter = new Export();
        $csvExporter->build(collect($report), ['imo' => 'IMO', 'mmsi' => 'MMSI', 'name' => 'Name', 'hm' => 'Hull and Machinery', 'dpa' => 'DPA', 'dpa_email' => 'DPA Email', 'dpa_work_phone' => 'DPA Work Phone', 'dpa_mobile_phone' => 'DPA Mobile Phone', 'dpa_aoh_phone' => 'DPA AOH Phone', 'dpa_fax' => 'DPA Fax'])->download();
    }

    public function exportActiveVessel()
    {
        $djsVessels = Vessel::where('active_field_id', 2)->with('vendors.hm', 'company.dpaContacts')->orderBy('updated_at', 'desc')->get();
        $report = [];
        foreach ($djsVessels as $vessel) {
            $make['imo'] = $vessel->imo;
            $make['mmsi'] = $vessel->mmsi;
            $make['name'] = $vessel->name;
            $make['company'] = $vessel->company->first()->name;
            $make['country'] = $vessel->company->addresses()->first() ? $vessel->company->addresses()->first()->country : '';
            foreach ($vessel->vendors as $vendor) {
                if ($vendor->hm) {
                    $make['hm'] = $vendor->name;
                }
            }
            if ($vessel->company->dpaContacts->count()) {
                $make['dpa'] = $vessel->company->dpaContacts[0]->prefix . ' ' . $vessel->company->dpaContacts[0]->first_name . ' ' . $vessel->company->dpaContacts[0]->last_name;
                $make['dpa_email'] = $vessel->company->dpaContacts[0]->email;
                $make['dpa_work_phone'] = $vessel->company->dpaContacts[0]->work_phone;
                $make['dpa_mobile_phone'] = $vessel->company->dpaContacts[0]->mobile_phone;
                $make['dpa_aoh_phone'] = $vessel->company->dpaContacts[0]->aoh_phone;
                $make['dpa_fax'] = $vessel->company->dpaContacts[0]->fax;
            }
            $report[] = $make;
        }

        return $report;
    }

    public function trackReport(Request $request)
    {
        $dates = $request->input('dates');

        if($dates) {
            if(count($dates) < 2) {
                return response()->json([ 'success' => false, 'message' => 'Please input correct dates!' ]); 
            }
        }

        if(!$dates) {
            $dates[0] = date("Y-m-d H:i:s", strtotime('-30days'));
            $dates[1] = date("Y-m-d H:i:s");
        }

        $sort = $request->has('sortBy') ? $request->get('sortBy') : 'updated_at';
        $sortDir = $request->has('direction') ? $request->get('direction') : 'desc';

        $changedTableId = (int)request('changed_table_name_id');
        if(request('action_id')) {
            $fieldIds = TrackChange::where([['changes_table_name_id', $changedTableId], ['action_id', request('action_id')]])->whereBetween('updated_at', array($dates[0], $dates[1]))->orderBy($sort, $sortDir)->get();
        } else {
            $fieldIds = TrackChange::where('changes_table_name_id', $changedTableId)->whereBetween('updated_at', array($dates[0], $dates[1]))->orderBy($sort, $sortDir)->get();
        }
    
        $results = [];
        foreach($fieldIds as $fieldId)
        {   
            switch ($changedTableId) {
                case 1: // Users Table
                    $changedRows = User::whereIn('id', explode(",", $fieldId->ids))
                                    ->orderBy($sort, $sortDir)
                                    ->get();

                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'id' => Action::where('id', $fieldId->action_id)->first()->id == 2 ? NULL : $changedRow->id,
                            'name' => $changedRow->username,
                            'company_id' => $changedRow->primary_company_id,
                            'company_name' => Company::where('id', $changedRow->primary_company_id)->first()->name,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                            'changed_table' => 'individuals'
                        ];
                    }
                break;
                case 2: // Vessel Table
                    if(request('active_field_id') == -1) {
                        $changedRows = Vessel::whereIn('id', explode(",", $fieldId->ids))
                                        ->whereIn('active_field_id', [2,3,5])
                                        ->orderBy($sort, $sortDir)
                                        ->get();
                    } else {
                        $changedRows = Vessel::whereIn('id', explode(",", $fieldId->ids))
                                        ->where('active_field_id', request('active_field_id'))
                                        ->orderBy($sort, $sortDir)
                                        ->get();
                    }

                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'id' => Action::where('id', $fieldId->action_id)->first()->id == 2 ? NULL : $changedRow->id,
                            'name' => $changedRow->name,
                            'imo' => $changedRow->imo,
                            'official_number' => $changedRow->official_number,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                            'changed_table' => 'vessels'
                        ];
                    }
                break;
                case 3: // Company Table
                    if(request('active_field_id') == -1) {
                        $changedRows = Company::whereIn('id', explode(",", $fieldId->ids))
                                        ->whereIn('active_field_id', [2,3,5])
                                        ->orderBy($sort, $sortDir)
                                        ->get();
                    } else {
                        $changedRows = Company::whereIn('id', explode(",", $fieldId->ids))
                                        ->where('active_field_id', request('active_field_id'))
                                        ->orderBy($sort, $sortDir)
                                        ->get();
                    }

                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'id' => Action::where('id', $fieldId->action_id)->first()->id == 2 ? NULL : $changedRow->id,
                            'name' => $changedRow->name,
                            'plan_number' => $changedRow->plan_number,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                            'changed_table' => 'companies'
                        ];
                    }
                break;
            }
        }
        

        $per_page = empty(request('per_page')) ? 10 : (int)request('per_page');

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($results);
        $currentPageItems = $itemCollection->slice(($currentPage * $per_page) - $per_page, $per_page)->all();

        $paginatedItems = new LengthAwarePaginator(array_values($currentPageItems), count($results), $per_page);
        return $paginatedItems;
    }

    public function exportTrackReport()
    {
        $fieldIds = TrackChange::orderBy('updated_at', 'desc')->get();
    
        $results = [];
        foreach($fieldIds as $fieldId)
        {   
            switch ($fieldId->changes_table_name_id) {
                case 1: // Users Table
                    $changedRows = User::whereIn('id', explode(",", $fieldId->ids))->orderBy('updated_at', 'desc')->get();
                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'name' => $changedRow->username,
                            'company' => $changedRow->primary_company_id,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                        ];
                    }
                break;
                case 2: // Vessel Table
                    $changedRows = Vessel::whereIn('id', explode(",", $fieldId->ids))->orderBy('updated_at', 'desc')->get();
                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'name' => $changedRow->name,
                            'imo' => $changedRow->imo,
                            'official_number' => $changedRow->official_number,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                        ];
                    }
                break;
                case 3: // Company Table
                    $changedRows = Company::whereIn('id', explode(",", $fieldId->ids))->orderBy('updated_at', 'desc')->get();
                    foreach($changedRows as $changedRow)
                    {
                        $results[] = [
                            'name' => $changedRow->name,
                            'plan_number' => $changedRow->plan_number,
                            'date' => (string)$changedRow->updated_at,
                            'action' => Action::where('id', $fieldId->action_id)->first()->name,
                        ];
                    }
                break;
            }
        }
        
        return $results;
    }

    public function activeVesselReport(Request $request)
    {
        $sort = $request->has('sortBy') ? $request->get('sortBy') : 'updated_at';
        $sortDir = $request->has('direction') ? $request->get('direction') : 'desc';

        $vesselTable = new Vessel;
        $vesselTableName = $vesselTable->table();
        $companyTable = new Company;
        $companyTableName = $companyTable->table();

        $resultsQuery = Vessel::from($vesselTableName . ' AS v')->select(
            DB::raw('v.id, v.imo, v.mmsi, v.name, vt.name as hm, c.email, c.phone, c.work_phone, c.aoh_phone, c.fax, c.name as company, ca.country'))
                ->distinct()
                ->where('v.active_field_id', 2)
                ->leftJoin($companyTableName . " AS c", 'v.company_id','=','c.id')
                ->leftJoin('company_addresses AS ca', 'c.id', '=', 'ca.company_id')
                ->leftJoin('vendor_types AS vt', 'vt.id', '=', 'c.vendor_type');
        $per_page = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');

        $results = $resultsQuery->paginate($per_page);

        return $results;
    }

    public function noContractCompany(Request $request)
    {
        $sort = $request->has('sortBy') ? $request->get('sortBy') : 'updated_at';
        $sortDir = $request->has('direction') ? $request->get('direction') : 'desc';

        $companyIds = Company::where('active_field_id', 2)->orderBy($sort, $sortDir)->pluck('id');

        $resultsQuery = Company::whereHas('dpaContacts', function ($q) use ($companyIds) {
                $q->whereIn('company_id', $companyIds);
            });
        $per_page = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');

        $results = $resultsQuery->paginate($per_page);

        return $results;
    }

    public function exportNoContractCompany()
    {
        $companyIds = Company::where('active_field_id', 2)->orderBy('updated_at', 'desc')->pluck('id');

        $resultsQuery = Company::whereHas('dpaContacts', function ($q) use ($companyIds) {
                $q->whereIn('company_id', $companyIds);
            });
        // $per_page = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');

        // $results = $resultsQuery->paginate($per_page);

        return $resultsQuery->get();
    }

    public function setReportSchedule(Request $request)
    {
        $reportTypeId = request('report_type_id');
        $frequencyId = request('frequency_id');
        $userIds = request('user_ids');
        if(!$userIds) {
            return response()->json(['success' => false, 'message' => 'User Id is not setting.']);
        }
        foreach($userIds as $userId)
        {
            ReportSchedule::create([
                'report_type_id' => $reportTypeId,
                'frequency_id' => $frequencyId,
                'user_id' => $userId
            ]);
        }

        return response()->json(['success' => true, 'message' => 'The schedule has been set.']);
    }

    public function changedTables()
    {
        return ChangesTableName::all();
    }

    public function actions()
    {
        return Action::all();
    }

    public function reportType()
    {
        return ReportType::all();
    }

    public function frequency()
    {
        return Frequency::all();
    }

    public function vesselFileReport(Company $company)
    {
        // $folderLists = [
        //     'damage_stability_models',
        //     'drawings',
        //     'drills-and-exercises',
        //     'prefire_plan_certification',
        //     'prefire_plans',
        //     'stability-booklet'
        // ];
        // $companyName = $company->name;
        // $vessels = Vessel::where('company_id', $company->id)
        //                 ->whereIn('active_field_id', [2, 3])
        //                 ->get();
        
        // $i = 0;
        // $totalCount = 0;
        // foreach($vessels as $vessel)
        // {
        //     $fileCount = 0;
        //     $companyVesselFileInfo[$i]['files'] = [];
        //     foreach($folderLists as $location)
        //     {
        //         $directory = 'files/new/' . $location . '/' . $vessel->id . '/';
        //         $filesInFolder = Storage::disk('gcs')->files($directory);
        //         $fileCount += count($filesInFolder);
        //         foreach ($filesInFolder as $path) {
        //             $companyVesselFileInfo[$i]['files'][] = [
        //                 'name' => pathinfo($path)['basename'],
        //                 'size' => $this->formatBytes(Storage::disk('gcs')->size($directory . pathinfo($path)['basename'])),
        //                 'ext' => pathinfo($path)['extension'] ?? null,
        //                 'created_at' => date("Y-m-d", Storage::disk('gcs')->lastModified($directory . pathinfo($path)['basename']))
        //             ];
        //         }
        //     }
        //     $totalCount += $fileCount;
        //     $companyVesselFileInfo[$i]['vessel_name'] = $vessel->name . '(' . $fileCount . ')';
        //     $i ++;
        // }

        // $companyVesselFileInfo['company_name'] = $companyName . '(' . $totalCount . ')';

        // return $companyVesselFileInfo;
        Excel::store(new VesselReportExport($company), 'vesselReport.xlsx');
        return response()->download(storage_path('app/vesselReport.xlsx'));
    }

    private function getFileInformations($directory)
    {
        $files = [];
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

    public function getReport()
    {
        return Report::all();
    }

    public function getVendorReportData(Request $request)
    {
        $vesselVendors = Vessel::from('vessels as v')
                        ->select('v.name as vessel_name', 'v.imo as imo', 'v.official_number as official_number', 'c.name as company_name', 'c2.name as hm_name')
                        ->leftjoin('companies as c', 'c.id', '=', 'v.company_id')
                        ->leftjoin('vessels_vendors as vv', 'v.id', '=', 'vv.vessel_id')
                        ->leftjoin('companies as c2', 'vv.company_id', '=', 'c2.id')
                        ->where('c2.vendor_type', request('vendor_type_id'));

        if((int)request('active_field_id') !== -1) {
            $vesselVendors = $vesselVendors->where('v.active_field_id', request('active_field_id'));
        } else {
            $vesselVendors = $vesselVendors->whereIn('v.active_field_id', [2, 3, 5]);
        }

        return $vesselVendors->groupBy('v.id', 'v.name', 'v.imo', 'c.name', 'c2.name')
                                ->orderBy('c.name', 'ASC')
                                ->get();
    }

    public function getBillingReport(Request $request)
    {
        $groupBy = request('tank') ? 
            "billing_information.company_id , billing_information.billing_mode_id , billing_information.not_billed , billing_information.deactivated , billing_information.is_discountable , billing_information.deactivation_reason , billing_information.deleted_at , billing_information.tank_contract_no , billing_information.tank_expiration_date , billing_information.tank_cancellation_date , billing_information.tank_contract_signed_date , billing_information.tank_automatic_billing_start_date , tank_billing_start_date , billing_information.tank_contract_length , billing_information.tank_free_years , billing_information.tank_annual_retainer_fee , billing_information.manual_tank_discount , billing_information.non_tank_contract_no , billing_information.non_tank_expiration_date , billing_information.non_tank_cancellation_date , billing_information.non_tank_contract_signed_date , billing_information.non_tank_automatic_billing_start_date , non_tank_billing_start_date , billing_information.non_tank_contract_length , billing_information.non_tank_free_years , billing_information.non_tank_annual_retainer_fee , billing_information.manual_non_tank_discount , billing_information.last_billed_date"
            : "billing_information.company_id , billing_information.billing_mode_id , billing_information.not_billed , billing_information.deactivated , billing_information.is_discountable , billing_information.deactivation_reason , billing_information.deleted_at , billing_information.non_tank_contract_no , billing_information.non_tank_expiration_date , billing_information.non_tank_cancellation_date , billing_information.non_tank_contract_signed_date , billing_information.non_tank_automatic_billing_start_date , non_tank_billing_start_date , billing_information.non_tank_contract_length , billing_information.non_tank_free_years , billing_information.non_tank_annual_retainer_fee , billing_information.manual_non_tank_discount , billing_information.non_tank_contract_no , billing_information.non_tank_expiration_date , billing_information.non_tank_cancellation_date , billing_information.non_tank_contract_signed_date , billing_information.non_tank_automatic_billing_start_date , non_tank_billing_start_date , billing_information.non_tank_contract_length , billing_information.non_tank_free_years , billing_information.non_tank_annual_retainer_fee , billing_information.manual_non_tank_discount, billing_information.last_billed_date";        
        $activeFields = request('donjon') ? 
                " AND (vessels.active_field_id = 2 OR vessels.active_field_id = 5)" : " AND (vessels.active_field_id = 3 OR vessels.active_field_id = 5)";
        $billingMode = request('billing_mode');
        $retainerFeeCondition = request('tank') ? 
            "AND billing_information.tank_annual_retainer_fee > 0" :
            "AND billing_information.non_tank_annual_retainer_fee > 0";

        return DB::select(DB::raw("SELECT 
                billing_modes.name AS billing_mode,
                billing_information.company_id AS company_id,
                companies.name AS company_name,
                billing_information.tank_contract_no AS tank_contract_number,
                billing_information.non_tank_contract_no AS non_tank_contract_number,
                IF(billing_information.tank_automatic_billing_start_date = 1,
                    (DATE_ADD(tank_contract_signed_date,
                        INTERVAL IFNULL(tank_free_years, 0) YEAR)),
                    billing_information.tank_billing_start_date) AS calculated_tank_billing_start_date,

                IF(billing_information.non_tank_automatic_billing_start_date = 1,
                    (DATE_ADD(non_tank_contract_signed_date,
                        INTERVAL IFNULL(non_tank_free_years, 0) YEAR)),
                    billing_information.non_tank_billing_start_date) AS calculated_non_tank_billing_start_date,

                SUM(IF(vessels.tanker = 1, 1, 0)) AS number_of_tank_vessels,
                SUM(IF(vessels.tanker = 0, 1, 0)) AS number_of_non_tank_vessels,

                billing_information.tank_annual_retainer_fee AS tank_annual_retainer_fee,
                billing_information.non_tank_annual_retainer_fee AS non_tank_annual_retainer_fee,

                ROUND((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee,
                    2) AS gross_tank_total,
                ROUND((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee,
                    2) AS gross_non_tank_total,

                IF(billing_information.is_discountable = 1,
                    IFNULL(billing_information.manual_tank_discount,
                        ((IFNULL((SELECT 
                                discount_tank.discount
                            FROM
                                discount_tank
                            WHERE
                                (SUM(IF(vessels.tanker = 1, 1, 0))) BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme),
                        0)))),
                    NULL) AS tank_discount,
                IF(billing_information.is_discountable = 1,
                    IFNULL(billing_information.manual_non_tank_discount,
                            ((IFNULL((SELECT 
                                                    discount_non_tank.discount
                                                FROM
                                                    discount_non_tank
                                                WHERE
                                                    (SUM(IF(vessels.tanker = 0, 1, 0))) BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),
                                            0)))),
                    NULL) AS non_tank_discount,

                IF(billing_information.is_discountable = 1,
                    ROUND((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee,
                            2) - IF(billing_information.manual_tank_discount IS NULL,
                        ROUND((100 - ((IFNULL((SELECT 
                                    discount_tank.discount
                                FROM
                                    discount_tank
                                WHERE
                                    (SUM(IF(vessels.tanker = 1, 1, 0))) BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme),
                                0)))) / 100 * ((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee),
                            2),
                        ROUND((100 - billing_information.manual_tank_discount) / 100 * ((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee),
                                2)),
                    NULL) AS tank_discount_value,
                IF(billing_information.is_discountable = 1,
                    ROUND((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee,
                            2) - IF(billing_information.manual_non_tank_discount IS NULL,
                        ROUND((100 - ((IFNULL((SELECT 
                                                        discount_non_tank.discount
                                                    FROM
                                                        discount_non_tank
                                                    WHERE
                                                        (SUM(IF(vessels.tanker = 0, 1, 0))) BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),
                                                0)))) / 100 * ((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee),
                                2),
                        ROUND((100 - billing_information.manual_non_tank_discount) / 100 * ((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee),
                                2)),
                    NULL) AS non_tank_discount_value,

                IF(billing_information.is_discountable = 1,
                    (IF(billing_information.manual_tank_discount IS NULL,
                        ROUND((100 - ((IFNULL((SELECT 
                                            discount_tank.discount
                                        FROM
                                            discount_tank
                                        WHERE
                                            (SUM(IF(vessels.tanker = 1, 1, 0))) BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme),
                                    0)))) / 100 * ((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee),
                                2),
                        ROUND((100 - billing_information.manual_tank_discount) / 100 * ((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee),
                                2))),
                    ROUND((SUM(IF(vessels.tanker = 1, 1, 0))) * billing_information.tank_annual_retainer_fee,
                            2)) AS tank_net_total,
                IF(billing_information.is_discountable = 1,
                    (IF(billing_information.manual_non_tank_discount IS NULL,
                        ROUND((100 - ((IFNULL((SELECT 
                                        discount_non_tank.discount
                                    FROM
                                        discount_non_tank
                                    WHERE
                                        (SUM(IF(vessels.tanker = 0, 1, 0))) BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),
                                0)))) / 100 * ((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee),
                            2),
                        ROUND((100 - billing_information.manual_non_tank_discount) / 100 * ((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee),
                                2))),
                    ROUND((SUM(IF(vessels.tanker = 0, 1, 0))) * billing_information.non_tank_annual_retainer_fee,
                            2)) AS non_tank_net_total,
                
                billing_information.last_billed_date AS last_billed_date
            FROM
                billing_information
                    JOIN
                vessels ON billing_information.company_id = vessels.company_id
                    JOIN
                billing_modes ON billing_information.billing_mode_id = billing_modes.id
                    JOIN
                companies ON billing_information.company_id = companies.id
            WHERE
                billing_information.not_billed = 0
                    AND vessels.deleted_at IS NULL
                    " . $activeFields . "
                    AND billing_modes.name = " . "'" . $billingMode . "'" . "
                    " . $retainerFeeCondition . "
            GROUP BY $groupBy
            ORDER BY companies.name ASC;"));
    }
}
