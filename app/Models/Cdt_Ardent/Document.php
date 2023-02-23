<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    //
    protected $table = 'document';
    protected $connection = "mysql_ardent";
    protected $guarded = [];
}
