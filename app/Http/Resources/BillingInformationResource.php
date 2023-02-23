<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\DiscountNonTank;
use App\Models\DiscountTank;
use Illuminate\Support\Facades\DB;
use App\Models\BillingInformation;

class BillingInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $companyBillingInformation = $this->companyBillingInformation($this->company_id);

        if($this->tank_automatic_billing_start_date) {
            if($this->tank_free_years) {
                $tank_billing_start_date = date("Y-m-d", strtotime(date("Y-m-d", strtotime($this->tank_contract_signed_date)) . " + " . $this->tank_free_years . " year"));
            } else {
                $tank_billing_start_date = $this->tank_contract_signed_date;
            }
        } else {
            $tank_billing_start_date = $this->tank_billing_start_date;
        }

        if($this->non_tank_automatic_billing_start_date) {
            if($this->non_tank_free_years) {
                $non_tank_billing_start_date = date("Y-m-d", strtotime(date("Y-m-d", strtotime($this->non_tank_contract_signed_date)) . " + " . $this->non_tank_free_years . " year"));
            } else {
                $non_tank_billing_start_date = $this->non_tank_contract_signed_date;
            }
        } else {
            $non_tank_billing_start_date = $this->non_tank_billing_start_date;
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'address_id' => $this->address_id,
            'last_billed_date' => $this->last_billed_date,
            'not_billed' => $this->not_billed,
            'billing_mode_id' => $this->billing_mode_id,
            'deactivated' => $this->deactivated,
            'deactivation_reason' => $this->deactivation_reason,
            'is_discountable' => $this->is_discountable,
            'deleted_at' => $this->deleted_at,
            'tank_automatic_billing_start_date' => $this->tank_automatic_billing_start_date,
            'tank_contract_no' => $this->tank_contract_no,
            'tank_expiration_date' => $this->tank_expiration_date,
            'tank_cancellation_date' => $this->tank_cancellation_date,
            'tank_contract_signed_date' => $this->tank_contract_signed_date,
            'tank_contract_length' => $this->tank_contract_length,
            'tank_free_years' => $this->tank_free_years,
            'tank_annual_retainer_fee' => $this->tank_annual_retainer_fee,
            'tank_billing_start_date' => $tank_billing_start_date,
            'tank_billing_note' => $this->tank_billing_note,
            'tank_recurring' => $this->tank_recurring,
            'manual_tank_discount' => $this->manual_tank_discount,
            'non_tank_automatic_billing_start_date' => $this->non_tank_automatic_billing_start_date,
            'non_tank_contract_no' => $this->non_tank_contract_no,
            'non_tank_expiration_date' => $this->non_tank_expiration_date,
            'non_tank_contract_signed_date' => $this->non_tank_contract_signed_date,
            'non_tank_contract_length' => $this->non_tank_contract_length,
            'non_tank_free_years' => $this->non_tank_free_years,
            'non_tank_annual_retainer_fee' => $this->non_tank_annual_retainer_fee,
            'non_tank_billing_start_date' => $non_tank_billing_start_date,
            'non_tank_billing_note' => $this->non_tank_billing_note,
            'non_tank_recurring' => $this->non_tank_recurring,
            'manual_non_tank_discount' => $this->manual_non_tank_discount,
            'number_of_tank' => $companyBillingInformation['number_of_tank'],
            'number_of_non_tank' => $companyBillingInformation['number_of_non_tank'],
            'tank_auto_discount' => $this->is_discountable ? $this->autoDiscount(1, $this->vesselCount(1, $this->company_id)) : 0,
            'non_tank_auto_discount' => $this->is_discountable ? $this->autoDiscount(0, $this->vesselCount(0, $this->company_id)) : 0,
            'gross_tank_total' => $companyBillingInformation['gross_tank_total'],
            // 'tank_discount_value' => $companyBillingInformation['tank_discount_value'],
            'tank_discount_value' => $this->manual_tank_discount ? $companyBillingInformation['gross_tank_total'] * ($this->manual_tank_discount / 100) : ($companyBillingInformation['gross_tank_total'] * ($this->autoDiscount(1, $this->vesselCount(1, $this->company_id)))) / 100,
            // 'tank_net_total' => $companyBillingInformation['tank_net_total'],
            'tank_net_total' => !$this->is_discountable ? $companyBillingInformation['gross_tank_total'] : $this->manual_tank_discount ? $companyBillingInformation['gross_tank_total'] * ((100 - $this->manual_tank_discount) / 100) : ($companyBillingInformation['gross_tank_total'] * (100 - ($this->autoDiscount(1, $this->vesselCount(1, $this->company_id)))))/ 100,
            'gross_non_tank_total' => $companyBillingInformation['gross_non_tank_total'],
            // 'non_tank_discount_value' => $companyBillingInformation['non_tank_discount_value'],
            'non_tank_discount_value' => $this->manual_non_tank_discount ? $companyBillingInformation['gross_non_tank_total'] * ($this->manual_non_tank_discount / 100) : ($companyBillingInformation['gross_non_tank_total'] * ($this->autoDiscount(0, $this->vesselCount(0, $this->company_id))))/ 100,
            // 'non_tank_net_total' => $companyBillingInformation['non_tank_net_total']
            'non_tank_net_total' => !$this->is_discountable ? $companyBillingInformation['gross_non_tank_total'] : $this->manual_non_tank_discount ? $companyBillingInformation['gross_non_tank_total'] * ((100 - $this->manual_non_tank_discount) / 100) : ($companyBillingInformation['gross_non_tank_total'] * (100 - ($this->autoDiscount(0, $this->vesselCount(0, $this->company_id)))))/ 100,
        ];
    }

    private function companyBillingInformation($companyId)
    {
        return $results = Vessel::from('vessels as v')
                    ->select(DB::raw(BillingInformation::DEFAULT))
                    ->join('companies as c', 'c.id', '=', 'v.company_id')
                    ->join('billing_information as bi', 'v.company_id', '=', 'bi.company_id')
                    ->where([['bi.not_billed', 0], ['bi.deactivated', NULL],
                            ['v.deleted_at', NULL], ['c.id', $companyId]])
                    ->where(function ($query) {
                        $query->where('bi.tank_annual_retainer_fee', '>', 0)
                                ->orWhere('bi.non_tank_annual_retainer_fee', '>', 0);
                    })
                    // ->where(function ($query) {
                    //     $query->where('bi.tank_billing_start_date', '<', date("Y-m-d"))
                    //             ->orWhere('bi.non_tank_billing_start_date', '<', date("Y-m-d"));
                    // })
                    ->whereIn('v.active_field_id', [2, 3, 5])
                    ->groupBy('c.id', 'bi.is_discountable', 'bi.last_billed_date', 
                            'bi.tank_contract_no', 'bi.tank_billing_start_date', 'bi.non_tank_contract_no', 
                            'bi.non_tank_billing_start_date', 'bi.manual_tank_discount', 'bi.manual_non_tank_discount')
                    ->orderBy('c.name')
                    ->first();
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
        if($vesselCount < 5) {
            return 0;
        } else if($vesselCount > 50) {
            return $tank ? DiscountTank::where('min_extreme', '>=', 50)->first()->discount : 
                        DiscountNonTank::where('min_extreme', '>=', 50)->first()->discount;
        } else {
            return $tank ? DiscountTank::where([['min_extreme', '<=', $vesselCount], ['max_extreme', '>=', $vesselCount]])->first()->discount : 
                    DiscountNonTank::where([['min_extreme', '<=', $vesselCount], ['max_extreme', '>=', $vesselCount]])->first()->discount;
        }
    }
    
}
