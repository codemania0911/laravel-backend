<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Cdt_Ardent\Company as ArdentCompany;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;
use App\Models\Cdt_Ardent\QiServedCompany as ArdentQiServedCompany;

class ImportArdentCompany extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-companies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cdt_ardent companies table data to cdt companies one.';

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
        $allCompanies = ArdentCompany::all();
        $i = 1;
        foreach($allCompanies as $company)
        {
            $cdtCompany = new Company();
            $cdtCompany->name = $company->name;
            $cdtCompany->phone = $company->phone;
            $cdtCompany->aoh_phone = $company->emergencyphone;
            $cdtCompany->email = $company->email;
            $cdtCompany->fax = $company->fax;
            $cdtCompany->website = $company->website;
            $cdtCompany->deleted_at = $company->deletedAt;
            $cdtCompany->old_company_id = $company->id;
            $cdtCompany->active = $company->deactivated ? 3 : 2;

            if($cdtCompany->save()) {
                $addressData = $company->address()->first();
                if($addressData->country_entry_id) {
                    $country = ArdentEntry::where('id', $addressData->country_entry_id)->first()->abbrev;
                } else {
                    $country = '';
                }

                $address = $cdtCompany->addresses()->create([
                    'address_type_id' => 3,
                    'company_id' => $cdtCompany->id,
                    'street' => $addressData->address,
                    'unit' => $addressData->address_2,
                    'state' => $addressData->state,
                    'zip' => $addressData->postalcode,
                    'city' => $addressData->city,
                    'country' => $country
                ]);

                $accounting = $cdtCompany->accounting()->create([
                    'company_id' => $cdtCompany->id,
                    'last_billed_date' => $company->last_billed_date,
                    'not_billed' => $company->not_billed,
                    'billing_mode' => $company->billing_mode,
                    'deactivated' => $company->deactivated,
                    'deactivation_reason' => $company->deactivation_reason,
                    'is_discountable' => $company->is_discountable,
                    'account_manager_id' => $company->account_manager_id
                ]);

                if($company->notes) {
                    $note = $cdtCompany->notes()->create([
                        'note' => $company->notes,
                        'note_type' => 1,
                        'user_id' => 4794,
                        'company_id' => $cdtCompany->id,
                    ]);
                }

                echo ('added: ' . $i);
                echo PHP_EOL;
                $i++;
            }
        }
    }
}
