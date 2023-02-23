<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class BillingInformation extends Model
{
    //
    protected $table = 'billing_information';
    protected $connection = "mysql_ardent";
    protected $guarded = [];

    public function address()
    {
        return $this->belongsTo(Addresses::class, 'billingaddress_id', 'id');
    }
}
