<?php

namespace App\Models\Cdt_Ardent;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    //
    protected $table = 'companies';
    protected $connection = "mysql_ardent";
    protected $guarded = [];

    public function address()
    {
        return $this->belongsTo(Addresses::class, 'address_id', 'id');
    }
}
