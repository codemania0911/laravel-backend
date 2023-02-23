<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Vendor;
use App\Models\Vrp\VrpPlan;
use App\Models\Vessel;
use App\Models\Vrp\Vessel as VrpVessel;
use Illuminate\Http\Resources\Json\JsonResource;

class VesselListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $plan = VrpPlan::find($this->plan_number);

        $vessel_tanker = $this->tanker;
        $linked = $this->linked;

        $vrp = null;

        if(!$this->vrp_import) {
            $vessel = Vessel::find($this->id);
            if (isset($vessel->plan) && $vessel->plan->plan_number) {
                if($this->imo) {
                    $vrp = VrpVessel::join('vrp_plan', 'plan_number_id', '=', 'vrp_plan.id')
                    ->where('vrp_plan.plan_number', $vessel->plan->plan_number)
                    ->where('imo', $this->imo)
                    ->first();
                }
                elseif ($this->official_number) {
                    $vrp = VrpVessel::join('vrp_plan', 'plan_number_id', '=', 'vrp_plan.id')
                    ->where('vrp_plan.plan_number', $vessel->plan->plan_number)
                    ->where('official_number', $this->official_number)
                    ->first();
                }

                if(isset($vrp)) {
                    $vessel_status = $vrp->vessel_status;
                    if($vessel_status == 'Authorized') {
                        $linked = 1;
                    } else {
                        $linked = 0;
                    }
                } else {
                    $vessel_status = null;
                    $linked = 2;
                }
            } else {
                $vessel_status = null;
                $linked = 3;
            }

        } else {
            $vessel_status = $this->vrp_status;
            if($vessel_status == 'Authorized') {
                $linked = 1;
            } else {
                $linked = 0;
            }
            if($this->imo) {
                $vrp_vessel_tank = VrpVessel::where('imo', $this->imo)->first();
                $vessel_tanker = $vrp_vessel_tank->vessel_is_tank;
            }
            else if($this->official_number) {
                $vrp_vessel_tank = VrpVessel::where('official_number', $this->official_numbe)->first();
                if ($vrp_vessel_tank->vessel_is_tank == 'TANK (Primary)' || $vrp_vessel_tank->vessel_is_tank == 'TANK (Primary)/SMPEP' || $vrp_vessel_tank->vessel_is_tank == 'TANK/SOPEP' || $vrp_vessel_tank->vessel_is_tank == 'TANK (Secondary)' || $vrp_vessel_tank->vessel_is_tank == 'TANK (Secondary)/SOPEP') {
                    $vessel_tanker = 1;
                } else {
                    $vessel_tanker = 0;
                }
            }
        }

        return [
            'id' => $this->id > 0 ? $this->id : $this->vrpid,
            'imo' => $this->imo,
            'official_number' => $this->official_number,
            'vrp_count' => $this->vrp_count ?? 'N/A',
            'vrp_status' => $vessel_status ? $vessel_status : 'Not Authorized',
            'vrp_comparison' => $this->vrp_comparison ?? 'N/A',
            'vrp_plan_number' => $this->plan_number,
            'vrp_vessel_is_tank' => $this->vrp_vessel_is_tank,
            'plan' => $this->plan_id ? [
                'id' => $this->plan_id,
                'plan_number' => $this->plan_number,
            ] : [],
            'name' => $this->name,
            'type' => $this->type,
            'company' => $this->company_id ? [
                'id' => $this->company_id,
                'name' => Company::whereId($this->company_id)->first()->name,
                'active_field_id' => Company::whereId($this->company_id)->first()->active_field_id,
            ] : [],
            'tanker' => (boolean)$vessel_tanker,
            'resource_provider' => (boolean)$this->resource_provider,
            'active_field_id' => $this->active_field_id,
            'vrp_import' => $this->vrp_import,
            'djs_active' => $this->djs_active,
            'networks_active' => $this->networks_active,
            'capabilies_active' => $this->capabilies_active,
            'vrp_primary_smff' => $this->vrp_primary_smff ? $this->vrp_primary_smff : null,
            'linked' => $linked,
            'vessel_type_id' => $this->vessel_type_id,
            'dead_weight' => $this->dead_weight,
            'deck_area' => $this->deck_area,
            'oil_tank_volume' => $this->oil_tank_volume,
            'oil_group' => $this->oil_group,
            'wcd' => $this->wcd,
            'ex_name' => $this->ex_name,
            'gross_tonnage' => $this->gross_tonnage,
            'construction_built' => $this->construction_built,
             'qi' => $this->vendors()->whereHas('type', function ($q) {
                 $q->where('id', Vendor::TYPE_QI);
             })->pluck('id'),
             'pi' => $this->vendors()->whereHas('type', function ($q) {
                 $q->where('id', Vendor::TYPE_PANDI);
             })->pluck('id'),
             'societies' => $this->vendors()->whereHas('type', function ($q) {
                 $q->where('id', Vendor::TYPE_SOCIETY);
             })->pluck('id'),
             'insurers' => $this->vendors()->whereHas('type', function ($q) {
                 $q->where('id', Vendor::TYPE_HANDM);
             })->pluck('id'),
             'providers' => $this->vendors()->whereHas('type', function ($q) {
                 $q->where('id', Vendor::TYPE_DAMAGE);
             })->pluck('id')
        ];
    }

}
