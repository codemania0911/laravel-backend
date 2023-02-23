<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class VesselBillingGroup extends Model
{
    //
    use Searchable;
    protected $table = 'vessel_billing_group';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function address()
    {
        return $this->belongsTo(CompanyAddress::class, 'billing_address_id');
    }

    public function vessels()
    {
        return $this->hasMany(Vessel::class);
    }
}
