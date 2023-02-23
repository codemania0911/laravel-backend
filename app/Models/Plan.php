<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Plan extends Model
{
    //
    use Searchable;
    protected $table = 'plans';
    protected $guarded = [];

    const NON_VRP_FIELDS_PLAN = '
        p.id,
        p.company_id,
        -1 as vrpid,
        p.plan_holder_name as holder_name,
        p.plan_number,
        NULL as vrp_import,
        p.active_field_id as djs_active,
        NULL as vrp_plan_holder_name,
        NULL as vrp_status,
        p.plan_number as vrp_plan_number,
        NULL as plan_exp_date,
        NULL as vrp_country,
        NULL as vrp_primary_smff,
        c.vendor_active as vendor_active,
        c.networks_active as networks_active,
        cs.id IS NOT NULL AS capabilies_active,
        p.updated_at as updated_at
    ';
    const FIELDS_PLAN = '
        p.id,
        p.company_id,
        vp.id as vrpid,
        p.plan_holder_name as holder_name,
        p.plan_number,
        NULL as vrp_import,
        p.active_field_id as djs_active,
        vp.plan_holder as vrp_plan_holder_name,
        vp.status as vrp_status,
        p.plan_number as vrp_plan_number,
        vp.plan_exp_date as plan_exp_date,
        NULL as vrp_country,
        vp.primary_smff as vrp_primary_smff,
        c.vendor_active as vendor_active,
        c.networks_active as networks_active,
        cs.id IS NOT NULL AS capabilies_active,
        p.updated_at as updated_at
    ';

    const UNION_FIELDS_PLAN = '
        -1 as id,
        NULL as company_id,
        vp2.id as vrpid,
        vp2.plan_holder as holder_name,
        vp2.plan_number as plan_number,
        1 as vrp_import,
        NULL AS djs_active,
        vp2.plan_holder as vrp_plan_holder_name,
        vp2.status as vrp_status,
        vp2.plan_number as vrp_plan_number,
        vp2.plan_exp_date as plan_exp_date,
        vp2.holder_country as vrp_country,
        vp2.primary_smff as vrp_primary_smff,
        NULL as vendor_active,
        NULL as networks_active,
        NULL as capabilies_active,
        vp2.updated_at as updated_at
    ';

    const DETAIL_PLAN = '
        p.id,
        p.company_id,
        p.plan_holder_name,
        p.plan_number,
        c.phone,
        c.fax,
        c.email,
        p.qi_id,
        p.plan_preparer_id,
        c.operating_company_id,
        c.website,
        c.has_photo,
        c.shortname,
        c.vendor_active as vendor_active,
        cs.id IS NOT NULL AS capabilies_active,
        c.networks_active,
        p.active_field_id
    ';

    public function addresses()
    {
        return $this->hasMany(CompanyAddress::class, 'plan_id', 'id');
    }

    public function vessels()
    {
        return $this->hasMany(Vessel::class);
    }

    public function vrpPlan()
    {
        return $this->hasOne(Vrp\VrpPlan::class, 'plan_number', 'plan_number');
    }

    public function primaryAddress()
    {
        return $this->addresses()->whereHas('addressType', function ($q) {
            $q->where('name', 'Primary');
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function notes()
    {
        return $this->hasMany(CompanyNotes::class);
    }

    public function planPreparer()
    {
        return $this->belongsTo(PlanPreparer::class, 'plan_preparer_id');
    }
}
