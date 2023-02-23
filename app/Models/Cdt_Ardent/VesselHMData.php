<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class VesselHMData extends Model
{
    //
    protected $table = 'hms_to_objects';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
