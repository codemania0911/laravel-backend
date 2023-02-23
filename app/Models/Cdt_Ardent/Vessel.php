<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    //
    protected $table = 'objects';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
