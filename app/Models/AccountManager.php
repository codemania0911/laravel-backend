<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManager extends Model
{
    //
    protected $table = 'account_managers';
    protected $guarded = [];

    public function companies()
    {
        return $this->hasMany(AccountManagerCompany::class);
    }

    public function regions()
    {
        return $this->hasMany(AccountManagerRegion::class);
    }

    public function countries()
    {
        return $this->hasMany(AccountManagerCountry::class);
    }
}
