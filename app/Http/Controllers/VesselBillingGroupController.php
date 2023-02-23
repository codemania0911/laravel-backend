<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VesselBillingGroup;
use App\Models\Vessel;

use Illuminate\Support\Facades\DB;

class VesselBillingGroupController extends Controller
{
    //
    public function getVesselBillingGroup(Request $request)
    {
        $perPage = empty(request('per_page')) ? 10 : (int)request('per_page');

        $query = $request->get('query');

        $vesselBillingGroup = VesselBillingGroup::from('vessel_billing_group as vbg')
                                ->select('vbg.id as id', 'vbg.name as name', 'c.id as company_id', 'c.name as company_name', DB::raw('count(v.id) as vessel_count'))
                                ->leftJoin('companies as c', 'c.id', '=', 'vbg.company_id')
                                ->leftJoin('vessels as v', 'v.vessel_billing_group_id', '=', 'vbg.id')
                                ->groupBy('vbg.id');

        if(!empty($query) && strlen($query) > 2) {
            $uids = VesselBillingGroup::search($query)->get('id')->pluck('id');
            $vesselBillingGroup->whereIn('vbg.id', $uids);
        }

        return $vesselBillingGroup->paginate($perPage);
    }
    
    public function show(VesselBillingGroup $vesselBillingGroup)
    {
        return VesselBillingGroup::with('company:id,name')->whereId($vesselBillingGroup->id)->first();
    }

    public function vesselBillingGroupAddress(VesselBillingGroup $vesselBillingGroup)
    {
        return $vesselBillingGroup->address()->with('zone')->first();
    }

    public function vesselBillingGroupVessels(VesselBillingGroup $vesselBillingGroup, Request $request)
    {
        $query = $request->get('query');

        $results = $vesselBillingGroup->vessels()->select('id', 'name', 'imo', 'official_number');

        if(!empty($query) && strlen($query) > 2) {
            $uids = Vessel::search($query)->get('id')->pluck('id');
            $results = $vesselBillingGroup->vessels()->whereIn('id', $uids);
        }

        $perPage = request('per_page') == -1 ? count($results->get()) : (int)request('per_page');

        return $results->paginate($perPage);
    }

    public function getNote(VesselBillingGroup $vesselBillingGroup)
    {
        return $vesselBillingGroup;
    }

    public function addNote(VesselBillingGroup $vesselBillingGroup, Request $reqeuet)
    {
        $vesselBillingGroup->note = request('note');
        $vesselBillingGroup->save();

        return response()->json(['success' => true, 'message' => 'Vessel Billing Group note added.']);
    }

    public function updateVesselBillingGroup(VesselBillingGroup $vesselBillingGroup, Request $reqeuet)
    {
        $vesselBillingGroup->name = request('name');
        $vesselBillingGroup->company_id = request('company_id');
        $vesselBillingGroup->save();

        return response()->json(['success' => true, 'message' => 'Vessel Billing Group updated.']);
    }

    public function destroyVesselBillingGroup(VesselBillingGroup $vesselBillingGroup)
    {
        Vessel::where('vessel_billing_group_id', $vesselBillingGroup->id)->update([
            'vessel_billing_group_id' => NULL
        ]);

        $vesselBillingGroup->delete();

        return response()->json(['success' => true, 'message' => 'Vessel Billing Group deleted.']);
    }

    // Add the Vessel Class
    public function addVesselBillingGroup(Request $request)
    {
        $vesselBillingGroup = new VesselBillingGroup();
        $vesselBillingGroup->name = request('name');
        $vesselBillingGroup->company_id = request('company_id');
        $vesselBillingGroup->save();

        return response()->json(['message' => 'Vessel Billing Group Added.']);
    }
}
