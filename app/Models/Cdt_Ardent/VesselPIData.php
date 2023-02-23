<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class VesselPIData extends Model
{
    //
    protected $table = 'pis_to_objects';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
