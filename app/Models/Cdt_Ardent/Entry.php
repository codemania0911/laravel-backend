<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    //
    protected $table = 'entries';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
