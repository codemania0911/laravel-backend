<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Cdt_Ardent\User as ArdentUser;
use App\Models\Cdt_Ardent\Addresses as ArdentUserAddress;
use App\Models\Cdt_Ardent\Entry as ArdentEntry;
use App\Models\UserAddress;

class ImportArdentUserAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-useraddress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cdt_ardent addresses table data to cdt user_address one.';

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
        $allUsers = User::where('old_user_id', '!=', '""')->get();
        $i = 1;
        foreach($allUsers as $user)
        {
            $addressData = ArdentUser::where('id', $user->old_user_id)->first()->address()->first();
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

            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
