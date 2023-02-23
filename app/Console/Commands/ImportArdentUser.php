<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Cdt_Ardent\User as ArdentUser;
use Illuminate\Support\Facades\Hash;

class ImportArdentUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:ardent-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cdt_ardent users table data to cdt users one.';

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
        $aa = Hash::make('123456');
        $bb = Hash::make('Password#2021');
        $allUsers = ArdentUser::all();
        $i = 1;
        foreach($allUsers as $user)
        {
            User::create([
                'username' => $user->username,
                'active' => $user->enabled,
                'password' => Hash::make('Pass20word-djs'),
                // 7: Company Plan Manager
                'role_id' => 7,
                'first_name' => $user->firstname,
                'last_name' => $user->surname,
                'occupation' => $user->position,
                'work_phone' => $user->phone,
                'fax' => $user->fax,
                'mobile_number' => $user->mobile,
                'email' => $user->email,
                'description' => $user->note,
                'old_user_id' => $user->id,
            ]);
            echo ('added: ' . $i);
            echo PHP_EOL;
            $i++;
        }
    }
}
