<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Company;
use App\Models\VesselFleets;
use App\Models\Network;
use App\Models\Capability;
use App\Models\CapabilityValue;
use App\Models\Vessel;
use App\Models\VesselListIndex;
use App\Models\Vrp\Vessel as VrpVessel;
use App\Models\User;
use App\Models\VesselType;
use App\Models\Vrp\VrpPlan;
use App\Models\Vendor;
use App\Models\VesselVendor;
use App\Models\TrackChange;
use App\Models\ChangesTableName;
use App\Models\Action;
use App\Helpers\VRPExpressVesselHelper;
use App\Http\Resources\NoteResource;
use Intervention\Image\ImageManagerStatic as Image;
use App\Http\Resources\CapabilityResource;
use App\Http\Resources\VesselIndexResource;
use App\Http\Resources\VesselResource;
use App\Http\Resources\VesselListResource;
use App\Http\Resources\VesselShortResource;
use App\Http\Resources\VesselShowAISResource;
use App\Http\Resources\VesselShowConstructionDetailResource;
use App\Http\Resources\VesselShowDimensionsResource;
use App\Http\Resources\VesselShowResource;
use App\Http\Resources\VesselTrackResource;
use App\Http\Resources\VesselPollResource;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use stdClass;
use App\Helpers\MTHelper;
use App\Models\VesselAISPositions;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use DateTime;

use Config;

ini_set('memory_limit', '-1');

