<?php

namespace App\Http\Controllers;

use App\Models\AddressType;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyUser;
use App\Http\Resources\CompanyAddressResource;
use Illuminate\Http\Request;
use App\Helpers\GeoHelper;
use Illuminate\Support\Facades\Auth;

class CompanyAddressesController extends Controller
{
    public function index(Company $company)
    {
        $checkedPermission = $this->checkPermission($company);
        if(!$checkedPermission) {
            abort(403, 'Forbidden Access.');
        }

        $types = AddressType::select('id', 'name')->get();
        foreach ($types as $type) {
            $type->addresses = CompanyAddressResource::collection($company->addresses()->where('address_type_id', $type->id)->get());
        }
        return $types;
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
        $address['company_id'] = isset($request['company_id']) ? $request['company_id'] : $address['company_id'];
        $address['state'] = isset($request['state']) ? $request['state'] : $address['state'];
        $address['phone'] = isset($request['phone']) ? $request['phone'] : $address['phone'];
        $address['zone_id'] = getGeoZoneID($address['latitude'], $address['longitude']);
        if ($address->save()) {
           return response()->json(['success' => true, 'message' => 'Company address saved.']);//+ $cont
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function destroyAddress(CompanyAddress $address)
    {
        if ($address->delete()) {
            return response()->json(['success' => true, 'message' => 'Company address deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function store(Company $company)
    {
        if ($company->addresses()->create(['street' => '', 'address_type_id' => \request('type_id')])) {
            return response()->json(['success' => true, 'message' => 'Company address added.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    private function checkPermission($company)
    {
        $authUser = Auth::user();
        if($authUser->role_id == 7 || $authUser->role_id == 3) { // Company Plan Manager
            $exsitCompany = CompanyUser::where([['user_id', $authUser->id], ['company_id', $company->id]])->first();

            if(!$exsitCompany) {
                return false;
            }
        }

        return true;
    }
}
