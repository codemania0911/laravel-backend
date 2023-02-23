<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Vessel;

class AccountBillingInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->company_id,
            'active' => $this->active,
            'name' => $this->company->name,
            'tank_billing_start_date' => $this->tank_billing_start_date,
            'non_tank_billing_start_date' => $this->non_tank_billing_start_date,
            'last_billed_date' => $this->last_billed_date,
            'is_discountable' => $this->is_discountable,
            // 'total_number_of_vessels' => $this->total_number_of_vessels,
            'tank_contract_no' => $this->tank_contract_no,
            'tank_vessel_count' => $this->is_discountable ? $this->vesselCount(1, $this->company_id) : 0,
            'non_tank_vessel_count' => $this->is_discountable ? $this->vesselCount(0, $this->company_id) : 0,
            'gross_tank_fee' => $this->tank_annual_retainer_fee,
            'gross_tank_total' => $this->vesselCount(0, $this->company_id) * $this->tank_annual_retainer_fee,
            'tank_auto_discount' => $this->is_discountable ? $this->autoDiscount(1, $this->vesselCount(1, $this->company_id)) : 0,
            'manual_tank_discount' => $this->manual_tank_discount,
            // 'tank_discount_value' => ,
            // 'tank_net_total' => ,
            'non_tank_contract_no' => $this->non_tank_contract_no,
            'non_tank_billing_start_date' => $this->non_tank_billing_start_date,
            // 'non_tank_vessel_count' => ,
            'gross_non_tank_fee' => $this->non_tank_annual_retainer_fee,
            // 'gross_non_tank_total' => ,
            'non_tank_auto_discount' => $this->is_discountable ? $this->autoDiscount(0, $this->vesselCount(1, $this->company_id)) : 0,
            'manual_non_tank_discount' => $this->manual_non_tank_discount,
            // 'non_tank_discount_value' => ,
            // 'non_tank_net_total' => ,
        ];
    }

    private function vesselCount($tank, $companyId)
    {
        return Vessel::from('vessels as v')
                        ->leftJoin('companies as c', 'c.id', '=', 'v.company_id')
                        ->leftJoin('billing_information as bi', 'bi.company_id', '=', 'v.company_id')
                        ->where([['v.company_id', $companyId], ['v.tanker', $tank]])
                        ->whereIn('v.active_field_id', [2, 3])
                        ->whereNull('v.deleted_at')
                        ->count();
    }

    private function autoDiscount($tank, $vesselCount)
    {
        if(!$vesselCount) {
            return 0;
        } else if($vesselCount > 50) {
            return $tank ? DiscountTank::where('min_extreme', '>', 50)->first()->discount : 
                        DiscountNonTank::where('min_extreme', '>', 50)->first()->discount;
        } else {
            return $tank ? DiscountTank::where([['min_extreme', '<', $vesselCount], ['max_extreme', '>', $vesselCount]])->first()->discount : 
                    DiscountNonTank::where([['min_extreme', '<', $vesselCount], ['max_extreme', '>', $vesselCount]])->first()->discount;
        }
    }
}
