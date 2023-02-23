<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class CompanyContact extends Model
{
    //
    protected $table = 'company_contacts';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
