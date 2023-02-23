<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Vrp\VrpCountryMapping;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\VendorType;
use App\Models\VesselVendor;
use App\Models\Plan;
use App\Models\Vrp\VrpPlan;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $vrpVesselCount = Vessel::where('company_id', $this->id)->whereNotNull('plan_id')->count();
        $nonVrpVesselCount = Vessel::where('company_id', $this->id)->whereNull('plan_id')->count();

        $vendor_type = Company::where('id', $this->id)->first()->vendor_type;

        count($this->primaryAddress) ? 
            $country = $this->primaryAddress[0]->country : 
            $country = '';
        

        if(!empty($request->staticSearch['parent']) && $request->staticSearch['parent']) {
            if($request->staticSearch['parent'] == $this->id) {
                $operatingCompanyID = Company::where('id', $this->id)->first()->operating_company_id;
                if($operatingCompanyID) {
                    $operatingCompany = Company::where('id', $operatingCompanyID)->first();
                    $vendor_type = Company::where('id', $operatingCompany->id)->first()->vendor_type;

                    $vrpVesselCount = Vessel::where('company_id', $operatingCompany->id)->whereNotNull('plan_id')->count();
                    $nonVrpVesselCount = Vessel::where('company_id', $operatingCompany->id)->whereNull('plan_id')->count();

                    count($operatingCompany->primaryAddress) ? 
                        $country = $operatingCompany->primaryAddress[0]->country : 
                        $country = '';

                    return [
                        'id' => $operatingCompany->id,
                        'name' => $operatingCompany->name,
                        'email' => $operatingCompany->email,
                        'fax' => $operatingCompany->fax,
                        'phone' => $operatingCompany->phone,
                        'plan_count' => Plan::where('company_id', $operatingCompany->id)->whereNull('deleted_at')->count(),
                        'resource_provider' => $operatingCompany->smffCapability ? true : false,
                        'active_field_id' => $operatingCompany->active_field_id,
                        'location' => count($operatingCompany->primaryAddress) ? codeToCountryToCode($operatingCompany->primaryAddress[0]->country) : '',
                        'country'  => $country,
                        'stats' => [
                            'individuals' => count($operatingCompany->users),
                            'vessels' => $nonVrpVesselCount,
                            'contacts' => count($operatingCompany->contacts)
                        ],
                        'vrp_stats' => [
                            'vessels' => $vrpVesselCount
                        ],
                        'networks_active' => $operatingCompany->networks_active,
                        'vendor_active' => $operatingCompany->vendor_active,
                        'vendor_type' => $vendor_type ? VendorType::where('id', $vendor_type)->first() : [],
                        'capabilies_active' => $operatingCompany->capabilies_active,
                    ];
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'fax' => $this->fax,
            'phone' => $this->phone,
            'plan_count' => Plan::where('company_id', $this->id)->whereNull('deleted_at')->count(),
            'resource_provider' => $this->smffCapability ? true : false,
            'active_field_id' => $this->active_field_id,
            'location' => count($this->primaryAddress) ? codeToCountryToCode($this->primaryAddress[0]->country) : '',
            'country'  => $country,
            'stats' => [
                'individuals' => count($this->users),
                'vessels' => $nonVrpVesselCount,
                'contacts' => count($this->contacts)
            ],
            'vrp_stats' => [
                'vessels' => $vrpVesselCount
            ],
            'networks_active' => $this->networks_active,
            'vendor_active' => (int)$this->vendor_active,
            'vendor_type' => $vendor_type ? VendorType::where('id', $vendor_type)->first() : [],
            'capabilies_active' => $this->capabilies_active,
        ];
    }
}
