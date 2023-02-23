<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class UserCompany extends Model
{
    //
    protected $table = 'user_company';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
