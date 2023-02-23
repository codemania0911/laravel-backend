<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillingInformation;
use App\Models\Company;
use App\Models\Cdt_Ardent\Company as ArdentCompany;
use App\Models\CompanyAddress;
use App\Models\Cdt_Ardent\BillingInformation as ArdentBillingInformation;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;

class ImportArdentBillingInformation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-billing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Ardent Billing Information and Companies billing information to New billing_information table of CDT.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $billingInfos = ArdentBillingInformation::get();
        $i = 1;
        foreach($billingInfos as $billingInfo)
        {
            $company = Company::where('old_company_id', $billingInfo->company_id)->first();

            $existingBilling = BillingInformation::where('company_id', $company->id)->first();
            if($existingBilling) {
                if($billingInfo->vrptype_id == 287) {
                    $existingBilling->tank_automatic_billing_start_date = $billingInfo->automatic_billing_start_date;
                    $existingBilling->tank_contract_no = $billingInfo->contract_no;
                    $existingBilling->tank_expiration_date = $billingInfo->expiration_date;
                    $existingBilling->tank_cancellation_date = $billingInfo->cancellation_date;
                    $existingBilling->tank_contract_signed_date = $billingInfo->contract_signed_date;
                    $existingBilling->tank_contract_length = $billingInfo->contract_length;
                    $existingBilling->tank_free_years = $billingInfo->free_years;
                    $existingBilling->tank_annual_retainer_fee = $billingInfo->annual_retainer_fee ;
                    $existingBilling->tank_billing_start_date = $billingInfo->billing_start_date;
                    $existingBilling->tank_billing_note = $billingInfo->billing_note;
                    $existingBilling->tank_recurring = $billingInfo->recurring;
                } else {
                    $existingBilling->non_tank_automatic_billing_start_date = $billingInfo->automatic_billing_start_date;
                    $existingBilling->non_tank_contract_no = $billingInfo->contract_no;
                    $existingBilling->non_tank_expiration_date = $billingInfo->expiration_date;
                    $existingBilling->non_tank_cancellation_date = $billingInfo->cancellation_date;
                    $existingBilling->non_tank_contract_signed_date = $billingInfo->contract_signed_date;
                    $existingBilling->non_tank_contract_length = $billingInfo->contract_length;
                    $existingBilling->non_tank_free_years = $billingInfo->free_years;
                    $existingBilling->non_tank_annual_retainer_fee = $billingInfo->annual_retainer_fee ;
                    $existingBilling->non_tank_billing_start_date = $billingInfo->billing_start_date;
                    $existingBilling->non_tank_billing_note = $billingInfo->billing_note;
                    $existingBilling->non_tank_recurring = $billingInfo->recurring;
                }

                if($existingBilling->save()) {
                    echo ('updated: ' . $i);
                    echo PHP_EOL;
                    $i++;
                }   
            } else {
                // Import the billing address data to cdt
                $addressData = $billingInfo->address()->first();
                if($addressData) {
                    if($addressData->country_entry_id) {
                        $country = ArdentEntry::where('id', $addressData->country_entry_id)->first()->abbrev;
                    } else {
                        $country = '';
                    }
        
                    $ardentAddress = new CompanyAddress();
                    $ardentAddress->address_type_id = 1;
                    $ardentAddress->company_id = $company->id;
                    $ardentAddress->street = $addressData->address;
                    $ardentAddress->unit = $addressData->address_2;
                    $ardentAddress->state = $addressData->state;
                    $ardentAddress->zip = $addressData->zip;
                    $ardentAddress->city = $addressData->city;
                    $ardentAddress->country = $addressData->country;
                    $ardentAddress->save();
                }

                if($billingInfo->vrptype_id == 287) {
                    $cdtBillingInfo = new BillingInformation();
                    $cdtBillingInfo->company_id = $company->id;
                    $cdtBillingInfo->address_id = $addressData ? $ardentAddress->id : null;
                    $cdtBillingInfo->tank_automatic_billing_start_date = $billingInfo->automatic_billing_start_date;
                    $cdtBillingInfo->tank_contract_no = $billingInfo->contract_no;
                    $cdtBillingInfo->tank_expiration_date = $billingInfo->expiration_date;
                    $cdtBillingInfo->tank_cancellation_date = $billingInfo->cancellation_date;
                    $cdtBillingInfo->tank_contract_signed_date = $billingInfo->contract_signed_date;
                    $cdtBillingInfo->tank_contract_length = $billingInfo->contract_length;
                    $cdtBillingInfo->tank_free_years = $billingInfo->free_years;
                    $cdtBillingInfo->tank_annual_retainer_fee = $billingInfo->annual_retainer_fee ;
                    $cdtBillingInfo->tank_billing_start_date = $billingInfo->billing_start_date;
                    $cdtBillingInfo->tank_billing_note = $billingInfo->billing_note;
                    $cdtBillingInfo->tank_recurring = $billingInfo->recurring;
                } else {
                    $cdtBillingInfo = new BillingInformation();
                    $cdtBillingInfo->company_id = $company->id;
                    $cdtBillingInfo->address_id = $addressData ? $ardentAddress->id : null;
                    $cdtBillingInfo->non_tank_automatic_billing_start_date = $billingInfo->automatic_billing_start_date;
                    $cdtBillingInfo->non_tank_contract_no = $billingInfo->contract_no;
                    $cdtBillingInfo->non_tank_expiration_date = $billingInfo->expiration_date;
                    $cdtBillingInfo->non_tank_cancellation_date = $billingInfo->cancellation_date;
                    $cdtBillingInfo->non_tank_contract_signed_date = $billingInfo->contract_signed_date;
                    $cdtBillingInfo->non_tank_contract_length = $billingInfo->contract_length;
                    $cdtBillingInfo->non_tank_free_years = $billingInfo->free_years;
                    $cdtBillingInfo->non_tank_annual_retainer_fee = $billingInfo->annual_retainer_fee ;
                    $cdtBillingInfo->non_tank_billing_start_date = $billingInfo->billing_start_date;
                    $cdtBillingInfo->non_tank_billing_note = $billingInfo->billing_note;
                    $cdtBillingInfo->non_tank_recurring = $billingInfo->recurring;
                }

                // From Ardent Company table because of ardent company table included some billing information now.
                $ardentCompany = ArdentCompany::where('id', $billingInfo->company_id)->first();
                $cdtBillingInfo->last_billed_date = $ardentCompany->last_billed_date;
                $cdtBillingInfo->not_billed = $ardentCompany->not_billed;
                $cdtBillingInfo->billing_mode = $ardentCompany->billing_mode;
                $cdtBillingInfo->deactivated = $ardentCompany->deactivated;
                $cdtBillingInfo->deactivation_reason = $ardentCompany->deactivation_reason;
                $cdtBillingInfo->is_discountable = $ardentCompany->is_discountable; // Not sure for now.

                if($cdtBillingInfo->save()) {
                    echo ('added: ' . $i);
                    echo PHP_EOL;
                    $i++;
                }   
            }
        }
    }
}
