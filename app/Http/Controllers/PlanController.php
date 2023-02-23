<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\Plan;
use App\Models\AddressType;
use App\Models\CompanyAddress;
use App\Models\Vrp\VrpPlan;
use App\Models\Vrp\Vessel as VrpVessel;
use App\Models\Capability;
use App\Models\Country;
use App\Models\CompanyNotes;
use App\Models\CompanyUser;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use stdClass;
use Config;
use DateTime;

use App\Http\Resources\PlanResource;
use App\Http\Resources\PlanShowResource;
use App\Http\Resources\PlanAddressResource;
use App\Http\Resources\PlanShortResource;
use App\Http\Resources\CompanyNoteResource;
use App\Http\Resources\VendorShortResource;
use App\Http\Resources\CompanyContactShortResource;

use App\Helpers\VRPExpressCompanyHelper;

ini_set('memory_limit', '-1');

class PlanController extends Controller
{
    //
    public function getAll(Request $request)
    {
        $sort = $request->has('sortBy') ? $request->get('sortBy') : 'updated_at';
        $sortDir = $request->has('direction') ? $request->get('direction') : 'desc';

        $query = $request->get('query');

        $vrp = Auth::user()->hasVRP();

        $vesselTable = new VrpVessel;
        $vesselTableName = $vesselTable->table();

        $cdtDBName = Config::get('database.connections')['mysql']['database'];

        $plans = Plan::from('plans as p')
                    ->select(DB::raw((empty($vrp)) ? Plan::NON_VRP_FIELDS_PLAN : Plan::FIELDS_PLAN))
                    ->leftJoin('companies as c', 'p.company_id', '=', 'c.id')
                    ->leftJoin('capabilities as cs', 'c.smff_service_id', '=', 'cs.id')
                    ->whereRaw('(cs.status IS NULL OR cs.status=1 OR cs.status=0)')
                    ->whereNull('p.deleted_at');

        $plans = $this->getPlanModal($plans);

        if (request('staticSearch')) {
            $this->staticSearch($plans, request('staticSearch'));
        }

        if(empty($vrp)) {
            if (!empty($query) && strlen($query) > 2) {
                if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', $query, $specialChar)) {
                    $strings = explode($specialChar[0], $query);
                    if(strlen($strings[0]) > 1) {
                        $uids = Plan::where([['plan_holder_name', 'like', '%' . $strings[0] . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $plans = $plans->whereIn('p.id', $uids);
                    } else {
                        $uids = Plan::where([['plan_holder_name', 'like', $strings[0] . ' ' . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->Orwhere([['plan_holder_name', 'like', $strings[0] . '-' . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $plans = $plans->whereIn('p.id', $uids);
                    }
                } else {
                    $uids = Plan::search($query)->get('id')->pluck('id');
                    $plans = $plans->whereIn('p.id', $uids);
                }
            }

            $resultsQuery = $plans->orderBy($sort, $sortDir);
            
        } else {
            $planTable = new VrpPlan;
            $planTableName = $planTable->table();

            $plans->leftJoin($planTableName. ' as vp', 'p.plan_number', '=', 'vp.plan_number');

            if (!empty($query) && strlen($query) > 2) {
                $vrpPlans = VrpPlan::from($planTableName . ' as vp2')->select(DB::raw(Plan::UNION_FIELDS_PLAN))
                    ->whereRaw('vp2.plan_number NOT IN (SELECT p0.plan_number FROM ' . $cdtDBName . '.plans AS p0 WHERE p0.plan_number IS NOT NULL)');

                if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', $query, $specialChar)) {
                    $strings = explode($specialChar[0], $query);
                    if(strlen($strings[0]) > 1) {
                        $uids = Plan::where([['plan_holder_name', 'like', '%' . $strings[0] . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $plans = $plans->whereIn('p.id', $uids);
                        $uids = VrpPlan::where([['plan_holder', 'like', '%' . $strings[0] . '%'], ['plan_holder', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $vrpPlans = $vrpPlans->whereIn('vp2.id', $uids);
                    } else {
                        $uids = Plan::where([['plan_holder_name', 'like', $strings[0] . ' ' . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->Orwhere([['plan_holder_name', 'like', $strings[0] . '-' . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $plans = $plans->whereIn('p.id', $uids);
                        $uids = VrpPlan::where([['plan_holder', 'like', $strings[0] . ' ' . '%'], ['plan_holder', 'like', '%' . $strings[1] . '%']])->Orwhere([['plan_holder', 'like', $strings[0] . '-' . '%'], ['plan_holder', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                        $vrpPlans = $vrpPlans->whereIn('vp2.id', $uids);
                    }
                } else {
                    $uids = Plan::search($query)->get('id')->pluck('id');
                    $plans = $plans->whereIn('p.id', $uids);
                    $uids = VrpPlan::search($query)->get('id')->pluck('id');
                    $vrpPlans = $vrpPlans->whereIn('vp2.id', $uids);
                }
            } else {
                request('staticSearch')['merge'] == -1 ?
                $vrpPlans = VrpPlan::from($planTableName . ' as vp2')
                            ->select(DB::raw(Plan::UNION_FIELDS_PLAN))
                            ->whereRaw('vp2.plan_number NOT IN (SELECT p.plan_number FROM ' . $cdtDBName . '.plans AS p WHERE p.plan_number IS NOT NULL)') :

                $vrpPlans = VrpPlan::from($planTableName . ' as vp2')->select(DB::raw(Plan::UNION_FIELDS_PLAN));
            }

            if ($request->has('staticSearch')) {
                $this->staticSearchVrpPlans($vrpPlans, $request->get('staticSearch'));
            }

            $resultsQuery =
                $plans
                    ->union($vrpPlans)
                    ->orderBy($sort, $sortDir);
        }

        $perPage = request('per_page') == -1  ? count($resultsQuery->get()) : request('per_page');
        $results = PlanResource::collection($resultsQuery->paginate($perPage));

        return $results;
    }

    private function staticSearch($model, $staticSearch)
    {
        if ($staticSearch['active_field_id'] !== -1) {
            $model = $model->where('p.active_field_id', $staticSearch['active_field_id']);
        }

        if (array_key_exists('vrp_status', $staticSearch) && $staticSearch['vrp_status'] !== -1) {
            $statusSearch = $staticSearch['vrp_status'] ? 'Authorized' : 'Not Authorized';
            $model = $model->where('vp.status', $statusSearch);
        }

        if (array_key_exists('resource_provider', $staticSearch) && $staticSearch['resource_provider'] !== -1) {
            $model = $staticSearch['resource_provider'] ? $model->whereNotNull('cs.id') : $model->whereNull('cs.id');
        }

        if (array_key_exists('networks', $staticSearch) && count($staticSearch['networks'])) {
            $model = $model
                    ->where('c.networks_active', 1) //->orWhere('c1.smff_service_id', '<>', 0)
                    ->join('network_companies AS nc', 'c.id', '=', 'nc.company_id')
                    ->whereIn('nc.network_id', $staticSearch['networks']);
        }

        if (array_key_exists('company', $staticSearch)) {
            $model = $model->where('p.company_id', $staticSearch['company']);
        }

        if (array_key_exists('qi', $staticSearch)) {
            $model = $model->where('p.qi_id', $staticSearch['qi']);
        }

        if (array_key_exists('plan_preparer', $staticSearch)) {
            $model = $model->where('p.plan_preparer_id', $staticSearch['plan_preparer']);
        }

        if(array_key_exists('plan_number', $staticSearch)) {
            $model = $staticSearch['plan_number'] ? $model->whereNull('p.plan_number') : $model;
        }

        return $model;
    }

    private function staticSearchVrpPlans($model, $staticSearch)
    {
        if((array_key_exists('include_vrp', $staticSearch) && $staticSearch['include_vrp'] !== -1) || 
            (array_key_exists('company', $staticSearch))) {
            $model->whereRaw('0=1');
        }
        return $model;
    }

    private function getPlanModal($model)
    {
        $userInfo = Auth::user();
        switch ($userInfo->role_id) {
            case Role::COMPANY_PLAN_MANAGER : // Company Plan Manager
                $companyIds = Auth::user()->companies()->pluck('id');
                $ids = [];
                foreach($companyIds as $companyId) {
                    // $ids[] = $companyId;
                    $operatingCompany = Company::where('id', $companyId)->first()->operating_company_id;
                    if($operatingCompany) {
                        $affiliateCompanies = Company::where('operating_company_id', $operatingCompany)->get();
                        if(isset($affiliateCompanies)) {
                            foreach($affiliateCompanies as $affiliateCompany)
                            {
                                $ids[] = $affiliateCompany->id;
                            }
                            return $model = $model->whereIn('p.company_id', $ids);   
                        }
                    }
                }

                return $model = $model->whereIn('p.company_id', $companyIds);   
            case Role::QI_COMPANIES : // QI Companies
                return $model = $userInfo->primary_company_id ? $model->where('p.qi_id', $userInfo->primary_company_id)->whereIn('c.active_field_id', [2, 3, 5])
                    : $model->where('p.qi_id', -1)->whereIn('p.active_field_id', [2, 3, 5]);
        }
        return $model;
    }

    public function show($id)
    {
        $plan = Plan::where('id', $id)->first();
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $plan = Plan::select(DB::raw(Plan::DETAIL_PLAN))
                ->from('plans as p')
                ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
                ->leftJoin('capabilities as cs', function($join) {
                    $join->on('c.smff_service_id', '=', 'cs.id');
                    $join->on('cs.status', '=', DB::raw('1'));
                })
                ->where('p.id', $id)
                ->whereNull('p.deleted_at');

        return PlanShowResource::collection($plan->get());
    }

    public function planAddresses(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $types = AddressType::select('id', 'name')->get();
        foreach ($types as $type) {
            $type->addresses = PlanAddressResource::collection(CompanyAddress::where([['company_id', $plan->company_id], ['address_type_id', $type->id]])->get());
        }

        return $types;
    }

    public function getShort($id)
    {
        return PlanShortResource::collection(Plan::where('id', $id)->get())[0];
    }

    public function getVRPData($planNumber)
    {
        try {
            $vrpData = null;
            $vrpData = json_decode(json_encode(VRPExpressCompanyHelper::getCompaniesByPlan($planNumber)), true);
        } catch (\Exception $error) {
            
        }

        return $vrpData;
    }

    public function showSMFF(Plan $plan)
    {
        $company = Company::where('id', $plan->company_id)->first();
        $smff_service_id = $company->smff_service_id;
        $smff = $company->smff();
        return response()->json([
            'smff' => $smff,
            'networks' => $company->networks->pluck('code'),
            'serviceItems' => Capability::primaryServiceAvailable()
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        $company = Company::find($plan->company_id);
        if ($request->has('plan_holder_name')) $plan->plan_holder_name = request('plan_holder_name');
        if ($request->has('plan_number')) $plan->plan_number = request('plan_number');
        if ($request->has('qi_id')) $plan->qi_id = request('qi_id');
        if ($request->has('active_field_id')) $plan->active_field_id = request('active_field_id');
        if($request->has('plan_preparer_id')) $plan->plan_preparer_id = request('plan_preparer_id');
        if($plan->save()) {
            if ($request->has('email')) $company->email = request('email');
            if ($request->has('fax')) $company->fax = request('fax');
            if ($request->has('website')) $company->website = request('website');
            if ($request->has('description')) $company->description = request('description');
            if ($request->has('phone')) $company->phone = request('phone');
            if($request->has('operating_company_id')) $company->operating_company_id = request('operating_company_id');
            if ($request->has('company_poc_id')) $company->company_poc_id = request('company_poc_id');
            if ($request->has('shortname')) $company->shortname = request('shortname');

            $company->save();
            return response()->json(['success' => true, 'message' => 'Plan updated.']);
        }

        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function destroy(Plan $plan)
    {
        CompanyNotes::where('plan_id', $plan->id)->delete();
        Vessel::where('plan_id', $plan->id)->update([
            'plan_id' => NULL
        ]);

        $plan->deleted_at = new DateTime();

        return $plan->save() ? response()->json(['success' => true, 'message' => 'Plan deleted.']) : response()->json(['success' => false, 'message' => 'Could not delete plan.']);
    }

    public function storeAddress(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }
        
        if ($plan->addresses()->create(['company_id' => $plan->company_id, 'street' => '', 'address_type_id' => \request('type_id')])) {
            return response()->json(['success' => true, 'message' => 'Plan address added.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function updateAddress(CompanyAddress $address,Request $request)
    {
        if ($request['street'] || $request['city']) {
            $geocoder = app('geocoder')->geocode(request('street') . ' ' . request('city') . ' ' . request('state') . ' ' . request('country') . ' ' . request('zip'))->get()->first();
            if ($geocoder) {
                $coordinates = $geocoder->getCoordinates();
                $address['latitude'] = $coordinates->getLatitude();
                $address['longitude'] = $coordinates->getLongitude();
            }
        }
        $address['street'] = isset($request['street']) ? $request['street'] : $address['street'];
        $address['unit'] = isset($request['unit']) ? $request['unit'] : $address['unit'];
        $address['address_type_id'] = isset($request['address_type_id']) ? $request['address_type_id'] : $address['address_type_id'];
        $address['city'] = isset($request['city']) ? $request['city'] : $address['city'];
        $address['zip'] = isset($request['zip']) ? $request['zip'] : $address['zip'];
        $address['province'] = isset($request['province']) ? $request['province'] : $address['province'];

        $address['co'] = isset($request['co']) ? $request['co'] : $address['co'];
        $address['document_format'] = isset($request['document_format']) ? $request['document_format'] : $address['document_format'];
        $address['country'] = isset($request['country']) ? $request['country'] : $address['country'];
        $address['state'] = isset($request['state']) ? $request['state'] : $address['state'];
        $address['phone'] = isset($request['phone']) ? $request['phone'] : $address['phone'];
        $address['zone_id'] = getGeoZoneID($address['latitude'], $address['longitude']);
        if ($address->save()) {
           return response()->json(['success' => true, 'message' => 'Plan address saved.']);//+ $cont
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function destroyAddress(CompanyAddress $address)
    {
        if ($address->delete()) {
            return response()->json(['success' => true, 'message' => 'Plan address deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function getNotes(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        return CompanyNoteResource::collection($plan->notes()->orderBy('updated_at', 'desc')->get());
    }

    public function addNote(Plan $plan, Request $request)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        $this->validate($request, [
            'note_type' => 'required',
            'note' => 'required'
        ]);

        $note = $plan->notes()->create([
            'note_type' => request('note_type'),
            'note' => request('note'),
            'user_id' => Auth::id()
        ]);

        if ($note) {
            return response()->json(['success' => true, 'message' => 'Note added.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function destroyNote(Plan $plan, $id)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        if (!Auth::user()->isAdminOrDuty()) {
            return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
        }
        if ($plan->notes()->find($id)->delete()) {
            return response()->json(['success' => true, 'message' => 'Note deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function store(Request $request)
    {
        $duplicatePlanNumber = Plan::where('plan_number', request('plan_number'))->first();
        if($duplicatePlanNumber && request('plan_number')) {
            if(!request('permitted')) {
                return response()->json(['success' => false, 'message' => 'Plan Number Duplicated.']);
            }
        }

        $plan = new Plan();
        $plan->company_id = request('company_id');
        $plan->plan_number = request('plan_number');
        $plan->plan_holder_name = request('plan_holder_name');
        $plan->plan_preparer_id = request('plan_preparer_id');
        $plan->qi_id = request('qi_id');
        $plan->active_field_id = request('active_field_id');
        $plan->deleted = 0;
        if($plan->save()) {
            $message = 'Created Entry for ' . request('plan_holder_name') . '.';
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

            $note = $plan->notes()->create([
                'note_type' => 1,
                'note' => $message,
                'user_id' => Auth::id()
            ]);

            return response()->json(['success' => true, 'message' => 'Plan added.'], 200);
        }  
    }

    // Get Duplicate Plan Number
    public function getDuplicatePlanNumber($planNumber)
    {
        if ($planNumber && (int)$planNumber != 0) {
            if (Plan::where('plan_number', $planNumber)->first()) {
                return response()->json(['success' => false]);
            }
        }
        return response()->json(['success' => true]);
    }

    public function toggleStatus(Plan $plan, Request $request)
    {
        $plan->active_field_id = (int)request('active_field_id');

        if ($plan->save()) {
            switch ((int)request('active_field_id')) {
                case 1:
                    $message = 'Deactivated DJ-S coverage.';
                    $this->planInActiveCascade($plan);
                break;
                case 2:
                    $message = 'Activated DJ-S coverage.';
                break;
                case 3:
                    $message = 'Activated DJ-S A coverage.';
                break;
                case 4:
                    $message = 'Deactivated DJ-S A coverage.';
                    $this->planInActiveCascade($plan);
                break;
                case 5:
                    $message = 'Activated DJS and DJS-A coverage.';
                break;
            }

            $note = $plan->notes()->create([
                'note_type' => 1,
                'note' => $message,
                'user_id' => Auth::id()
            ]);
            return response()->json(['success' => true, 'message' => $message]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    private function planInActiveCascade($plan)
    {
        $vessels = $plan->vessels()->get();
        foreach($vessels as $vessel)
        {
            if($vessel->active_field_id == 2) {
                $vessel->active_field_id = 1;
            } else if($vessel->active_field_id == 3) {
                $vessel->active_field_id = 4;
            }

            $vessel->save();
        }

        return true;
    }

    public function getPlanLists(Request $request)
    {
        $plans = Plan::from('plans as p')->select('id', 'plan_holder_name as name', 'plan_number');

        if (!empty(request('name')) && strlen(request('name')) > 2) {
            if(preg_match('/[\'^£$%&*( )}{@#~?><>,|=_+¬-]/', request('name'), $specialChar)) {
                $strings = explode($specialChar[0], request('name'));
                $uids = Plan::where([['plan_holder_name', 'like', '%' . $strings[0] . '%'], ['plan_holder_name', 'like', '%' . $strings[1] . '%']])->get('id')->pluck('id');
                $plans = $plans->whereIn('id', $uids);
            } else {
                $uids = request('name') ? Plan::where('plan_holder_name', 'like', '%' . request('name') . '%')->get('id')->pluck('id') : Plan::where('plan_number', 'like', '%' . request('plan_number') . '%')->get('id')->pluck('id');
                $plans = $plans->whereIn('id', $uids);
            }
        } else if (!empty(request('plan_number')) && strlen(request('plan_number')) > 2) {
            $uids = request('name') ? Plan::where('plan_holder_name', 'like', '%' . request('name') . '%')->get('id')->pluck('id') : Plan::where('plan_number', 'like', '%' . request('plan_number') . '%')->get('id')->pluck('id');
            $plans = $plans->whereIn('id', $uids);
        }
        return $plans->whereNull('deleted_at')->get();
    }

    // File Section Response
    public function getFilesCount(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $files = [];

        $files['plan']['id'] = $plan->id;
        $files['plan']['name'] = $plan->plan_holder_name;
        $files['plan']['plan_number'] = $plan->plan_number;
        $files['plan']['files'] = [];

        $planDirectory = 'files/plans/' . $plan->id . '/';
        $planFilesDirectories = Storage::disk('gcs')->directories($planDirectory);
        foreach ($planFilesDirectories as $directory) {
            $filesInFolder = Storage::disk('gcs')->files($planDirectory . pathinfo($directory)['basename']);
            $files['plan']['files'][pathinfo($directory)['basename']] = count($filesInFolder);
        }

        $companyFilesDirectory = 'files/Documents/' . $plan->company_id . '/';
        $companyFilesDirectories = Storage::disk('gcs')->directories($companyFilesDirectory);

        $files['plan_holder']['id'] = $plan->company_id;
        $files['plan_holder']['name'] = $plan->company->name;
        $files['plan_holder']['has_photo'] = $plan->company->has_photo;
        $files['plan_holder']['files'] = [];

        foreach ($companyFilesDirectories as $path)
        {
            $filesInFolder = Storage::disk('gcs')->files($companyFilesDirectory . pathinfo($path)['basename']);
            $files['plan_holder']['files'][pathinfo($path)['basename']] = count($filesInFolder);
        }

        $vesselCompanies = Vessel::where([['plan_id', $plan->id], ['company_id', '<>', $plan->company_id]])->groupBy('company_id')->get();

        $i = 0;
        foreach($vesselCompanies as $vesselCompany)
        {
            $company = Company::where('id', $vesselCompany->company_id)->first();

            $companyFilesDirectory = 'files/Documents/' . $company->id . '/';
            $companyFilesDirectories = Storage::disk('gcs')->directories($companyFilesDirectory);
            $files['company'][$i]['id'] = $company->id;
            $files['company'][$i]['name'] = $company->name;
            $files['company'][$i]['has_photo'] = $company->has_photo;
            $files['company'][$i]['files'] = [];

            foreach($companyFilesDirectories as $path)
            {
                $filesInFolder = Storage::disk('gcs')->files($companyFilesDirectory . pathinfo($path)['basename']);
                $files['company'][$i]['files'][pathinfo($path)['basename']] = count($filesInFolder);
            }

            $i++;
        }

        return response()->json($files);
    }

    public function getFilesDOC(Plan $plan, $type)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $files = [];
        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
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

    public function destroyFileDOC(Plan $plan, $type, $fileName)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
        if (Storage::disk('gcs')->delete($directory . $fileName)) {
            return response()->json(['success' => true, 'message' => 'File deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function bulkDestroy(Request $request, Plan $plan, $type)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $removeData = $request->all();
        for($i = 0; $i < count($removeData); $i ++) {
            $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
            Storage::disk('gcs')->delete($directory . $removeData[$i]['name']);
        }
        return response()->json(['success' => true, 'message' => 'File deleted.']);
    }

    public function downloadFileDOC(Plan $plan, $type, $fileName)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
        $url = Storage::disk('gcs')->temporaryUrl(
            $directory . $fileName, now()->addMinutes(5)
        );
        return response()->json(['success' => true, 'message' => 'Download started.', 'url' => $url]);
    }

    public function downloadFileDOCForce(Plan $plan, $type, $fileName)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        ini_set('memory_limit', '-1');

        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
        return response()->streamDownload(function() use ($directory, $fileName) {
            echo Storage::disk('gcs')->get($directory . $fileName);
        }, $fileName, [
                'Content-Type' => 'application/octet-stream'
            ]);
    }

    public function uploadFileDOC(Plan $plan, $type, Request $request)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // add_cors_headers_group_cdt_individual($request);

        $fileName = $request->file->getClientOriginalName();
        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';

        if (Storage::disk('gcs')->exists($directory . $fileName)) {
            $fileName = date('m-d-Y_h:ia - ') . $fileName;
        }
        if (Storage::disk('gcs')->putFileAs($directory, \request('file'), $fileName)) {
            return response()->json(['success' => true, 'message' => 'File uploaded.', 'name' => $fileName]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function generateFileDOC(Plan $plan, $type, Request $request)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $data = [
            'footerText' => '',
            'issueDate' => \request('dateIssued'),
            'dateCreated' => \request('dateCreated'),
            'address' => CompanyAddress::where('id', request('address'))->first()
        ];
        $vessels = [];
        $fileName = request('name') . '.pdf';
        $directory = 'files/plans/' . $plan->id . '/' . $type . '/';
        $headerHtml = view()->make('documents.pdf-templates.partials.header-1')->render();
        $footerHtml = view()->make('documents.pdf-templates.partials.footer-1', compact('data'))->render();
        $pdf = App::make('snappy.pdf.wrapper');
        $pdf->setPaper('A4');
        $pdf->setOption('margin-bottom', '1cm');
        $pdf->setOption('margin-top', '2cm');
        $orientation = 'portrait';
        $pdf->setOption('margin-right', '1cm');
        $pdf->setOption('margin-left', '1cm');
        $pdf->setOption('enable-javascript', true);
        $pdf->setOption('enable-smart-shrinking', true);
        $pdf->setOption('no-stop-slow-scripts', true);
        switch ($type) {
            case 'group-v-consent-letter':
                $fileName = 'Consent Agreement For Vessel Response Plan.pdf';
                break;
            case 'letter-of-intent-non-tank-vessels-below-250-bbls':
                $data['capacity'] = '250';
                $data['footerText'] = 'Consent Agreement For Vessel Response Plans<br/>Conforms to International Group of P&I Guidelines';
                break;
            case 'letter-of-intent-non-tank-vessels':
                $data['capacity'] = '2,500';
                $data['footerText'] = 'Consent Agreement For Vessel Response Plans<br/>Conforms to International Group of P&I Guidelines';
                break;
            case 'smff-coverage-certification':
                $vessels = $plan->vessels()->select('id', 'name')->get();
                $pdf->setOption('margin-bottom', '2cm');
                $footerHtml = view()->make('documents.pdf-templates.partials.footer-asa', compact('data'))->render();
                break;
            case 'nt-smff-annex':
                $pdf->setOption('minimum-font-size', 18);
                $data['qi'] = \request('qi');
                $data['dpa'] = \request('dpa');
                $data['contract'] = \request('contract');
                $data['title'] = 'NT Vessel Response Plan';
                $data['subtitle'] = 'Salvage and Marine Firefighting Annex';
                $data['footerText'] = 'Donjon-SM IT LLC<br/>Salvage & Marine Firefighting Annex';
                //TANKER === FALSE
                $vessels = $plan->vessels()->select('id', 'name', 'imo', 'tanker')->where('tanker', 0)->get();
                $headerHtml = view()->make('documents.pdf-templates.partials.header-2', compact('data'))->render();
                break;
        }

        $pdf->setOrientation($orientation);
        $pdf->setOption('header-html', $headerHtml);
        $pdf->setOption('footer-html', $footerHtml);
        $pdf->loadView('documents.pdf-templates.' . $type . '.index', compact('company', 'data', 'vessels'));

        if (Storage::disk('gcs')->exists($directory . $fileName)) {
            $fileName = date('m-d-Y_h:ia - ') . $fileName;
        }
        $tmpName = md5($fileName . time());
        $pdf->save(storage_path($tmpName));

        if (Storage::disk('gcs')->putFileAs($directory, new File(storage_path($tmpName)), $fileName)) {
            unlink(storage_path($tmpName));
            return response()->json(['success' => true, 'message' => 'File generated.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function getQI(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $vessel_ids = $plan->vessels()->pluck('id');
        return VendorShortResource::collection(Company::whereHas('type', function ($q) {
            $q->where('name', 'QI Company');
        })->whereHas('vessels', function ($q) use ($vessel_ids) {
            $q->whereIn('id', $vessel_ids);
        })->get());
    }

    public function getContactsDPA(Plan $plan)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        return CompanyContactShortResource::collection($plan->company->contacts()->whereHas('contactTypes', function ($q) {
            $q->where('name', 'DPA');
        })->get());
    }

    public function updatePlanCompany(Plan $plan, Company $company)
    {
        $checkedPermission = $this->checkPermission($plan);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }
        
        $plan->company_id = $company->id;
        if($plan->save()) {
            return response()->json(['success' => true, 'message' => 'Plan Company status changed.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function getPlanNumber()
    {
        return Plan::select('id', DB::raw('CAST(plan_number AS CHAR) AS name'))->whereNotNull('plan_number')->get();
    }

    public function indexShort()
    {
        return Plan::select('id', 'plan_holder_name')->whereNull('deleted_at')->get();
    }

    public function importVrp($id, Request $request)
    {
        $planVrp = VrpPlan::where('plan_number', $id)->first();
        if($planVrp) {
            $plan = Plan::where('plan_number', $id)->first();
            if($plan) {
                return response()->json(['success' => false, 'message' => 'Already exists a plan number in CDT!']);
            } else {
                if(!(request('company_id'))) {
                    $newCompany = new Company();
                    $newCompany->operating_company_id = 0;
                    $newCompany->networks_active = 0;
                    $newCompany->vendor_active = 0;
                    $newCompany->name = $planVrp->plan_holder;
                    $newCompany->save();
                }

                $newPlan = new Plan();
                $newPlan->company_id = request('company_id') ? request('company_id') : $newCompany->id;
                $newPlan->plan_number = $planVrp->plan_number;
                $newPlan->plan_holder_name = $planVrp->plan_holder;
                $newPlan->active_field_id = 1;
                $newPlan->deleted = 0;
                    
                if($newPlan->save()) {
                    $companyAddress = request('company_id') ? CompanyAddress::where('id', request('company_id'))->first() : NULL;
                    if($companyAddress) {
                        $companyAddress->plan_id = $newPlan->id;
                        $companyAddress->save();
                    } else {
                        $address = new CompanyAddress();
                        $address->address_type_id = 3;
                        $address->company_id = request('company_id') ? request('company_id') : $newCompany->id;
                        $address->plan_id = $newPlan->id;
                        $address->street = $planVrp->holder_address_1;
                        $address->city = $planVrp->holder_city;
                        $address->state = $planVrp->holder_state;
                        $address->country = Country::where('name', $planVrp->holder_country)->first()->code;
                        $address->zip = $planVrp->holder_zip;
                        $address->save();
                    }
                    return response()->json(['success' => true, 'message' => 'Plan imported successfully']);
                }
            }
        }
        return response()->json(['success' => false, 'message' => 'Doesn\'t exist this plan number!']);
    }

    public function bulkUpdate(Request $request)
    {
        $planDatas = request('planData');

        foreach($planDatas as $planData)
        {
            $plan = Plan::find($planData['id']);
            $plan->plan_holder_name = $planData['plan_holder_name'];
            $plan->plan_number = $planData['plan_number'];
            $plan->active_field_id = $planData['active_field_id'];
            $plan->qi_id = $planData['qi_id'];
            $plan->plan_prepare_id = $planData['plan_prepare_id'];

            $plan->save();
        }

        return response()->json(['success' => true, 'message' => 'Plans updated.']);
    }

    private function checkPermission($plan)
    {
        $authUser = Auth::user();
        if($authUser->role_id == 7 || $authUser->role_id == 3) { // Company Plan Manager
            $company = CompanyUser::where([['user_id', $authUser->id], ['company_id', $plan->company_id]])->first();

            if(!$company) {
                return false;
            }
        }

        return true;
    }
}
