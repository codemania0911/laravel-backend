<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class Addresses extends Model
{
    //
    protected $table = 'addresses';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
