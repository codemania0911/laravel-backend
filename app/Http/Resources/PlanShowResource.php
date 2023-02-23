<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Company;
use App\Models\NetworkCompanies;

class PlanShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $company =  Company::whereId($this->company_id)->first();
        $networkCompanies = NetworkCompanies::where([['network_id', 1], ['company_id', $this->company_id], ['active', 1]])->first();
        if(isset($networkCompanies)) {
            if(isset($networkCompanies->contracted_company_id) && $networkCompanies->contracted_company_id !== 0) {
                $unique_identification_number_djs = NetworkCompanies::where([['network_id', 1], ['company_id', $networkCompanies->contracted_company_id]])->first()->unique_identification_number_djs;
                $unique_identification_number_ardent = NetworkCompanies::where([['network_id', 1], ['company_id', $networkCompanies->contracted_company_id]])->first()->unique_identification_number_ardent;
            } else {
                $unique_identification_number_djs = $networkCompanies->unique_identification_number_djs;
                $unique_identification_number_ardent = $networkCompanies->unique_identification_number_ardent;
            }
        } else {
            $unique_identification_number_djs = '';
            $unique_identification_number_ardent = '';
        }

        return [
            'id' => $this->id,
            'plan_holder_name' => $this->plan_holder_name,
            'plan_number' => $this->plan_number,
            'email' => $this->email,
            'fax' => $this->fax,
            'phone' => $this->phone,
            'website' => $this->website,
            'description' => $this->description,
            'active_field_id' => $this->active_field_id,
            'qi_id' => $this->qi_id,
            'operating_company_id' => $this->operating_company_id,
            'networks_active' => $this->networks_active,
            'vendor_active' => $this->vendor_active,
            'capabilies_active' => $this->capabilies_active,
            'vendor_type' => $company->type ? $company->type->name : null,
            'vendor_type_id' => $company->type ? $company->type->id : null,
            'shortname' =>  $this->shortname,
            'company_poc_id' => $this->company_poc_id,
            'zone_name' => $this->primaryAddress->first() ? $this->primaryAddress->first()->zone->name : null,
            'plan_preparer_id' => $this->plan_preparer_id,
            'contracted_company_id' => isset($networkCompanies) ? $networkCompanies->contracted_company_id : 0,
            'unique_identification_number_djs' => $unique_identification_number_djs,
            'unique_identification_number_ardent' => $unique_identification_number_ardent,
            'exist_opa_company' => isset($networkCompanies) ? 1 : 0,
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'active_field_id' => $this->company->active_field_id,
                'vendor_active' => $this->company->vendor_active,
                'has_photo' => (bool) $this->company->has_photo,
                'networks_active' => $this->company->networks_active,
                'capabilies_active' => $this->company->smff_service_id ? 1 : 0,
            ],
        ];
    }
}
