<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cdt_Ardent\UserCompany as ArdentUserCompany;
use App\Models\Company;
use App\Models\User;
use App\Models\CompanyUser;

class ImportUserCompany extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-user-company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'The Ardent user_company ids match with new user and company id.';

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
        $allData = ArdentUserCompany::all();
        $i = 1;
        foreach($allData as $data)
        {
            $company = Company::where('old_company_id', $data->company_id)->first();
            $user = User::where('old_user_id', $data->user_id)->first();
            if($user->primary_company_id) {
                CompanyUser::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ]);
            } else {
                User::where('old_user_id', $data->user_id)
                    ->update([
                        'primary_company_id' => $company->id,
                    ]);

                CompanyUser::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ]);
            }

            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
