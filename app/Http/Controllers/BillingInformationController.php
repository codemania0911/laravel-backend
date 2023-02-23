<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\BillingInformation;
use App\Models\DiscountNonTank;
use App\Models\DiscountTank;
use App\Models\Vessel;
use App\Models\BillingMode;

use App\Http\Resources\BillingInformationResource;
use App\Http\Resources\AccountBillingInformationResource;

use Illuminate\Support\Facades\DB;

class BillingInformationController extends Controller
{
    //
    public function getBillingInformation(Company $company, Request $request)
    {
        return BillingInformationResource::collection($company->billingInformation()->get());
    }

    public function updateBillingInformation(Company $company, Request $request)
    {
        $billingInformation = new BillingInformation();
        if(BillingInformation::where('company_id', $company->id)->first()) {
            $billingInformation = BillingInformation::where('company_id', $company->id)->first();
        }

        $billingInformation->company_id = $company->id;
        $billingInformation->last_billed_date = request('last_billed_date');
        $billingInformation->not_billed = request('not_billed');
        $billingInformation->billing_mode_id = request('billing_mode_id');
        $billingInformation->deactivated = request('deactivated');
        $billingInformation->deactivation_reason = request('deactivation_reason');
        $billingInformation->is_discountable = request('is_discountable');
        
        // Tank Information
        $billingInformation->tank_automatic_billing_start_date = request('tank_automatic_billing_start_date');
        $billingInformation->tank_contract_no = request('tank_contract_no');
        $billingInformation->tank_expiration_date = request('tank_expiration_date');
        $billingInformation->tank_cancellation_date = request('tank_cancellation_date');
        $billingInformation->tank_contract_signed_date = request('tank_contract_signed_date');
        $billingInformation->tank_billing_start_date = !request('tank_automatic_billing_start_date') ? request('tank_billing_start_date') : NULL;
        $billingInformation->tank_contract_length = request('tank_contract_length');
        $billingInformation->tank_free_years = request('tank_free_years');
        $billingInformation->tank_annual_retainer_fee = request('tank_annual_retainer_fee');
        $billingInformation->tank_billing_note = request('tank_billing_note');
        $billingInformation->manual_tank_discount = request('manual_tank_discount');

        // Non Tank Information
        $billingInformation->non_tank_automatic_billing_start_date = request('non_tank_automatic_billing_start_date');
        $billingInformation->non_tank_contract_no = request('non_tank_contract_no');
        $billingInformation->non_tank_expiration_date = request('non_tank_expiration_date');
        $billingInformation->non_tank_cancellation_date = request('non_tank_cancellation_date');
        $billingInformation->non_tank_contract_signed_date = request('non_tank_contract_signed_date');
        $billingInformation->non_tank_billing_start_date = !request('non_tank_automatic_billing_start_date') ? request('non_tank_billing_start_date') : NULL;
        $billingInformation->non_tank_contract_length = request('non_tank_contract_length');
        $billingInformation->non_tank_free_years = request('non_tank_free_years');
        $billingInformation->non_tank_annual_retainer_fee = request('non_tank_annual_retainer_fee');
        $billingInformation->non_tank_billing_note = request('non_tank_billing_note');
        $billingInformation->manual_non_tank_discount = request('manual_non_tank_discount');

        if($billingInformation->save()) {
            return response()->json(['success' => true, 'message' => 'Billing information Updated Successfully!']);
        }
    }

    public function getDiscountInfo()
    {
        $data['tank'] = DiscountTank::get();
        $data['non_tank'] = DiscountNonTank::get();

        return $data;
    }

