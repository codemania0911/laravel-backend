<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAccounting extends Model
{
    //
    protected $table = 'company_accounting';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
