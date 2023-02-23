<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class VesselClass extends Model
{
    //
    use Searchable;
    protected $table = 'vessel_classes';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vessels()
    {
        return $this->hasMany(Vessel::class, 'vessel_class_id', 'id');
    }
}