    public function getAccountBillingInformation(Request $request)
    {
        $query = $request->get('query');
        $perPage = request('per_page') ? request('per_page') : 10;

        $results = Vessel::from('vessels as v')
                    ->select(DB::raw(request('staticSearch')['billing_mode'] == 'group' ? BillingInformation::GROUP : BillingInformation::DEFAULT))
                    ->join('companies as c', 'c.id', '=', 'v.company_id')
                    ->join('billing_information as bi', 'c.id', '=', 'bi.company_id')
                    ->where([['bi.not_billed', 0], ['bi.deactivated', NULL],
                            ['v.deleted_at', NULL], ['c.deleted_at', NULL]])
                    ->groupBy('c.id', 'bi.is_discountable', 'bi.last_billed_date', 
                            'bi.tank_contract_no', 'bi.tank_billing_start_date', 'bi.non_tank_contract_no', 
                            'bi.non_tank_billing_start_date', 'bi.manual_tank_discount', 'bi.manual_non_tank_discount')
                    ->orderBy('c.name');

        $this->staticSearch($results, request('staticSearch'));

        if (!empty($query) && strlen($query) > 2) {
            $uids = Company::search($query)->get('id')->pluck('id');
            $results = $results->whereIn('c.id', $uids);
        }

        $results = $results->paginate($perPage);

        return $results;
    }

    private function staticSearch($model, $staticSearch)
    {
        // switch ($staticSearch['option']) {
        //     case 0:
        //         $model = $model->where([['bi.non_tank_annual_retainer_fee', '>', 0], 
        //                             ['bi.non_tank_billing_start_date', '<', date('Y-m-d H:i:s')]]);
        //     break;
        //     case 1:
        //         $model = $model->where([['bi.tank_annual_retainer_fee', '>', 0], 
        //                             ['bi.tank_billing_start_date', '<', date('Y-m-d H:i:s')]]);
        //     break;
        //     case 2:
        //         $model = $model->where(function ($query) {
        //                             $query->where('bi.tank_annual_retainer_fee', '>', 0)
        //                                     ->orWhere('bi.non_tank_annual_retainer_fee', '>', 0);
        //                         })
        //                         ->where(function ($query) {
        //                             $query->where('bi.tank_billing_start_date', '<', date("Y-m-d"))
        //                                     ->orWhere('bi.non_tank_billing_start_date', '<', date("Y-m-d"));
        //                         });
        //     break;
        // }

        switch ($staticSearch['option']) {
            case 0:
                $model = $model->where('bi.non_tank_annual_retainer_fee', '>', 0);
            break;
            case 1:
                $model = $model->where('bi.tank_annual_retainer_fee', '>', 0);
            break;
            case 2:
                $model = $model->where(function ($query) {
                                    $query->where('bi.tank_annual_retainer_fee', '>', 0)
                                            ->orWhere('bi.non_tank_annual_retainer_fee', '>', 0);
                                });
            break;
        }

        $model = $staticSearch['djs'] ? $model->whereIn('v.active_field_id', [2, 5]) : $model->whereIn('v.active_field_id', [3, 5]);

        switch($staticSearch['billing_mode']) {
            case 'vessel' : 
                $model = $model->where('bi.billing_mode_id', 2);
            break;
            case 'client' : 
                $model = $model->where('bi.billing_mode_id', 1);
            break;
            case 'group' : 
                $model = $model->where('bi.billing_mode_id', 3)
                            ->join('vessel_billing_group as vbg', 'vbg.id', '=', 'v.vessel_billing_group_id');
            break;
        }
        // $model = $staticSearch['billing_mode'] == 'group' ? $model->join('vessel_billing_group as vbg', 'vbg.id', '=', 'v.vessel_billing_group_id') : $model;

        return $model;
    }

