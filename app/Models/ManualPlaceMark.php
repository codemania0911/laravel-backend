<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class ManualPlaceMark extends Model
{
    //
    use Searchable;
    protected $table = 'manual_placemarks';
    protected $guarded = [];
}
