<?php

namespace App\Http\Controllers;

use App\Models\AddressType;
use App\Http\Resources\AddressTypeResource;
use App\Models\Country;
use App\Http\Resources\CountryResource;
use Illuminate\Http\Request;
use App\Models\Region;

class AddressController extends Controller
{
    public function types()
    {
        return AddressTypeResource::collection(AddressType::all());
    }

    public function countries()
    {
        return Country::orderBy('name')->get();
        return CountryResource::collection(Country::orderBy('name')->get());
    }

    public function regions()
    {
        return Region::all();
        return response()->json(['data' => Region::all()], 200);
    }
}
