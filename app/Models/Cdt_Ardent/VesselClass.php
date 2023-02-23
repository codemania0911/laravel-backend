<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class VesselClass extends Model
{
    //
    protected $table = 'vessel_class';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
