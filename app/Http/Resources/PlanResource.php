<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Vessel;
use App\Models\Vrp\VrpCountryMapping;
use App\Models\Vrp\Vessel as VrpVessel;
use App\Models\CompanyAddress;
use App\Models\Plan;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $country = '';
        $address = CompanyAddress::where([['company_id', $this->company_id], ['address_type_id', 3]])->first();
        if ($address) {
            $country = $address->country;
        } else if ($this->vrp_country && !empty($this->vrp_country)) {
            $countryMapping = VrpCountryMapping::where('vrp_country_name', $this->vrp_country)->first();
            if ($countryMapping) {
                $country = $countryMapping->code;
            }
        }

        return [
            'id' => $this->id,
            'vrpid' => $this->vrpid,
            'plan_holder_name' => $this->vrp_import ? $this->vrp_plan_holder_name : $this->holder_name,
            'plan_number' => $this->vrp_import ? $this->vrp_plan_number : $this->plan_number,
            'location' => count($this->primaryAddress) ? codeToCountryToCode($this->primaryAddress[0]->country) : '',
            'country' => $country ?? '',
            'vessel_count' => $this->id != -1 ? Vessel::where('plan_id', $this->id)->count() : 0,
            'vrp_vessel_count' => $this->vrpid ? VrpVessel::where('plan_number_id', $this->vrpid)->count() : 0,
            'vrp_import' => $this->vrp_import,
            'active_field_id' => $this->djs_active,
            'qi_id' => Plan::where('id', $this->id)->first() ? Plan::where('id', $this->id)->first()->qi_id : NULL,
            'plan_preparer_id' => Plan::whereid($this->id)->first() ? Plan::whereid($this->id)->first()->plan_preparer_id : NULL,
            'vrp_holder_name' => $this->vrp_plan_holder_name,
            'vrp_status' => $this->vrp_status,
            'vrp_plan_number' => $this->vrp_plan_number,
            'plan_exp_date' => $this->plan_exp_date,
            'vrp_primary_smff' => $this->vrp_primary_smff,
            'vendor_active' => $this->vendor_active,
            'capabilies_active' => $this->capabilies_active,
            'networks_active' => $this->networks_active
        ];
    }
}
