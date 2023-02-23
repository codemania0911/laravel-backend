<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class VesselData extends Model
{
    //
    protected $table = 'object_data';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
