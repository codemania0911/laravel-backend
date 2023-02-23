<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use App\Models\Cdt_Ardent\CompanyContact as ArdentCompanyContact;
use App\Models\Cdt_Ardent\Contact as ArdentContact;
use App\Models\Cdt_Ardent\Addresses as ArdentUserAddress;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;
use App\Models\UserAddress;
use App\Models\CompanyUser;

class ImportArdentCompanyContact extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:company-contact';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Company contact to Cdt users table without role.';

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
        $contacts = ArdentCompanyContact::all();
        $i = 1;

        foreach($contacts as $contact)
        {
            $contactInfo = ArdentContact::whereId($contact->contact_id)->first();
            $companyId = Company::where('old_company_id', $contact->company_id)->first()->id;
            $user = new User();
            $user->first_name = $contactInfo->firstname;
            $user->last_name = $contactInfo->lastname;
            $user->work_phone = $contactInfo->phone;
            $user->aoh_phone = $contactInfo->emergencyphone;
            $user->email = $contactInfo->email;
            $user->occupation = $contactInfo->title;
            $user->primary_company_id = $companyId;
            $user->save();

            CompanyUser::create([
                'user_id' => $user->id,
                'company_id' => $companyId
            ]);

            $addressData = ArdentUserAddress::where('id', $contactInfo->address_id)->first();
            if($addressData) {
                if($addressData->country_entry_id) {
                    $country = ArdentEntry::where('id', $addressData->country_entry_id)->first()->entry;
                } else {
                    $country = '';
                }
    
                UserAddress::create([
                    'user_id' => $user->id,
                    'street' => $addressData->address,
                    'unit' => $addressData->addresss_2,
                    'state' => $addressData->state,
                    'zip' => $addressData->postalcode,
                    'city' => $addressData->city,
                    'country' => $country
                ]);
    
            }
            
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