    public function totalBillingInformation()
    {
        $currentDate = date("Y-m-d");
        return DB::select(DB::raw("SELECT 
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 1 AND (companies.active_field_id = 2 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN companies.id END)) AS djs_client_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 2 AND vessels.deleted_at IS NULL AND (companies.active_field_id = 2 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN companies.id END)) AS djs_vessel_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 3 AND (companies.active_field_id = 2 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN companies.id END)) AS djs_group_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 1 AND vessels.deleted_at IS NULL AND vessels.active_field_id = 2 AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_client_vessels,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 2 AND vessels.deleted_at IS NULL AND vessels.active_field_id = 2 AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_vessel_vessels,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 3 AND vessels.deleted_at IS NULL AND vessels.active_field_id = 2 AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_group_vessels,
            (" . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 1
                    AND vessels.active_field_id=2
            GROUP BY companies.id, vessels.tanker, billing_information.is_discountable) AS group_amount) AS djs_client_amount,
            (" . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 2
                    AND vessels.active_field_id=2
            GROUP BY companies.id, vessels.tanker, billing_information.is_discountable) AS group_amount) AS djs_vessel_amount,
            ( " . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                JOIN vessel_billing_group ON vessels.vessel_billing_group_id = vessel_billing_group.id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 3
                    AND vessels.active_field_id=2
            GROUP BY companies.id, vessels.tanker, billing_information.is_discountable) AS group_amount) AS djs_group_amount,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 1 AND (companies.active_field_id = 3 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0)
            THEN companies.id END)) AS djs_a_client_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 2 AND vessels.deleted_at IS NULL AND (companies.active_field_id = 3 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN companies.id END)) AS djs_a_vessel_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 3 AND (companies.active_field_id = 3 OR companies.active_field_id = 5) AND companies.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN companies.id END)) AS djs_a_group_clients,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 1 AND vessels.deleted_at IS NULL AND (vessels.active_field_id=3 OR vessels.active_field_id=5) AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_a_client_vessels,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 2 AND vessels.deleted_at IS NULL AND (vessels.active_field_id=3 OR vessels.active_field_id=5) AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_a_vessel_vessels,
            COUNT(DISTINCT(CASE WHEN billing_information.billing_mode_id = 3 AND vessels.deleted_at IS NULL AND (vessels.active_field_id=3 OR vessels.active_field_id=5) AND vessels.deleted_at IS NULL 
            AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0) 
            THEN vessels.id END)) AS djs_a_group_vessels,
            (" . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 1
                    AND (vessels.active_field_id=3 OR vessels.active_field_id=5)
                    AND (billing_information.tank_annual_retainer_fee > 0 OR billing_information.non_tank_annual_retainer_fee > 0)
            GROUP BY companies.id, billing_information.last_billed_date, billing_information.is_discountable, billing_information.tank_contract_no,
                billing_information.tank_billing_start_date, billing_information.non_tank_contract_no, billing_information.non_tank_billing_start_date,
                billing_information.manual_tank_discount, billing_information.manual_non_tank_discount) AS group_amount) AS djs_a_client_amount,
            (" . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 2
                    AND (vessels.active_field_id=3 OR vessels.active_field_id=5)
            GROUP BY companies.id, vessels.tanker, billing_information.is_discountable) AS group_amount) AS djs_a_vessel_amount,
            ( " . BillingInformation::TOTAL_AMOUNT . "
                FROM vessels 
                JOIN companies ON vessels.company_id = companies.id
                JOIN billing_information ON companies.id = billing_information.company_id
                JOIN vessel_billing_group ON vessels.vessel_billing_group_id = vessel_billing_group.id
                WHERE billing_information.not_billed = 0
                    AND billing_information.deactivated IS NULL
                    AND vessels.deleted_at IS NULL
                    AND billing_information.billing_mode_id = 3
                    AND (vessels.active_field_id=3 OR vessels.active_field_id=5)
            GROUP BY companies.id, vessels.tanker, billing_information.is_discountable) AS group_amount) AS djs_a_group_amount,
            (SELECT 
                COUNT(companies.id)
                FROM
                    companies
                        LEFT JOIN
                    billing_information ON companies.id = billing_information.company_id
                        JOIN
                    active_fields ON companies.active_field_id = active_fields.id
                WHERE
                    billing_information.id IS NOT NULL
                    AND (companies.active_field_id = 2
                    OR companies.active_field_id = 3
                    OR companies.active_field_id = 5)
                    AND ((billing_information.tank_annual_retainer_fee = 0 OR billing_information.tank_annual_retainer_fee IS NULL)
                    AND (billing_information.non_tank_annual_retainer_fee = 0 OR billing_information.non_tank_annual_retainer_fee IS NULL))
                    AND companies.deleted_at IS NULL) 
            AS no_retainer_fee,
            (SELECT 
                count(distinct companies.id)
            FROM
                companies
                    LEFT JOIN
                billing_information ON companies.id = billing_information.company_id
            WHERE
                billing_information.id IS NULL
                    AND (companies.active_field_id = 2
                    OR companies.active_field_id = 3
                    OR companies.active_field_id = 5)
                    AND companies.deleted_at IS NULL) 
            AS no_billing_entry
        FROM
            vessels
                JOIN
            companies ON vessels.company_id = companies.id
                JOIN
            billing_information ON companies.id = billing_information.company_id
        WHERE
            billing_information.not_billed = 0;"));
    }

    public function getBillingModes()
    {
        return BillingMode::all();
    }

    public function getCompaniesWithZeroRetainerFee(Request $request)
    {
        $companies = Company::from('companies as c')
                            ->select('c.id as id', 'c.name as company_name', 'af.meaning as active_status', 'bi.tank_contract_no', 'bi.non_tank_contract_no')
                            ->leftjoin('billing_information as bi', 'bi.company_id', '=', 'c.id')
                            ->join('active_fields as af', 'af.id', '=', 'c.active_field_id')
                            ->where('c.deleted_at', NULL)
                            ->whereNotNull('bi.id')
                            ->where(function($q) {
                                $q->where('bi.tank_annual_retainer_fee', 0)
                                  ->orWhere('bi.tank_annual_retainer_fee', NULL);
                            })
                            ->where(function($q) {
                                $q->where('bi.non_tank_annual_retainer_fee', 0)
                                  ->orWhere('bi.non_tank_annual_retainer_fee', NULL);
                            });

        if(request('active_field_id') == -1) {
            $companies = $companies->whereIn('c.active_field_id', [2,3,5]);
        } else {
            $companies = $companies->where('c.active_field_id', request('active_field_id'));
        }

        $query = request('query');
        if (!empty($query) && strlen($query) > 2) {
            if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', $query, $specialChar)) {
                $strings = explode($specialChar[0], $query);
                $uids = Company::where([['name', 'like', '%' . $strings[0] . '%'], ['name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
            } else {
                $uids = Company::search($query)->get('id')->pluck('id');
            }

            $companies = $companies->whereIn('c.id', $uids);
        } 

        $per_page = request('per_page') == -1  ? count($companies->get()) : request('per_page');

        return $companies->paginate($per_page);
    }

    public function getCompaniesWithoutBillingEntries(Request $request)
    {
        $companies = Company::from('companies as c')
                            ->select('c.id as id', 'c.name as company_name', 'af.meaning as active_status', 'bi.tank_contract_no', 'bi.non_tank_contract_no')
                            ->leftjoin('billing_information as bi', 'bi.company_id', '=', 'c.id')
                            ->join('active_fields as af', 'af.id', '=', 'c.active_field_id')
                            ->where('c.deleted_at', NULL)
                            ->whereNull('bi.id');

        if(request('active_field_id') == -1) {
            $companies = $companies->whereIn('c.active_field_id', [2,3,5]);
        } else {
            $companies = $companies->where('c.active_field_id', request('active_field_id'));
        }

        $query = request('query');
        if (!empty($query) && strlen($query) > 2) {
            if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', $query, $specialChar)) {
                $strings = explode($specialChar[0], $query);
                $uids = Company::where([['name', 'like', '%' . $strings[0] . '%'], ['name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
            } else {
                $uids = Company::search($query)->get('id')->pluck('id');
            }

            $companies = $companies->whereIn('c.id', $uids);
        } 

        $per_page = request('per_page') == -1  ? count($companies->get()) : request('per_page');

        return $companies->paginate($per_page);
    }
}
