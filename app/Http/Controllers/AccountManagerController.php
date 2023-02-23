<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountManager;
use App\Models\AccountManagerRegion;
use App\Models\AccountManagerCompany;
use App\Models\AccountManagerCountry;
use App\Models\User;
use App\Models\Region;
use App\Models\Company;
use App\Models\Country;
use App\Models\CompanyAddress;
use App\Http\Resources\AccountManagerResource;
use App\Http\Resources\CompanyAccountManagerResource;

class AccountManagerController extends Controller
{
    //
    public function getAccountManagers(Request $request)
    {   
        $query = $request->get('query');

        $results = AccountManager::orderBy('updated_at', 'desc');

        if(!empty($query) && strlen($query) > 2) {
            $uids = User::search($query)->whereNull('deleted_at')->whereIn('role_id', [1,2])->get('id')->pluck('id');
            $results = $results->whereIn('user_id', $uids);
        }

        $perPage = request('per_page') == -1 ? count($results->get()) : (int)request('per_page');

        $results = AccountManagerResource::collection($results->paginate($perPage));

        return $results;
    }

    public function companyRelatedAccountManagers(Company $company, Request $request)
    {
        $companyAccountManagerIds = AccountManagerCompany::where('company_id', $company->id)->get()->pluck('account_manager_id');

        // Get Company Address country code
        $country = '';
        $address = CompanyAddress::where([['company_id', $company->id], ['address_type_id', 3]])->first();
        
        if ($address) {
            $country = $address->country;
        }

        // Country Account Managers
        $countryId = $country && Country::where('code', $country)->first() ? Country::where('code', $country)->first()->id : NULL;
        $countryAccountManagers = AccountManagerCountry::where('country_id', $countryId)->get();
        
        // Region Account Managers
        $region = $country && Country::where('code', $country)->first() ? Country::where('code', $country)->first()->region_code : NULL;
        $regionId = $region ? Region::where('code', $region)->first()->id : NULL;
        $regionAccountManagers = AccountManagerRegion::where('region_id', $regionId)->get();

        return response()->json(['company_account_managers' => $companyAccountManagerIds, 
                                 'country_account_managers' => $countryAccountManagers,
                                 'region_account_managers' => $regionAccountManagers
                                ], 200);
    }

    public function short()
    {
        return AccountManager::from('account_managers as am')
                                ->select('am.id as id', 'u.username as name')
                                ->leftJoin('users as u', 'u.id', '=', 'am.user_id')
                                ->get();
    }
}