class VesselController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function getAll(Request $request)
    {
        $sort = request('sortBy') ? request('sortBy') : 'updated_at';
        $sortDir = request('direction') ? request('direction') : 'desc';

        $query = request('query');

        $bulkSelected =  request('bulkSelected');

        $vrp = Auth::user()->hasVRP();

        $cdtDBName = Config::get('database.connections')['mysql']['database'];

        $vessels = Vessel::from('vessels as v1')
                        ->select(DB::raw(empty($vrp) ? Vessel::FIELDS_CDT : Vessel::UNION_FIELDS_CDT))
                        ->leftJoin('vessel_types as t', 'v1.vessel_type_id', '=', 't.id')
                        ->leftJoin('companies as c1', 'v1.company_id', '=', 'c1.id')
                        ->leftJoin('plans as p1', 'v1.plan_id', '=', 'p1.id')
                        ->leftjoin('capabilities AS vs', function($join) {
                            $join->on('v1.smff_service_id','=','vs.id');
                            $join->on('vs.status', '=', DB::raw('1'));
                        })
                        ->whereNull('v1.deleted_at');

        $vessels = $this->getVesselModal(null, $vessels);

        if (!empty($query) && strlen($query) > 2) {
            $vesselIds = Vessel::search($query)->get('id')->pluck('id');
            $vessels->whereIn('v1.id', $vesselIds);
        }

        if ($request->has('staticSearch')) {
            $this->staticSearch($vessels, $request->get('staticSearch'));
        }

        if (empty($vrp)) {
            if (!empty($query) && strlen($query) > 2) {
                $vesselIds = Vessel::search($query)->get('id')->pluck('id');
                $vessels->whereIn('v1.id', $vesselIds);
            }

            $resultsQuery = $vessels->orderBy($sort, $sortDir);

        } else {

            $vrpVesselTable = new VrpVessel;
            $vrpVesselTableName = $vrpVesselTable->table();

            $planTable = new VrpPlan;
            $planTableName = $planTable->table();

            $vessels = $vessels
                    ->leftJoin($vrpVesselTableName . " AS vrpv", function($join) {
                        $join->on('v1.imo','=','vrpv.imo');
                        $join->on('p1.plan_number','=','vrpv.plan_number_id');
                    })
                    ->leftJoin($planTableName . " AS p", 'vrpv.plan_number_id','=','p.id');

            if (!empty($query) && strlen($query) > 2) {
                $vrpVessels = VrpVessel::from($vrpVesselTableName . " AS vrpv2")
                    ->select(DB::raw(Vessel::UNION_FIELDS_VRP))
                    ->leftJoin($planTableName . " AS p", 'vrpv2.plan_number_id','=','p.id')
                    ->whereRaw('((vrpv2.imo IS NOT NULL AND vrpv2.imo NOT IN (SELECT imo FROM ' . $cdtDBName . '.vessels AS vx WHERE vx.imo IS NOT NULL)) OR (vrpv2.imo IS NULL AND vrpv2.official_number IS NOT NULL AND vrpv2.official_number NOT IN (SELECT official_number FROM ' .$cdtDBName. '.vessels AS vx2 WHERE vx2.official_number IS NOT NULL)))');

                if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', $query, $specialChar)) {
                    $strings = explode($specialChar[0], $query);
                    $uids = VrpVessel::where([['vessel_name', 'like', '%' . $strings[0] . '%'], ['vessel_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                    $vrpVessels->whereIn('vrpv2.id', $uids);
                } else {
                    $uids = VrpVessel::search($query)->get('id')->pluck('id');
                    $vrpVessels->whereIn('vrpv2.id', $uids);
                }
            } else {
                request('staticSearch')['merge'] == -1 ?
                $vrpVessels = VrpVessel::from($vrpVesselTableName . " AS vrpv2")
                    ->select(DB::raw(Vessel::UNION_FIELDS_VRP))
                    ->leftJoin($planTableName . " AS p", 'vrpv2.plan_number_id','=','p.id')
                    ->whereRaw('((vrpv2.imo IS NOT NULL AND vrpv2.imo NOT IN (SELECT imo FROM ' . $cdtDBName . '.vessels AS vx WHERE vx.imo IS NOT NULL AND vx.plan_id IS NOT NULL)) OR (vrpv2.imo IS NULL AND vrpv2.official_number IS NOT NULL AND vrpv2.official_number NOT IN (SELECT official_number FROM ' .$cdtDBName. '.vessels AS vx2 WHERE vx2.official_number IS NOT NULL AND vx2.plan_id IS NOT NULL)))') :
                    
                $vrpVessels = VrpVessel::from($vrpVesselTableName . " AS vrpv2")
                    ->select(DB::raw(Vessel::UNION_FIELDS_VRP))
                    ->leftJoin($planTableName . " AS p", 'vrpv2.plan_number_id','=','p.id');
            }

            if ($request->has('staticSearch')) {
                $this->staticSearchVrpVessels($vrpVessels, $request->get('staticSearch'));
            }

            $resultsQuery = $vessels
                        ->union($vrpVessels)
                        ->orderBy($sort, $sortDir);
        }

        $perPage = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');

        $results = VesselListResource::collection($resultsQuery->paginate($perPage));

        return $results;
    }

    private function staticSearch($model, $staticSearch)
    {
        $model = (array_key_exists('active_field_id', $staticSearch) && $staticSearch['active_field_id'] !== -1) ? $model->where('v1.active_field_id', $staticSearch['active_field_id']) : $model;
        $model = ($staticSearch['vrp_status'] !== -1) ? $model->whereNotNull('vrp_id') : $model;

        if (array_key_exists('resource_provider', $staticSearch) && $staticSearch['resource_provider'] !== -1) {
            $model = $staticSearch['resource_provider'] ? $model->whereRaw('(vs.id IS NOT NULL)') : $model->whereRaw('(vs.id IS NULL)');
        }

        $model = count($staticSearch['types']) ? $model->whereIn('v1.vessel_type_id', $staticSearch['types']) : $model;

        $model = (array_key_exists('fleets', $staticSearch) && count($staticSearch['fleets'])) ? $model->join('vessels_fleets AS vf', 'v1.id', '=', 'vf.vessel_id')->whereIn('vf.fleet_id', $staticSearch['fleets'])
                : $model;

        $index = 1;
        foreach (['vendors', 'qi', 'pi', 'response', 'societies', 'insurers', 'providers'] as $v_type) {
            if (array_key_exists($v_type, $staticSearch) && count($staticSearch[$v_type])) {
                $model = $model
                    ->join('vessels_vendors AS vv' . $index,
                        'v1.id', '=', 'vv' . $index . '.vessel_id')
                    ->whereIn('vv' . $index . '.company_id', $staticSearch[$v_type]);
                $index++;
            }
        }


        if (array_key_exists('company', $staticSearch)) {
            $ids[] = $staticSearch['company'];
            if (array_key_exists('operated', $staticSearch) && $staticSearch['operated']) {
                $ids = Company::where('operating_company_id', $staticSearch['company'])->pluck('id')->toArray();
                $model = $model->whereIn('c1.id', $ids);
            } else {
                $model = $model->whereIn('v1.company_id', $ids);
            }
            
            if (array_key_exists('non_vrp', $staticSearch) && $staticSearch['non_vrp'] == 1) {
                $model = $model->whereNull('v1.plan_id');
            }
        }

        $model = (array_key_exists('companies', $staticSearch) && count($staticSearch['companies'])) ? $model->whereIn('c1.id', $staticSearch['companies']) : $model;
        $model = (array_key_exists('plan', $staticSearch) && isset($staticSearch['plan'])) ? $model->where('v1.plan_id', $staticSearch['plan']) : $model;

        $model = (array_key_exists('networks', $staticSearch) && count($staticSearch['networks'])) ? $model
            ->join('network_companies AS nc', 'c1.id', '=', 'nc.company_id')
            ->where('c1.networks_active', 1)
            ->whereIn('nc.network_id', $staticSearch['networks']) : $model;

        return $model;
    }


    private function staticSearchVrpVessels($model, $staticSearch)
    {
        $hasVendor = false;
        foreach (['vendors', 'qi', 'pi', 'response', 'societies', 'insurers', 'providers'] as $v_type) {
            if (array_key_exists($v_type, $staticSearch) && count($staticSearch[$v_type])) {
                $hasVendor = true;
            }
        }

        $vrpPlan = true;
        if(array_key_exists('plan', $staticSearch)) {
            $planNumber = Plan::whereId($staticSearch['plan'])->first()->plan_number;
            $vrpPlan = VrpPlan::where('plan_number', $planNumber)->first();
        }
        

        if ($hasVendor ||
            count($staticSearch['types']) ||
            count($staticSearch['fleets']) ||
            count($staticSearch['networks']) ||
            (intval($staticSearch['active_field_id']) !== -1) ||
            (array_key_exists('resource_provider', $staticSearch) && $staticSearch['resource_provider'] !== -1) ||
            (array_key_exists('include_vrp', $staticSearch) && $staticSearch['include_vrp'] !== -1) || 
            (array_key_exists('company', $staticSearch)) ||
            (!$vrpPlan)) {
            $model->whereRaw('0=1');
        } else {
            if (array_key_exists('vrp_status', $staticSearch) && $staticSearch['vrp_status'] !== -1) {
                $statusSearch = $staticSearch['vrp_status'] ? 'Authorized' : 'Not Authorized';
                $model = $model->where('vrpv2.vessel_status', $statusSearch);
            }

            if(array_key_exists('plan', $staticSearch)) {
                $planNumber = Plan::whereId($staticSearch['plan'])->first()->plan_number;
                if(VrpPlan::where('plan_number', $planNumber)->first()) {
                    $vrpPlanId = VrpPlan::where('plan_number', $planNumber)->first()->id;
                    $model = $model->where('vrpv2.plan_number_id', $vrpPlanId);
                }
            }
        }
        return $model;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function show($id)
    {
        $defaultModel = new Vessel;

        $cdtVesselsTable = $this->getVesselModal(null, $defaultModel);
        $cdtVesselTableName = $defaultModel->table();

        $companyTable = new Company;
        $companyTableName = $companyTable->table();

        $vesselTypeTable = new VesselType;
        $vesselTypeTableName = $vesselTypeTable->table();

        $vessel = $cdtVesselsTable
            ->from($cdtVesselTableName . ' AS v1')
            ->select(DB::raw(Vessel::FIELDS_CDT . ", v1.*"))
            ->leftJoin($vesselTypeTableName . " AS t", 'v1.vessel_type_id', '=', 't.id')
            ->leftJoin($companyTableName . " AS c1", 'v1.company_id','=','c1.id')
            ->leftJoin('plans as p1', 'v1.plan_id', '=', 'p1.id')
            ->leftjoin('capabilities AS vs', function($join) {
                $join->on('v1.smff_service_id','=','vs.id');
                $join->on('vs.status','=',DB::raw('1'));
            })
            ->where('v1.id', $id)
            ->whereNull('v1.deleted_at')
            ->get();
        return VesselShowResource::collection($vessel);
    }


    public function showVRP($id)
    {
        $vrp = Auth::user()->hasVRP();

        if (!$vrp) {
            return response()->json(null);
        }

        $vessel = Vessel::find($id);
        $plan = $vessel->plan;
        $vrp = VrpVessel::join('vrp_plan', 'plan_number_id', '=', 'vrp_plan.id')
             ->where('vrp_plan.plan_number', $plan->plan_number)
            ->where('imo', $vessel->imo)
            ->first();

        return response()->json(!empty($vrp) ? [
            'vrp_status' => $vrp->vessel_status,
            'imo' => $vrp->imo,
            'official_number' => $vrp->official_number,
            'vessel_status' => $vrp->vessel_status,
            'vrp_plan_status' => $vrp->vrpPlan->status,
            'vrp_plan_number' => $vrp->vrpPlan->plan_number,
            'vessel_is_tank' => $vrp->vessel_is_tank === 'NT' ? 0 : 1,
            'vrp_count' => VrpVessel::where('imo', $vessel->imo)->count(),
            'vessel_name' => $vrp->vessel_name,
            'vessel_type' => $vrp->vessel_type,
            'plan_holder' => $vrp->vrpPlan->plan_holder ?? '',
            'primary_smff' => $vrp->vrpPlan->primary_smff ?? '',
            'wcd_barrels' => $vrp->wcd_barrels
        ] : null);
    }

    public function getAllShort()
    {
        return VesselIndexResource::collection($this->getVesselModal()->whereNull('deleted_at')->get());
    }

    public function getAllUnderCompanyShort($cid)
    {
        return VesselIndexResource::collection(Vessel::where('company_id', $cid)->whereIn('active_field_id', [2, 3, 5])->whereNull('deleted_at')->get());
    }

    public function getAllUnderPlanShort($pid)
    {
        return VesselIndexResource::collection(Vessel::where('plan_id', $pid)->whereIn('active_field_id', [2, 3, 5])->whereNull('deleted_at')->get());
    }

    public function getRelatedList(Request $request)
    {
        $query = request('query');

        if (strlen($query) < 3) {
            return [];
        }

        $ids = Vessel::search($query)->get('id')->pluck('id');
        return VesselShortResource::collection($this->getVesselModal()
                    ->whereIn('id', $ids)
                    ->where('lead_ship', 0)
                    ->whereNull('deleted_at')
                    ->get());
    }

    public function getParentList(Request $request)
    {
        $query = request('query');

        if (strlen($query) < 3) {
            return [];
        }

        $ids = Vessel::search($query)->get('id')->pluck('id');
        return VesselShortResource::collection($this->getVesselModal()
                            ->whereIn('id', $ids)
                            ->where('lead_ship', 1)
                            ->whereNull('deleted_at')
                            ->get());
    }

    public function getSisterList()
    {   
        //isLeadShip=false&noLeadShip=true&noChildShip=true
        return VesselShortResource::collection($this->getVesselModal()
                            ->where('lead_ship', 0)
                            ->whereNull('lead_ship_id')
                            ->whereNull('deleted_at')
                            ->get());
    }

    public function getChildVesselsList()
    {
        //isLeadShip=false&noLeadShip=true&noSisterShip=true
        //->whereNull('lead_ship_id')
        return VesselShortResource::collection($this->getVesselModal()
                            ->where('lead_ship', 0)
                            ->whereNull('lead_sister_ship_id')
                            ->whereNull('deleted_at')
                            ->get());
    }

    public function getSisterVesselsList()
    {
        //isLeadShip=false&noLeadShip=true&noChildShip=true
        return VesselShortResource::collection($this->getVesselModal()
                            ->where('lead_ship', 0)
                            ->whereNull('lead_ship_id')
                            ->whereNull('deleted_at')
                            ->get());
    }

    public function storePhoto(Vessel $vessel, Request $request)
    {
        $this->validate($request, [
            'file' => [
                'mimes:png,jpg,jpeg',
            ]
        ]);

        $frect = $request->file('file_rect');
        $fsqr = $request->file('file_sqr');

        $image_rect = Image::make($frect->getRealPath());
        $image_sqr = Image::make($fsqr->getRealPath());

        $directory = 'pictures/vessels/' . $vessel->id . '/';

        $name1 = 'cover_rect.jpg';
        $name2 = 'cover_sqr.jpg';

        if (
            Storage::disk('gcs')->put($directory . $name2, (string)$image_sqr->encode('jpg'), 'public') &&
            Storage::disk('gcs')->put($directory . $name1, (string)$image_rect->encode('jpg'), 'public')
        ) {
            $vessel->has_photo = true;
            $vessel->save();
            return response()->json(['success' => true, 'message' => 'Picture uploaded.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function destroyPhoto(Vessel $vessel)
    {
        $directory = 'pictures/vessels/' . $vessel->id . '/';
        if (
            Storage::disk('gcs')->delete($directory . 'cover_rect.jpg') &&
            Storage::disk('gcs')->delete($directory . 'cover_sqr.jpg')
        ) {
            $vessel->has_photo = false;
            $vessel->save();
            return response()->json(['success' => true, 'message' => 'Picture deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Can not delete a company photo.']);
    }

    public function unAssignedVessel(Vessel $vessel)
    {
        $unAssignedVessel = Vessel::select('id','name')->get();
        return response()->json(['success' => true, 'message' => 'Unassigned vessels list', 'vessels' => $unAssignedVessel]);
    }



    public function assignMultipleVessel(Request $request)
    {
       $vessels_id = request('vessel.vessel_ids');
       foreach ($vessels_id as $key => $value) {
        Vessel::where('id',$value)
                ->update(['company_id' => request('vessel.company_id')]);
       }
        return response()->json(['success' => true, 'message' => 'Company Added Successfully']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $vessel = new Vessel();
        $vessel->name = request('name');
        $vessel->imo = request('imo_number');
        $vessel->mmsi = request('mmsi_number');
        $vessel->company_id = request('company');
        $vessel->plan_id = request('plan');
        $vessel->official_number = request('official_number');
        $vessel->sat_phone_primary = $request->input('phone_primary');
        $vessel->sat_phone_secondary = $request->input('phone_secondary');
        $vessel->email_primary = $request->input('email_primary');
        $vessel->email_secondary = $request->input('email_secondary');
        $vessel->tanker = (boolean)request('is_tank');
        $vessel->vessel_type_id = request('type');
        $vessel->dead_weight = request('dead_weight');
        $vessel->deck_area = request('deck_area');
        $vessel->oil_group = request('oil_group');
        $vessel->oil_tank_volume = request('oil_tank_volume');
        $vessel->primary_poc_id = request('primary_contact');
        $vessel->secondary_poc_id = request('secondary_contact');
        $vessel->active_field_id = request('active_field_id');
        $vessel->lead_ship_id = (request('parent_ship') && !request('is_lead'))? request('parent_ship') : 0;
        $vessel->lead_sister_ship_id = (request('sister_ship') && !request('is_lead')) ? request('sister_ship') : 0;
        $vessel->ais_timestamp = '0000-00-00 00:00:00';
        $vessel->ex_name = request('ex_name');
        $vessel->sister_ship = request('sister_ship');
        $vessel->gross_tonnage = request('gross_tonnage');

        if(request('is_lead')) {
            $vessel->lead_ship = 1;
        }

        if (!request('permitted')) {
            if ($request->has('imo_number') && (int)request('imo_number') != 0 && Vessel::where('imo', request('imo_number'))->first()) {
                return response()->json(['success' => false, 'message' => 'That IMO already exists.']);
            }

            if ($request->has('official_number') && (int)request('official_number') != 0 &&  Vessel::where('official_number', request('official_number'))->first()) {
                return response()->json(['success' => false, 'message' => 'That Official Number already exists.']);
            }
        }

        if ($vessel->save())
        {
            $qi = (request('qi_company'))?request('qi_company'):array();
            $pi = (request('pi_club'))?request('pi_club'):array();
            $societies =  (request('society'))?request('society'):array();
            $insurers =  (request('insurer'))?request('insurer'):array();
            $providers = (request('ds_provider'))?request('ds_provider'):array();
            $vessel->vendors()->attach($qi);
            $vessel->vendors()->attach($pi);
            $vessel->vendors()->attach($societies);
            $vessel->vendors()->attach($insurers);
            $vessel->vendors()->attach($providers);

            if (request('is_lead'))
            {
                if(request('sister_vessel'))
                {
                    foreach (\request('sister_vessel') as $id)
                    {
                        Vessel::find($id)->update(['lead_sister_ship_id' => $vessel->id]);
                    }
                }
                if(request('child_vessel'))
                {
                    foreach (\request('child_vessel') as $id) {
                        Vessel::find($id)->update(['lead_ship_id' => $vessel->id]);
                    }
                }
            }
            $vessel->fleets()->sync(\request('fleet'));

            if(request('comments')) {
                $vessel->notes()->create([
                    'note' => $request->input('comments'),
                    'note_type' => 1,
                    'user_id' => Auth::user()->id,
                    'vessel_id' => $vessel->id
                ]);
            }

            $message = 'Created Entry for ' . request('name') . '.';
            switch ((int)request('active_field_id')) {
                case 2:
                    $message = 'Created Entity and Activated DJS Coverage.';
                break;
                case 3:
                    $message = 'Created Entity and Activated DJS-A Coverage';
                break;
                case 5:
                    $message = 'Created Entity and Activated both DJS and DJS-A Coverage.';
                break;
            }

            $vessel->notes()->create([
                'note' => $message,
                'note_type' => 1,
                'user_id' => Auth::user()->id,
                'vessel_id' => $vessel->id
            ]);

            /*Image upload to S3*/
            if($request->image){
                $reqest_image = $request->image;
                $imageInfo = explode(";base64,", $reqest_image);
                $image1 = str_replace(' ', '+', $imageInfo[1]);

                $image = Image::make($image1);
                $image->fit(720, 405);

                $directory = 'pictures/vessels/' . $vessel->id . '/';

                $name = 'cover.jpg';

                if (Storage::disk('gcs')->put($directory.$name, (string)$image->encode('jpg'), 'public')) {
                    $vessel->photo = $name;
                    $vessel->save();
                }
            }

            $vesselIds = [];
            $vesselIds[] = $vessel->id;
            $ids = '';
            foreach($vesselIds as $vesselId)
            {
                $ids .= $vesselId.',';
            }
            $ids = substr($ids, 0, -1);
            TrackChange::create([
                'changes_table_name_id' => 2,
                'action_id' => 1,
                'count' => 1,
                'ids' => $ids,
            ]);
            return response()->json(['success' => true, 'message' => 'Vessel added.', 'id' => $vessel->id]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function storeSMFF($id)
    {
        $vessel = Vessel::find($id);
        $smff = null;

        if ($vessel) {
            if ($vessel->smff_service_id) {
                $smff = Capability::find($vessel->smff_service_id);
                if (empty($smff)) {
                    // error???
                } else {
                    $smff->status = 1; // undelete
                    $smff->save();
                }
            } else {
                if ($vessel->company && $vessel->company->smffCapability) {
                    $smff_copy = $vessel->company->smffCapability->replicate();
                    $vessel->smff_service_id = Capability::create($smff_copy->toArray())->id;
                } else {
                    $vessel->smff_service_id = Capability::create()->id;
                }
            }
            return $vessel->save() ? response()->json(['success' => true, 'message' => 'SMFF Capabilities created.']) 
                        : response()->json(['success' => false, 'message' => 'Could not create SMFF Capabilities.']);
        }

        return response()->json(['success' => false, 'message' => 'No vessel found.'], 404);
    }

    public function toggleStatus(Vessel $vessel, Request $request)
    {
        $vessel->active_field_id = (int)request('active_field_id');

        if ($vessel->save()) {
            switch ((int)request('active_field_id')) {
                case 1:
                    $message = 'Deactivated DJ-S coverage.';
                break;
                case 2:
                    $message = 'Activated DJ-S coverage.';
                break;
                case 3:
                    $message = 'Activated DJ-S A coverage.';
                break;
                case 4:
                    $message = 'Deactivated DJ-S A coverage.';
                break;
                case 5:
                    $message = 'Activated DJS and DJS-A coverage.';
                break;
            }

            $vessel->notes()->create([
                'note' => $message,
                'note_type' => 1,
                'user_id' => Auth::user()->id,
                'vessel_id' => $vessel->id
            ]);
            return response()->json(['success' => true, 'message' => $message]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function toggleTanker(Vessel $vessel)
    {
        $vessel->tanker = !$vessel->tanker;
        if ($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Vessel tanker status changed.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }


    public function showConstructionDetail($id)
    {
        return VesselShowConstructionDetailResource::collection(Vessel::where('id', $id)->get());
    }

    public function showAIS($id)
    {
        return VesselShowAISResource::collection(Vessel::where('id', $id)->get());
    }

    public function showSMFF($id)
    {
        $vessel = Vessel::where('id', $id)->first();
        $smff =  $vessel->smff();
        $networks = $vessel->networks;
        return response()->json([
            'vessel' => $vessel->smff_service_id,
            'smff' => $smff,
            'networks' => $networks->pluck('code'),
            'serviceItems' => Capability::primaryServiceAvailable()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {

        $vessel = Vessel::find($id);
        if ($request->has('name')) $vessel->name = request('name');
        if ($request->has('imo')) $vessel->imo = request('imo');
        if ($request->has('official_number')) $vessel->official_number = request('official_number');
        if ($request->has('mmsi')) $vessel->mmsi = request('mmsi');
        if ($request->has('vessel_type_id')) $vessel->vessel_type_id = request('vessel_type_id');
        if ($request->has('dead_weight')) $vessel->dead_weight = request('dead_weight');
        if ($request->has('tanker')) $vessel->tanker = request('tanker');
        if ($request->has('active_field_id')) $vessel->active_field_id = (int)request('active_field_id');
        if ($request->has('deck_area')) $vessel->deck_area = request('deck_area');
        if ($request->has('oil_tank_volume')) $vessel->oil_tank_volume = request('oil_tank_volume');
        if ($request->has('oil_group')) $vessel->oil_group = request('oil_group');
        if ($request->has('company_id')) $vessel->company_id = request('company_id');
        if ($request->has('plan_id')) $vessel->plan_id = request('plan_id');
        if ($request->has('primary_poc_id')) $vessel->primary_poc_id = request('primary_poc_id');
        if ($request->has('secondary_poc_id')) $vessel->secondary_poc_id = request('secondary_poc_id');
        if ($request->has('sat_phone_primary')) $vessel->sat_phone_primary = request('sat_phone_primary');
        if ($request->has('sat_phone_secondary')) $vessel->sat_phone_secondary = request('sat_phone_secondary');
        if ($request->has('email_primary')) $vessel->email_primary = request('email_primary');
        if ($request->has('email_secondary')) $vessel->email_secondary = request('email_secondary');
        if ($request->has('ex_name')) $vessel->ex_name = request('ex_name');
        if ($request->has('sister_ship')) $vessel->sister_ship = request('sister_ship');
        if ($request->has('wcd')) $vessel->wcd = request('wcd');
        if ($request->has('gross_tonnage')) $vessel->gross_tonnage = request('gross_tonnage');
        if ($request->has('construction_built')) $vessel->construction_built = request('construction_built');

        if ($vessel->save()) {
            if ($request->has('qi')) {
                $vessel->vendors()->detach();
                $vessel->vendors()->attach(array_merge(
                    request('qi'),
                    request('pi'),
                    request('societies'),
                    request('insurers'),
                    request('providers')
                ));
            }

            if ($request->has('fleet_id')) {
                $vessel->fleets()->sync(request('fleet_id'));
            }
            $vesselIds = [];
            $vesselIds[] = $vessel->id;
            $ids = '';
            foreach($vesselIds as $vesselId)
            {
                $ids .= $vesselId.',';
            }
            $ids = substr($ids, 0, -1);
            TrackChange::create([
                'changes_table_name_id' => 2,
                'action_id' => 3,
                'count' => 1,
                'ids' => $ids,
            ]);

            return response()->json(['success' => true, 'message' => 'Vessel updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Can\'t save. Something unexpected happened.']);
    }

    public function importVrp($id)
    {
        $vrpVessel = VrpVessel::whereId($id)->first();

        if($vrpVessel) {
            $vessel = Vessel::where([['imo', $vrpVessel->imo], ['name', $vrpVessel->vessel_name]])
                            ->orWhere([['official_number', $vrpVessel->official_number], ['name', $vrpVessel->vessel_name]])
                            ->first();

            $type = VesselType::where('name', $vrpVessel->vessel_type)->first();
            $typeId = $type ? $type->id : null;

            if(!$typeId) {
                $vesselTypeNew = VesselType::create(['name' => $vrpVessel->vessel_type]);
                $typeId = $vesselTypeNew->id;
            }

            $vrpPlan = VrpPlan::where('id', $vrpVessel->plan_number_id)->first();
            $plan = null;
            if($vrpPlan) {
                $plan = Plan::where('plan_number', $vrpPlan->plan_number)->first();
            }

            if($vessel) {

                $vessel->name = $vrpVessel->vessel_name;
                $vessel->imo = $vrpVessel->imo;
                $vessel->official_number = $vrpVessel->official_number;
                $vessel->plan_id = isset($plan) ? $plan->id : null;
                $vessel->tanker = in_array($vrpVessel->vessel_is_tank,array('NT','SMPEP','SOPEP','NT/SMPEP','NT/SOPEP')) ? 0 : 1;
                $vessel->mmsi = $vrpVessel->mmsi;
                $vessel->vessel_type_id = $typeId;
                $vessel->save();

                return response()->json(['success' => true,'message' => 'VRP entry updated on cdt']);

            } else{
                Vessel::create([
                    'name' => $vrpVessel->vessel_name,
                    'imo' => $vrpVessel->imo,
                    'official_number' => $vrpVessel->official_number,
                    'plan_id' => isset($plan) ? $plan->id : null,
                    'tanker' => $vrpVessel->vessel_is_tank != 'NT' ? 1 : 0,
                    'mmsi' => $vrpVessel->mmsi,
                    'vessel_type_id' => $typeId
                ]);

                return response()->json(['success' => true, 'message' => 'VRP entry created on cdt']);
            }
        }

        return response()->json(['success' => false, 'message' => 'VRP entry does not exist']);

    }

    public function updateDimensions(Request $request, $id)
    {

        $vessel = Vessel::find($id);
        if ($request->has('construction_length_overall')) $vessel->construction_length_overall = request('construction_length_overall');
        if ($request->has('construction_length_bp')) $vessel->construction_length_bp = request('construction_length_bp');
        if ($request->has('construction_length_reg')) $vessel->construction_length_reg = request('construction_length_reg');
        if ($request->has('construction_bulbous_bow')) $vessel->construction_bulbous_bow = request('construction_bulbous_bow');
        if ($request->has('construction_breadth_extreme')) $vessel->construction_breadth_extreme = request('construction_breadth_extreme');
        if ($request->has('construction_breadth_moulded')) $vessel->construction_breadth_moulded = request('construction_breadth_moulded');
        if ($request->has('construction_draught')) $vessel->construction_draught = request('construction_draught');
        if ($request->has('construction_depth')) $vessel->construction_depth = request('construction_depth');
        if ($request->has('construction_height')) $vessel->construction_height = request('construction_height');
        if ($request->has('construction_tcm')) $vessel->construction_tcm = request('construction_tcm');
        if ($request->has('construction_displacement')) $vessel->construction_displacement = request('construction_displacement');

        if ($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Dimensions Updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function updateProviders(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $dscp_ids = $vessel->vendors()->whereHas('type', function ($q) {
            $q->where('name', 'Damage Stability Certificate Provider');
        })->pluck('id')->toArray();
        $vessel->vendors()->detach($dscp_ids);
        if (request('providers')) {
            $vessel->vendors()->syncWithoutDetaching(request('providers'));
        }
        return response()->json(['success' => true, 'message' => 'Providers Updated.']);
    }

    public function updateConstructionDetail(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $vessel->lead_ship = request('lead_ship');
        $vessel->lead_ship_id = request('lead_ship_id');
        $vessel->lead_sister_ship_id = request('lead_sister_ship_id');
        if ($vessel->save()) {
            if ($vessel->lead_ship) {
                foreach (\request('sister_vessels') as $idv) {
                    Vessel::find($idv)->update(['lead_sister_ship_id' => $vessel->id]);
                }
                foreach (\request('child_vessels') as $idv) {
                    Vessel::find($idv)->update(['lead_ship_id' => $vessel->id]);
                }
            }
            $dscp_ids = $vessel->vendors()->whereHas('type', function ($q) {
                $q->where('name', 'Damage Stability Certificate Provider');
            })->pluck('id')->toArray();
            $vessel->vendors()->detach($dscp_ids);
            $vessel->vendors()->syncWithoutDetaching(request('providers'));
            $vessel->fleets()->sync(\request('fleets'));
            return response()->json(['success' => true, 'message' => 'Vessel construction detail updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function makeLead(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $vessel->lead_ship = request('lead_ship');
        if ($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Lead relation updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function updateRelation(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $vessel->lead_ship = request('lead_ship');
        if (request('child_vessel')) {
            Vessel::find(request('child_vessel'))->update(['lead_ship_id' => $vessel->id]);
        } else if (request('sister_vessel')) {
            Vessel::find(request('sister_vessel'))->update(['lead_sister_ship_id' => $vessel->id]);
        } else if (request('parent')) {
            $vessel->lead_ship_id = request('parent');
        } else {
            $vessel->lead_sister_ship_id = request('lead_sister');
        }
        if ($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'A new relation added.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function removeRelation(Request $request)
    {
        switch (request('type')) {
            case 'child':
                Vessel::find(request('id'))->update(['lead_ship_id' => null]);
            break;
            case 'sister':
                Vessel::find(request('id'))->update(['lead_sister_ship_id' => null]);
            break;
            case 'lead_parent':
                Vessel::find(request('vessel_id'))->update(['lead_ship_id' => null]);
            break;
            case 'lead_sister':
                Vessel::find(request('vessel_id'))->update(['lead_sister_ship_id' => null]);
            break;
        }
        return response()->json(['success' => true, 'message' => 'Successfully removed.']);
    }

    public function updateAIS(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $vessel->latitude = request('latitude');
        $vessel->longitude = request('longitude');
        if ($vessel->save()) {
            $vessel->fleets()->sync(\request('fleets'));
            return response()->json(['success' => true, 'message' => 'Vessel AIS data updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function updateSMFF(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $capabilities = Capability::find($vessel->smff_service_id);
        if (!$capabilities) {
            $this->storeSMFF($id);
            $vessel = Vessel::find($id);
            $capabilities = Capability::find($vessel->smff_service_id);
        }
        $capabilities->status = 1;
        $smffFields = request('smff');
        if (!$capabilities->updateValues(
            isset($smffFields['primary_service']) ? $smffFields['primary_service'] : null,
            isset($smffFields['notes']) ? $smffFields['notes'] : null,
            $smffFields)) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        return response()->json(['success' => true, 'message' => 'Vessel SMFF Capabilities updated.']);
    }

    public function updateNetwork(Request $request, $id)
    {
        $vessel = Vessel::find($id);
        $network_ids = Network::whereIn('code', request('networks'))->pluck('id');
        if ($vessel->networks()->sync($network_ids)) {
            return response()->json(['success' => true, 'message' => 'Vessel Network Membership Updated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $vessel = Vessel::find($id);
        if ($vessel) {
            //delete Damage Stability Models
            if ($vessel->company()->first()) {
                $dms = 'files/damage_stability_models/' . $vessel->company()->first()->id . '/' . $vessel->id . '/';
                Storage::deleteDirectory($dms);
            }

            VesselFleets::where('vessel_id', $id)->delete();
            VesselVendor::where('vessel_id', $id)->delete();

            $vesselIds = [];
            $vesselIds[] = $id;
            $ids = '';
            foreach($vesselIds as $vesselId)
            {
                $ids .= $vesselId.',';
            }
            $ids = substr($ids, 0, -1);
            TrackChange::create([
                'changes_table_name_id' => 2,
                'action_id' => 2,
                'count' => 1,
                'ids' => $ids,
            ]);

            $deletedAt = new DateTime();
            $vessel->deleted_at = $deletedAt;
            return $vessel->save() ? response()->json(['success' => true, 'message' => 'Vessel deleted.']) 
                                        : response()->json(['success' => false, 'message' => 'Could not delete vessel.']);
        }

        return response()->json(['success' => false, 'message' => 'No vessel found.'], 404);
    }

    public function destroySMFF($id)
    {
        $vessel = Vessel::find($id);
        if ($vessel) {
            $smff = Capability::find($vessel->smff_service_id);
            if (!empty($smff)) {
                $smff->status = 0;
            }
            return empty($smff) || $smff->save() ? response()->json(['success' => true, 'message' => 'SMFF Capabilities deleted.']) 
                            : response()->json(['success' => false, 'message' => 'Could not delete SMFF Capabilities.']);
        }

        return response()->json(['success' => false, 'message' => 'No vessel found.'], 404);
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function getBySearch()
    {
        $per_page = empty(request('per_page')) ? 10 : (int)request('per_page');
        $uids = Vessel::search(request()->query('query'))->get('id');
        $ids = array();
        foreach ($uids as $u) {
            $ids[] = $u->id;
        }
        $vessels = $this->staticSearch($this->getVesselModal()->whereIn('id', $ids), \request('staticSearch'))->paginate($per_page);
        $vessels = $this->vrpStats($vessels);
        return VesselResource::collection($vessels);
    }

    public function getBySearchWithVRP(Request $request)
    {

        $per_page = empty(request('per_page')) ? 10 : (int)request('per_page');
        $uids = Vessel::search(request()->query('query'))->get('id');
        $ids = array();
        foreach ($uids as $u) {
            $ids[] = $u->id;
        }
        $staticSearch = \request('staticSearch');
        $vesselModel = $this->staticSearch($this->getVesselModal()
                                ->latest()
                                ->whereIn('id', $ids)
                                ->with('Company:id,plan_number'), $staticSearch);
        $vessels = $vesselModel->get();
        $vessels = $this->vrpStats($vessels);

        $exclude_ids = [];

        foreach ($vessels as $vessel) {
            if ($vessel->vrp_count === 1) {
                $exclude_ids[] = $vessel->imo;
            }
        }

        //initiate paginator
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Create a new Laravel collection from the array data merged with VRPexpress search
        $itemCollection = collect($this->vrpSearch($vessels, $exclude_ids, request()->query('query'), $staticSearch['vrp_status']));

        // Slice the collection to get the items to display in current page
        $currentPageItems = $itemCollection->slice(($currentPage * $per_page) - $per_page, $per_page)->all();

        // Create our paginator and pass it to the view
        $paginatedItems = new LengthAwarePaginator(array_values($currentPageItems), count($itemCollection), $per_page);

        // set url path for generted links
        $paginatedItems->setPath($request->url());

        return $paginatedItems;
    }

// @todo: for removing smff data from user's who don't have smff data permission from backend
    private function getVesselModal ($fleet_id=null, $model=null) {
        if (!$model) {
            $model = Vessel::latest();
        }

        $userInfo = Auth::user();
        switch ($userInfo->role_id) {
            case Role::COMPANY_PLAN_MANAGER : // Company Plan Manager
                $companyIds = Auth::user()->companies()->pluck('id');
                $ids = [];
                foreach($companyIds as $companyId) {
                    $ids[] = $companyId;
                    $operatingCompany = Company::where('id', $companyId)->first()->operating_company_id;
                    $affiliateCompanies = Company::where('operating_company_id', $companyId)->get();
                    if(!$operatingCompany && isset($affiliateCompanies)) {
                        foreach($affiliateCompanies as $affiliateCompany)
                        {
                            $ids[] = $affiliateCompany->id;
                        }
                    }
                }
                return $model->whereIn('v1.active_field_id', [2, 3, 5])->whereIn('v1.company_id', $ids);
            case Role::QI_COMPANIES : // QI Companies
                return $model->whereIn('v1.active_field_id', [2, 3, 5])->join('vessels_vendors AS vv', 'v1.id', '=', 'vv.vessel_id')
                        ->whereIn('vv.company_id', Company::where([['id', $userInfo->primary_company_id], ['vendor_active', 1],['vendor_type', 3]])->pluck('id'));
            case Role::VESSEL_VIEWER : // Vessel viewer
                // return $model->whereIn('company_id', Company::where('qi_id', Auth::user()->vendor_id)->pluck('id'));
            case Role::COAST_GUARD : // Coast Guard
                return $model->whereIn('v1.active_field_id', [2, 3, 5])
                    // ->whereIn('v1.company_id', Company::whereIn('active', [1, 2])->pluck('id'))
                    ->whereIn('v1.plan_id', Plan::whereIn('active_field_id', [2, 3, 5])->pluck('id'));
            case Role::NAVY_NASA :
                return $model->whereIn('c1.id', Company::where('networks_active', 1)->orWhere('smff_service_id', '<>', 0)->pluck('id'));
            case Role::ADMIN : // falls through
            case Role::DUTY_TEAM :
                return $model;
        }

        if($fleet_id){
            return $model->whereHas('vessels_fleets', function($q) use ($fleet_id){
                $q->where('fleet_id', '=', $fleet_id);
            });
        }

        return null;
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function getByOrder()
    {
        $per_page = empty(request('per_page')) ? 10 : (int)request('per_page');
        $direction = request()->query('direction');
        $sortBy = request()->query('sortBy');
        $vessels = $this->staticSearch($this->getVesselModal()->orderBy($sortBy, $direction), \request('staticSearch'))->paginate($per_page);
        $vessels = $this->vrpStats($vessels);
        return VesselResource::collection($vessels);
    }

    public function getVesselsUnderPlan(Company $company)
    {
        ini_set('memory_limit','2048M');
        //include the VRP Express vessels under these companies
        //pass the plans, get the vessels
        $vessels = [];
        $plan_number = $company->plan_number;
        if ($plan_number) {
            try {
                $exclude_imo = Vessel::whereHas('company', static function ($q) use ($plan_number) {
                    $q->where('plan_number', $plan_number);
                })->pluck('imo');
            //    $client = new Client();
            //    $res = $client->request('POST', 'https://35.184.163.31/api/vessels/underPlan', ['json' => ['plan_number' => $plan_number, 'exclude_imo' => $exclude_imo], 'stream' => true, 'timeout' => 0, 'read_timeout' => 10]);
            //    $vessels = json_decode($res->getBody()->getContents(), true);
                $vessels = json_decode(json_encode(VRPExpressVesselHelper::getVesselsUnderPlan($plan_number, $exclude_imo)),true);
            } catch (\Exception $error) {

            }
        }
        return $vessels;
    }

    private function vrpStats($vessels)
    {
        try {
            $filteredVessels = $vessels->filter(function ($vessel, $key) {
                return $vessel->imo !== null;
            });
            $vessel_data = [];
            foreach ($filteredVessels as $filteredVessel) {
                $vessel_data[] = [
                    'id' => $filteredVessel->id,
                    'imo' => $filteredVessel->imo,
                    'plan_number' => $filteredVessel->company->plan_number,
                    'official_number' => $filteredVessel->official_number
                ];
            }
//            $client = new Client();
//            $res = $client->request('POST', 'http://35.184.163.31/api/vessels', ['json' => ['vessel_data' => $vessel_data], 'stream' => true, 'timeout' => 0, 'read_timeout' => 10]);
//            $vrp_data = json_decode($res->getBody()->getContents(), true);
            $vrp_data = json_decode(json_encode(VRPExpressVesselHelper::getVessels($vessel_data)),true);
//            return $vessel_data;
            foreach ($vessels as $vessel) {
                if ($vessel->imo !== null && isset($vrp_data[$vessel->id])) {
                    $vessel->vrp_status = $vrp_data[$vessel->id]['status'];
                    $vessel->vrp_comparison = $vrp_data[$vessel->id]['vrp_comparison'];
                    $vessel->vrp_plan_number = $vrp_data[$vessel->id]['plan_number'];
                    $vessel->vrp_vessel_is_tank = $vrp_data[$vessel->id]['vessel_is_tank'];
                    $vessel->vrp_count = $vrp_data[$vessel->id]['vrp_count'];
                    $vessel->plan_holder = $vrp_data[$vessel->id]['plan_holder'];
                    $vessel->vrp_express = false;
                    $vessel->primary_smff = $vrp_data[$vessel->id]['primary_smff'];
                }
            }
        } catch (\Exception $error) {
//            return $error->getMessage();
        }
        return $vessels;
    }

    private function vrpSearch($vessels, $exclude_ids, $query, $vrp_status)
    {
        $vrp_vessels = [];
        try {
//            $client = new Client();
//            $res = $client->request('POST', 'http://35.184.163.31/api/vessels/search', ['json' => ['query' => $query, 'exclude_ids' => $exclude_ids, 'vrp_status' => $vrp_status], 'stream' => true, 'timeout' => 0, 'read_timeout' => 10]);
//            $vrp_data = json_decode($res->getBody()->getContents(), true);
            $vrp_data = json_decode(json_encode(VRPExpressVesselHelper::getVesselsBySearch($query, $exclude_ids, $vrp_status)),true);
            foreach ($vrp_data as $vessel) {
                $vessel = (object)$vessel;
                $loop[] = $vessel->imo;
                $vrp_vessels[] = [
                    'id' => -1,
                    'imo' => $vessel->imo,
                    'official_number' => $vessel->official_number,
                    'company' => [
                        'plan_number' => '',
                        'id' => -1
                    ],
                    'vrp_status' => $vessel->vessel_status ?? '',
                    'vrp_comparison' => $vessel->vrp_comparison ?? 'N/A',
                    'vrp_plan_number' => $vessel->vrp_plan_number ?? '',
                    'vrp_vessel_is_tank' => $vessel->vessel_is_tank,
                    'vrp_count' => $vessel->vrp_count ?? 0,
                    'name' => $vessel->vessel_name,
                    'type' => $vessel->vessel_type,
                    'resource_provider' => false,
                    'active' => false,
                    'fleets' => [],
                    'vrp_express' => true,
                    'coverage'    => str_contains(strtolower($vessel->primary_smff), 'donjon') ? 1 : 0,
                ];
            }
        } catch (\Exception $error) {

        }
        $merged_vessels = [];
        foreach ($vessels as $vessel) {
            $merged_vessels[] = [
                'id' => $vessel->id,
                'imo' => $vessel->imo,
                'official_number' => $vessel->official_number,
                'company' => [
                    'plan_number' => trim($vessel->company->plan_number) ? $vessel->company->plan_number : '',
                    'id' => $vessel->company->id
                ],
                'vrp_status' => $vessel->vrp_status ?? '',
                'vrp_comparison' => $vessel->vrp_comparison ?? 'N/A',
                'vrp_plan_number' => $vessel->vrp_plan_number ?? '',
                'vrp_vessel_is_tank' => $vessel->vrp_vessel_is_tank,
                'vrp_count' => $vessel->vrp_count ?? 0,
                'name' => $vessel->name,
                'type' => $vessel->type ? $vessel->type->name : 'Unknown',
                'tanker' => (boolean)$vessel->tanker,
                'resource_provider' => $vessel->smffCapability ? true : false,
                'active' => (boolean)$vessel->active,
                'fleets' => $vessel->fleets()->pluck('fleets.id'),
                'vrp_express' => $vessel->vrp_express ? true : false,
                'response' => $vessel->smff_service_id ? 1 : 0,
                'coverage' => $vessel->active,
            ];
        }
        $merged_vessels = array_merge($merged_vessels, $vrp_vessels);
        return $merged_vessels;
    }

    public function addNote(Vessel $vessel, Request $request)
    {
        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        $this->validate($request, [
            'note_type' => 'required',
            'note' => 'required'
        ]);

        $note = $vessel->notes()->create([
            'note_type' => request('note_type'),
            'note' => request('note'),
            'user_id' => Auth::id()
        ]);

        if ($note) {
            return response()->json(['success' => true, 'message' => 'Note added.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function getNotes(Vessel $vessel)
    {
        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        return NoteResource::collection($vessel->notes()->orderBy('updated_at', 'desc')->get());
    }

    public function destroyNote(Vessel $vessel, $id)
    {
        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        if ($vessel->notes()->find($id)->delete()) {
            return response()->json(['success' => true, 'message' => 'Note deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function saveFleets(Vessel $vessel, Request $request)
    {
        if ($vessel->fleets()->sync(\request('fleets'))) {
            return response()->json(['success' => true, 'message' => 'Vessel Fleet saved.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function getUploadedFiles(Vessel $vessel, $location, $year)
    {
        $files = [];
        $company_id = isset($vessel->company()->first()->id) ? $vessel->company()->first()->id : null;

        $location == 'racs'
            ? $directory = 'files/new/' . $location . '/' . $vessel->id . '/' . $year . '/'
            : $directory = 'files/new/' . $location . '/' . $vessel->id . '/';
        $filesInFolder = Storage::disk('gcs')->files($directory);
        foreach ($filesInFolder as $path) {
            $files[] = [
                'name' => pathinfo($path)['basename'],
                'size' => $this->formatBytes(Storage::disk('gcs')->size($directory . pathinfo($path)['basename'])),
                'ext' => pathinfo($path)['extension'] ?? null,
                'created_at' => date("Y-m-d", Storage::disk('gcs')->lastModified($directory . pathinfo($path)['basename']))
            ];
        }
        return $files;
    }

    public function uploadVesselFiles(Vessel $vessel, $location, $year, Request $request)
    {
        $fileName = $request->file->getClientOriginalName();
        $location == 'racs'
            ? $directory = 'files/new/' . $location . '/' . $vessel->id . '/' . $year . '/'
            : $directory = 'files/new/' . $location . '/' . $vessel->id . '/';

        if (Storage::disk('gcs')->exists($directory . $fileName)) {
            $fileName = date('m-d-Y_h:ia - ') . $fileName;
        }
        if (Storage::disk('gcs')->putFileAs($directory, \request('file'), $fileName)) {
            return response()->json(['success' => true, 'message' => 'File uploaded.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function deleteSingleVesselFile(Vessel $vessel, $location, $year, $fileName)
    {
        $location == 'racs'
            ? $directory = 'files/new/' . $location . '/' . $vessel->id . '/' . $year . '/'
            : $directory = 'files/new/' . $location . '/' . $vessel->id . '/';
        if (Storage::disk('gcs')->delete($directory . $fileName)) {
            return response()->json(['success' => true, 'message' => 'File deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function deleteAllVesselFiles(Vessel $vessel, $location, $year, Request $request)
    {
        $removeData = $request->all();
        for($i = 0; $i < count($removeData); $i ++) {
            $location == 'racs'
                ? $directory = 'files/new/' . $location . '/' . $vessel->id . '/' . $year . '/'
                : $directory = 'files/new/' . $location . '/' . $vessel->id . '/';
            Storage::disk('gcs')->delete($directory . $removeData[$i]['name']);
        }
        return response()->json(['success' => true, 'message' => 'Files are deleted.']);
    }

    public function downloadVesselFile(Vessel $vessel, $location, $year, $fileName)
    {
        $location == 'racs'
            ? $directory = 'files/new/' . $location . '/' . $vessel->id . '/' . $year . '/'
            : $directory = 'files/new/' . $location . '/' . $vessel->id . '/';

        return response()->streamDownload(function() use ($directory, $fileName) {
            echo Storage::disk('gcs')->get($directory . $fileName);
        }, $fileName, [
                'Content-Type' => 'application/octet-stream'
            ]);
    }

    public function getFilesCount(Request $request)
    {
        $files = [];
        $vesselNames = [
            'prefire_plans',
            'drawings',
            'damage_stability_models',
            'racs',
            'prefire_plan_certification',
            'stability-booklet',
            'drills-and-exercises'
        ];
        foreach($vesselNames as $vesselName) {
            foreach ($request->ids as $id) {
                if ($id) {
                    $vessel = Vessel::find($id);
                    $vesselClassId = Vessel::where('id', $id)->first()->vessel_class_id;

                    if ($vesselName == 'racs') {
                        for ($i = 0; $i < 4; $i++) {
                            $vesselDirectory = 'files/new/' . $vesselName . '/' . $id . '/' . (string)(date("Y") - $i) . '/';
                            $filesInFolder = Storage::disk('gcs')->files($vesselDirectory);
                            $files[$vesselName]['vessel'][$id][(string)(date("Y") - $i)] = count($filesInFolder);
                        }
                    } else {
                        $vesselDirectory = 'files/new/' . $vesselName . '/' . $id . '/';
                        $filesInFolder = Storage::disk('gcs')->files($vesselDirectory);
                        $files[$vesselName]['vessel'][$id] = count($filesInFolder);

                        if($vesselClassId) {
                            $vesselClassDirectory = 'files/vessel_classes/' . $vesselClassId . '/' . $vesselName . '/';
                            $directories = Storage::disk('gcs')->files($vesselClassDirectory);
                            $files[$vesselName]['vessel_class'][$vesselClassId] = count($directories);
                        }

                    }
                }
            }
        }
        return response()->json($files);
    }

    private function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int)$size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(1024 ** ($base - floor($base)), $precision) . $suffixes[floor($base)];
        }

        return $size;
    }

    public function transferToCompany(Request $request)
    {
        $vessels = Vessel::whereIn('id', \request('vessel_ids'));
        foreach ($vessels->get() as $vessel) {
            if (\request('company_id') !== $vessel->company_id) {
                $this->vesselFilesToCompany($vessel, \request('company_id'));
            }
        }
        if ($vessels->update(['company_id' => \request('company_id')])) {
            return response()->json(['success' => true, 'message' => 'Success. ' . $vessels->count() . ' vessel(s) were assigned under a new company.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function bulkAction()
    {
        $company_id = \request('action')['company'];
        $insurers = \request('action')['insurers'];
        $vessels = Vessel::whereIn('id', \request('vessel_ids'));
        try {
            foreach ($vessels->get() as $vessel) {
                if ($company_id && $company_id !== $vessel->company_id) {
                    $this->vesselFilesToCompany($vessel, $company_id);
                }
                if (count($insurers)) {
                    $vessel->vendors()->detach($insurers);
                    $vessel->vendors()->attach($insurers);
                }
            }
            if ($company_id) {
                $vessels->update(['company_id' => $company_id]);
            }
            return response()->json(['success' => true, 'message' => 'Successfully updated ' . $vessels->count() . ' vessel(s).']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
    }

    private function vesselFilesToCompany($vessel, $company_id)
    {
        $vesselLocations = [
            'prefire_plans',
            'drawings',
            'damage_stability_models',
            date("Y"),
            date("Y") - 1,
            date("Y") - 2
        ];
        foreach ($vesselLocations as $location) {
            $folder = 'files/' . $location . '/' . $vessel->company_id . '/' . $vessel->id . '/';
            $files = Storage::disk('gcs')->files($folder);
            foreach ($files as $file) {
                Storage::disk('gcs')->move($file, 'files/' . $location . '/' . $company_id . '/' . $vessel->id . '/' . pathinfo($file)['filename']);
            }
        }
    }

    public function bulkImport()
    {
        $filename = storage_path('app/new_smff.csv');
        $file = fopen($filename, "r");
        while (($data = fgetcsv($file, 200, ',')) !== FALSE) {
            $id = $data[0];
            $s_heavy_lift = $data[1];
            $s_lifting_gear_minimum_swl = $data[2];
            //create smff for each vessel
            $load_data = [
                's_heavy_lift' => $s_heavy_lift,
                's_lifting_gear_minimum_swl' => $s_lifting_gear_minimum_swl
            ];
            $vessel = Vessel::where('id', $id)->withCount('smffCapability')->first();
            if ($vessel->smff_capability_count === 0) {
                $capability = Capability::create($load_data);
                $vessel->smff_service_id = $capability->id;
                $vessel->save();
            } else {
                $vessel->smffCapability()->update($load_data);
            }
        }
        fclose($file);
    }

    // store csv
    public function storeCSV(Request $request,Vessel $vessel)
    {
        if($request->hasFile('file'))
        {
            $path = $request->file('file')->getPathName();
            $csvFile = fopen($path, 'r');
            $total = 0;
            $dup_imos = [];
            $dup_officials = [];
            $first = 0;
            $vesselIds = [];
            $to_be_added = [];
            while(($row = fgetcsv($csvFile)) !== FALSE)
            {
                if ($first < 3) {
                    $first++;
                    continue;
                } else {
                    // Check all values in the last row are empty
                    if (!empty(array_filter($row, function ($value) { return $value != ""; }))) {
                        if ( (int)$row[1] != 0 || (int)$row[2] != 0 ) {
                            if($row[1] != "") {
                                if (Vessel::where('imo', $row[1])->first()) {
                                    array_push($dup_imos, $row[1]);
                                    continue;
                                }
                            }
                            if($row[2] != "") {
                                if (Vessel::where('official_number', $row[2])->first()) {
                                    array_push($dup_officials, $row[2]);
                                    continue;
                                }
                            }
                        }
                        $to_be_added[] = [
                            'name' => $row[0],
                            'imo' => (int)$row[1] != 0 ? (int)$row[1] : NULL,
                            'official_number' => (int)$row[2] != 0 ? (int)$row[2] : NULL,
                            'company_id' => (int)$row[3],
                            'sat_phone_primary' => $row[4],
                            'sat_phone_secondary' => $row[5],
                            'email_primary' => $row[6],
                            'email_secondary' => $row[7],
                            'vessel_type_id' => (int)$row[8],
                            'society' => $row[9],
                            'pi_club' => $row[10],
                            'hm_insurer' => $row[11],
                            'damage_stability' => $row[12],
                            'oil_group' => $row[13],
                            'dead_weight' => $row[14],
                            'deck_area' => $row[15],
                            'oil_tank_volume' => $row[16],
                            'tanker' => $row[17] == "YES" ? 1 : 0,
                            'active' => $row[18] == "YES" ? 1 : 0,
                        ];
                    } else {
                        break;
                    }
                }
            }
            for ($i = 0; $i < count($to_be_added); $i++) {
                $vessel = new Vessel();
                $vessel->name = $to_be_added[$i]['name'];
                $vessel->imo = $to_be_added[$i]['imo'];
                $vessel->official_number = $to_be_added[$i]['official_number'];
                $vessel->company_id = $to_be_added[$i]['company_id'];
                $vessel->sat_phone_primary = $to_be_added[$i]['sat_phone_primary'];
                $vessel->sat_phone_secondary = $to_be_added[$i]['sat_phone_secondary'];
                $vessel->email_primary = $to_be_added[$i]['email_primary'];
                $vessel->email_secondary = $to_be_added[$i]['email_secondary'];
                $vessel->vessel_type_id = $to_be_added[$i]['vessel_type_id'];
                $vessel->dead_weight = $to_be_added[$i]['dead_weight'];
                $vessel->deck_area = $to_be_added[$i]['deck_area'];
                $vessel->oil_tank_volume = $to_be_added[$i]['oil_tank_volume'];
                $vessel->oil_group = $to_be_added[$i]['oil_group'];
                $vessel->tanker = $to_be_added[$i]['tanker'];
                $vessel->active = $to_be_added[$i]['active'];
                $vessel->ais_timestamp = '0000-00-00 00:00:00';

                if($vessel->save()) {
                    $vendorCompanies = $to_be_added[$i]['society'] . ',' . $to_be_added[$i]['pi_club'] . ',' . $to_be_added[$i]['hm_insurer'] . ',' . $to_be_added[$i]['damage_stability'];
                    $vendorCompanies = explode(",", $vendorCompanies);

                    foreach($vendorCompanies as $vendorCompany)
                    {
                        $company_id = intval($vendorCompany);
                        if(Company::where('id', $company_id)->first()) {
                            VesselVendor::create([
                                'vessel_id' => $vessel->id,
                                'company_id' => $company_id,
                            ]);
                        }
                    }

                    $total++;
                    $vesselIds[] = $vessel->id;
                } else {
                    return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
                }
            }
            $ids = '';
            foreach($vesselIds as $vesselId)
            {
                $ids .= $vesselId.',';
            }
            $ids = substr($ids, 0, -1);
            TrackChange::create([
                'changes_table_name_id' => 2,
                'action_id' => 1,
                'count' => $total,
                'ids' => $ids,
            ]);
            if (count($dup_imos) || count($dup_officials)) {
                $message = '';
                if (count($dup_imos) && count($dup_officials)) $message = 'Duplicate IMOs are '.join(', ', $dup_imos).' and '.'Duplicate Official Numbers are '.join(', ', $dup_officials);
                else if (count($dup_imos)) $message = 'Duplicate IMOs are '.join(', ', $dup_imos);
                else if (count($dup_officials)) $message = 'Duplicate Official Numbers are '.join(', ', $dup_officials);
                return response()->json(['success' => 'warning', 'message' => $message]);
            }
            return response()->json(['success' => 'success', 'message' => $total.' Vessels are added.']);
        }
        return response()->json(['success'=> 'error', 'message' => 'File not found.']);
    }
    // end store csv

    // Get duplicated vessel with IMO
    public function getDuplicateIMOVessel($number, $flag)
    {
        if ($number && (int)$number != 0) {
            $flag
                ? $duplicates = Vessel::where('imo', $number)->first()
                : $duplicates = Vessel::where('official_number', $number)->first();
            if ($duplicates) {
                return response()->json(['success' => false]);
            }
        }
        return response()->json(['success' => true]);
    }

    public function getVesselInfo() {
        return response()->json(Vessel::select('id', 'imo', 'name')->get());
    }

    public function getVRPdata($id)
    {
        try {
            $vrp_data = null;
            $vrp_data = json_decode(json_encode(VRPExpressVesselHelper::getVesselsUnderPlanById($id)), true);
        } catch (\Exception $error) {
        }

        return $vrp_data;
    }

    // return latest positions from vessel_ais_positions table
    public function getLatestAISPositions(Request $request)
    {
        $search = $request['search'];
        $perPage = $request['per-page'] ?? 10;
        $page = $request['page'] ?? 1;
        $orderBy = $request['sort-by'] ?? 'ais_timestamp';
        $orderDir = $request['direction'] ?? 'desc';

        $vesselsQuery = DB::table('vessels as v1')
            ->select('v1.id', 'v1.name', 'v1.ais_lat', 'v1.ais_long', 'v1.ais_timestamp')
            ->join('vessel_ais_positions as vap', 'v1.id', '=', 'vap.vessel_id')
            ->distinct()
            ->leftJoin('vessel_types as t', 'v1.vessel_type_id', '=', 't.id')
            ->leftJoin('companies as c1', 'v1.company_id', '=', 'c1.id')
            ->leftJoin('capabilities as vs', function($join) {
                $join->on('v1.smff_service_id', '=', 'vs.id');
                $join->on('vs.status', '=', DB::raw('1'));
            });

        if (!empty($search) && strlen($search) > 2) {
            $ids = Vessel::search($search)->get('id')->pluck('id');
            $vesselsQuery = $vesselsQuery->whereIn('v1.id', $ids);
        }

        if ($request->has('staticSearch')) {
            $this->staticSearch($vesselsQuery, $request['staticSearch']);
        }

        $total = count($vesselsQuery->get());

        $vessels = $vesselsQuery
            ->orderBy($orderBy, $orderDir)
            ->forPage($page, $perPage)
            ->get();

        foreach ($vessels as $vessel) {
            $latest = VesselAISPositions
                ::where([
                    ['vessel_id', $vessel->id],
                    ['timestamp', $vessel->ais_timestamp],
                ])
                ->first();
            $vessel->ais_dsrc = $latest['dsrc'];
        }

        return response()->json([
            'data' => $vessels,
            'total' => $total,
        ]);
    }

    // Sister Vessel Csv file import
    public function sisterVesselImport(Request $request)
    {
        if($request->hasFile('file'))
        {
            $path = $request->file('file')->getPathName();
            $csvFile = fopen($path, 'r');
            $first = 0;
            $imos = [];
            $leadShipId = 0;
            $existImo = '';

            while(($row = fgetcsv($csvFile)) !== FALSE)
            {
                if ($first < 1) {
                    $first++;
                    continue;
                } else {
                    if($row[1] == 1) {
                        $leadShipId = Vessel::where('imo', $row[0])->first()->id;
                        Vessel::where('id', $leadShipId)->update([
                            'lead_ship' => 1
                        ]);
                    } else {
                        $imos[] = $row[0];
                    }
                }
            }

            if($leadShipId) {
                foreach($imos as $imo)
                {
                    if(Vessel::where('imo', $imo)->first()) {
                        Vessel::where('imo', $imo)->update([
                            'lead_sister_ship_id' => $leadShipId
                        ]);
                    } else {
                        if($existImo == '') {
                            $existImo = $existImo . $imo;
                        } else {
                            $existImo = $existImo . ', ' . $imo;
                        }
                    }
                }
                if($existImo == '') {
                    // upload success
                    return response()->json(['success' => 'success', 'message' => 'Vessels are updated.']);
                } else {
                    // warning
                    return response()->json(['success' => 'warning', 'message' => 'Doesn\'t exist vessels: ' . $existImo]);
                }
            } else {
                // upload error
                return response()->json(['success' => 'error', 'message' => ' Import Failed: Lead Ship does not exist.']);
            }
        }
    }

    public function updateVesselClass(Vessel $vessel, Request $request)
    {
        $vessel->vessel_class_id = request('vessel_class_id');
        if($vessel->save()) {
            if(request('vessel_class_id')) {
                return response()->json(['success' => true, 'message' => 'Vessel Class Updated.']);
            }
            return response()->json(['success' => true, 'message' => 'Vessel removed From This Vessel Class.']);
        }
        return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
    }

    public function updateTags(Vessel $vessel, Request $request)
    {
        $vessel->company_id = request('company_id');
        $vessel->plan_id = request('plan_id');

        if($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Vessel Company and Plan updated.']);
        }

        return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
    }

    public function bulkUpdate(Request $request)
    {
        $vesselDatas = request('vesselData');

        foreach($vesselDatas as $vesselData)
        {
            $vessel = Vessel::find($vesselData['id']);
            $vessel->name = $vesselData['name'];
            $vessel->imo = $vesselData['imo'];
            $vessel->official_number = $vesselData['official_number'];
            $vessel->vessel_type_id = $vesselData['vessel_type_id'];
            $vessel->dead_weight = $vesselData['dead_weight'];
            $vessel->tanker = $vesselData['tanker'];
            $vessel->active_field_id = $vesselData['active_field_id'];
            $vessel->deck_area = $vesselData['deck_area'];
            $vessel->oil_tank_volume = $vesselData['oil_tank_volume'];
            $vessel->oil_group = $vesselData['oil_group'];
            $vessel->company_id = $vesselData['company'] ? $vesselData['company']['id'] : NULL;
            $vessel->plan_id = $vesselData['plan'] ? $vesselData['plan']['id'] : NULL;
            $vessel->gross_tonnage = $vesselData['gross_tonnage'];

            if ($vessel->save()) {
                $vessel->vendors()->detach();
                $vessel->vendors()->attach(array_merge(
                    $vesselData['qi'],
                    $vesselData['pi'],
                    $vesselData['societies'],
                    $vesselData['insurers'],
                    $vesselData['providers']
                ));

                $vesselIds = [];
                $vesselIds[] = $vessel->id;
                $ids = '';
                foreach($vesselIds as $vesselId)
                {
                    $ids .= $vesselId.',';
                }
                $ids = substr($ids, 0, -1);
                TrackChange::create([
                    'changes_table_name_id' => 2,
                    'action_id' => 3,
                    'count' => 1,
                    'ids' => $ids,
                ]);
            }
        }
        return response()->json(['success' => true, 'message' => 'Vessels updated.']);
    }


    public function removeVesselFromPlan(Vessel $vessel)
    {
        $vessel->plan_id = NULL;
        if($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Vessel removed from Plan.']);
        }

        return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
    }

    public function updateVesselFromCompany(Vessel $vessel)
    {
        $vessel->company_id = request('company_id');
        if($vessel->save()) {
            return response()->json(['success' => true, 'message' => 'Vessel removed from Company.']);
        }

        return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
    }

    public function updateVesselFromGroup(Vessel $vessel)
    {
        $vessel->vessel_billing_group_id = request('vessel_billing_group_id');
        if($vessel->save()) {
            return request('vessel_billing_group_id') ? response()->json(['success' => true, 'message' => 'Vessel added to Group.']) : response()->json(['success' => true, 'message' => 'Vessel removed from Group.']);
        }

        return response()->json(['success'=> false, 'message' => 'Something unexpected happened.']);
    }

    public function getVessels(Request $request)
    {
        $vrpDBName = Config::get('database.connections')['mysql_vrp']['database'];

        $query = $request->get('query');

        $results = Vessel::from('vessels as v')
                        ->select('v.id', 
                        'v.name as name', 
                        'v.ex_name as ex_name',
                        'vessels_data.vessel_status as vrp_vessel_status', 
                        'vessels_data.vessel_name as vrp_vessel_name',
                        'v.imo as imo', 
                        'v.official_number as official_number', 
                        'v.active_field_id as active_field_id', 
                        'v.tanker as tanker', 
                        'vt.name as vessel_type', 
                        'vessels_data.id as vrp_id',
                        'vessels_data.vessel_type as vrp_vessel_type', 
                        'p.plan_number as plan_number', 
                        'vrp_plan.plan_number as vrp_plan_number',
                        'vrp_plan.status as vrp_plan_status',
                        'c.id as company_id',
                        'c.name as company_name', 
                        'c.networks_active as networks_active',
                        'v.updated_at as updated_at')
                        ->join('plans as p', 'v.plan_id', '=', 'p.id', 'left outer')
                        ->join('vessel_types as vt', 'v.vessel_type_id', '=', 'vt.id', 'left outer')
                        ->join('companies as c', 'v.company_id', '=', 'c.id', 'left outer')
                        ->leftjoin(DB::raw("(" . $vrpDBName . ".vessels_data LEFT OUTER JOIN " . $vrpDBName . ".vrp_plan ON " . $vrpDBName . ".vessels_data.plan_number_id = " . $vrpDBName . ".vrp_plan.id)"), function($join) {
                            $join->on('v.imo', '=', Config::get('database.connections')['mysql_vrp']['database'] . '.vessels_data.imo');
                            $join->on('p.plan_number', '=', Config::get('database.connections')['mysql_vrp']['database'] . '.vrp_plan.plan_number');
                        })
                        ->where('v.deleted_at', NULL)
                        ->orderBy('v.updated_at', 'DESC');

        if (!empty($query) && strlen($query) > 2) {
            $vesselIds = Vessel::search($query)->get('id')->pluck('id');
            $results->whereIn('v.id', $vesselIds);
        }

        $perPage = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');

        return $results->paginate($perPage);

        // return $result = DB::select(DB::raw("SELECT 
        //                         vessels.id AS 'vessel_id',
        //                         vessels_data.vessel_status AS 'vrp_vessel_status',
        //                         vessels.name AS 'vessel_name',
        //                         vessels_data.vessel_name AS 'vrp_vessel_name',
        //                         vessels.imo AS 'imo',
        //                         vessels_data.imo AS 'vrp_imo',
        //                         vessels.official_number AS 'official_number',
        //                         vessels_data.official_number AS 'vrp_official_number',
        //                         vessels.active_field_id AS 'status',
        //                         vessels.tanker AS 'tanker',
        //                         vessel_types.name AS 'vessel_type',
        //                         vessels_data.vessel_type AS 'vrp_vessel_type',
        //                         plans.plan_number AS 'plan_number',
        //                         vrp_plan.plan_number AS 'vrp_plan_number',
        //                         vrp_plan.status AS 'vrp_plan_status',
        //                         companies.id AS 'company_id',
        //                         companies.name AS 'company_name',
        //                         vessels.updated_at
        //                     FROM
        //                         vessels
        //                             LEFT OUTER JOIN
        //                         plans ON vessels.plan_id = plans.id
        //                             LEFT OUTER JOIN
        //                         vessel_types ON vessels.vessel_type_id = vessel_types.id
        //                         LEFT OUTER JOIN
        //                         companies ON vessels.company_id= companies.id
        //                             LEFT OUTER JOIN
        //                         (" . $vrpDBName . ".vessels_data
        //                         LEFT OUTER JOIN " . $vrpDBName . ".vrp_plan ON " . $vrpDBName . ".vessels_data.plan_number_id = " . $vrpDBName . ".vrp_plan.id) ON (vessels.imo = " . $vrpDBName . ".vessels_data.imo
        //                             AND plans.plan_number = " . $vrpDBName . ".vrp_plan.plan_number)
        //                     WHERE
        //                         vessels.deleted_at IS NULL
        //                     ORDER BY updated_at DESC;"));
    }

    public function bulkDestroy(Request $request)
    {
        foreach(request('ids') as $id)
        {
            Vessel::where('id', $id)->delete();
        }

        return response()->json(['success'=> true, 'message' => 'Success'], 200);
    }

}
