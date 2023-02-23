<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Cdt_Ardent\UserCompany as ArdentUserCompany;
use App\Models\Cdt_Ardent\Company as ArdentCompany;
use App\Models\Cdt_Ardent\User as ArdentUser;
use Illuminate\Support\Facades\Hash;

class UpdateArdentUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:ardent-user-role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $aa = Hash::make('Password#2021');
        $users = User::whereNotNull('old_user_id')->get();

        $i = 1;
        foreach($users as $user)
        {
            if(ArdentUser::where('id', $user->old_user_id)->first()->enabled) {
                $companyIds = ArdentUserCompany::where('user_id', $user->old_user_id)->get()->pluck('company_id');

                foreach($companyIds as $companyId)
                {
                    $company = ArdentCompany::where('id', $companyId)->first();
                    if($company->billing_mode == 'client') {
                        $user->role_id = 7;
                        $user->save();
                        echo ('added: ' . $i);
                        echo PHP_EOL;
                        $i++;
                        break;
                    }
                }
            }
        }
    }
}
