<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Models\Company;
use App\Models\Region;
use App\Models\Country;

class AccountManagerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $user = User::where('id', $this->user_id)->first();
        $regionIds = $this->regions()->get()->pluck('region_id');
        $countryIds = $this->countries()->get()->pluck('country_id');
        $companyIds = $this->companies()->get()->pluck('company_id');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'account_name' => $user->first_name . ' ' . $user->last_name,
            'company_id' => Company::where('id', $user->primary_company_id)->first()->id,
            'company_name' => Company::where('id', $user->primary_company_id)->first()->name,
            'region_codes' => Region::whereIn('id', $regionIds)->get()->pluck('code'),
            'country_codes' => Country::whereIn('id', $countryIds)->get()->pluck('code'),
            'companies' => Company::whereIn('id', $companyIds)->select('id', 'name')->get()
        ];
    }
}
