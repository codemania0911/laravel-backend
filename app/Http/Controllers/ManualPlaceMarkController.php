<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ManualPlaceMark;

class ManualPlaceMarkController extends Controller
{
    //
    public function getPlaceMark(Request $request)
    {
        $perPage = empty(request('per_page')) ? 10 : (int)request('per_page');

        $query = $request->get('query');

        $placeMarkQuery = ManualPlaceMark::orderBy('updated_at', 'desc');

        if(!empty($query) && strlen($query) > 2) {
            $uids = ManualPlaceMark::search($query)->get('id')->pluck('id');
            $placeMarkQuery->whereIn('id', $uids);
        }
        
        return $placeMarkQuery->paginate($perPage);
    }

    public function addPlaceMark(Request $request)
    {
        $placeMark = new ManualPlaceMark();
        $placeMark->name = request('name');
        $placeMark->latitude = request('latitude');
        $placeMark->longitude = request('longitude');
        $placeMark->occured_time = request('occured_time');
        $placeMark->icon = request('icon');
        $placeMark->color = request('color');
        $placeMark->save();

        return response()->json(['success' => true, 'message' => 'Place Mark Added.']);
    }

    public function updatePlaceMark(ManualPlaceMark $placeMark, Request $request)
    {
        $placeMark->name = request('name');
        $placeMark->latitude = request('latitude');
        $placeMark->longitude = request('longitude');
        $placeMark->occured_time = request('occured_time');
        $placeMark->icon = request('icon');
        $placeMark->color = request('color');
        $placeMark->save();

        return response()->json(['success' => true, 'message' => 'Place Mark Updated']);
    }

    public function destroyPlaceMark(ManualPlaceMark $placeMark)
    {
        $placeMark->delete();

        return response()->json(['success' => true, 'message' => 'Place Mark deleted.']);
    }

    public function getAllPlaceMark()
    {
        return ManualPlaceMark::all();
    }
}
