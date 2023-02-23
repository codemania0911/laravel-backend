<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VesselShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'imo' => $this->imo,
            'official_number' => $this->official_number,
            'mmsi' => $this->mmsi,
            'plan_number' => isset($this->plan) ? $this->plan->plan_number : NULL,
            'vessel_type_id' => $this->vessel_type_id,
            'dead_weight' => $this->dead_weight,
            'tanker' => $this->tanker,
            'deck_area' => $this->deck_area,
            'has_photo' => (bool) $this->has_photo,
            'oil_tank_volume' => $this->oil_tank_volume,
            'oil_group' => $this->oil_group,
            'company_id' => $this->company_id,
            'company_has_photo' => (bool) $this->company_has_photo,
            'operating_company_id' => $this->company->operating_company_id,
            'primary_poc_id' => $this->primary_poc_id,
            'secondary_poc_id' => $this->secondary_poc_id,
            'sat_phone_primary' => $this->sat_phone_primary,
            'sat_phone_secondary' => $this->sat_phone_secondary,
            'email_primary' => $this->email_primary,
            'email_secondary' => $this->email_secondary,
            'active_field_id' => $this->active_field_id,
            'vrp_import' => $this->vrp_import,
            'djs_active' => $this->djs_active,
            'networks_active' => $this->networks_active,
            'capabilies_active' => $this->capabilies_active,
            'vrp_primary_smff' => $this->vrp_primary_smff,
            'zone_name' => $this->zone->name,
            'ex_name' => $this->ex_name,
            'sister_ship' => $this->sister_ship,
            // ais fetched photo
            'ais_photo_url' => $this->ais_photo_url,
            'ais_timestamp' => $this->ais_timestamp,
            'fleet_id' => ($this->fleets()->where('vessel_id',$this->id)->first())?$this->fleets()->where('vessel_id',$this->id)->first()->pivot->fleet_id:'',
            // 'test' => $this->vendors,
            'qi' => $this->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'QI and Plan Preparer');
            })->pluck('id'),
            'pi' => $this->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'P&I Club');
            })->pluck('id'),
            'societies' => $this->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'Society');
            })->pluck('id'),
            'insurers' => $this->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'H&M Insurer');
            })->pluck('id'),
            'providers' => $this->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'Damage Stability Certificate Provider');
            })->pluck('id'),
            'plan' => isset($this->plan) ? [
                'id' => $this->plan->id,
                'company_id' => $this->plan->company_id,
                'plan_number' => $this->plan->plan_number,
                'plan_holder_name' => $this->plan->plan_holder_name,
                'active_field_id' => $this->plan->active_field_id,
                'qi_id' => $this->plan->qi_id,
                'plan_preparer_id' => $this->plan->plan_preparer_id,
            ] : [],
            'plan_preparer' => isset($this->plan) && $this->plan->plan_preparer_id ? $this->plan->planPreparer : NULL,
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'active_field_id' => $this->company->active_field_id,
                'vendor_active' => $this->company->vendor_active,
                'networks_active' => $this->company->networks_active,
                'capabilies_active' => $this->company->smff_service_id ? 1 : 0,
            ],
            'vendor' => $this->company->type,
            'wcd' => $this->wcd,
            'construction_built' => $this->construction_built,
            'gross_tonnage' => $this->gross_tonnage
        ];
    }
}
