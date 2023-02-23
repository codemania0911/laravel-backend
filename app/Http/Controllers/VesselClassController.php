<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vessel;
use App\Models\VesselClass;
use App\Models\Company;
use App\Http\Resources\VesselClassResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

ini_set('memory_limit', '-1');

class VesselClassController extends Controller
{
    //
    public function getVesselClass(Request $request)
    {
        $perPage = empty(request('per_page')) ? 10 : (int)request('per_page');

        $query = $request->get('query');

        $vesselClassQuery = VesselClass::orderBy('updated_at', 'desc');

        if(!empty($query) && strlen($query) > 2) {
            $uids = VesselClass::search($query)->get('id')->pluck('id');
            $vesselClassQuery->whereIn('id', $uids);
        }

        return VesselClassResource::collection($vesselClassQuery->paginate($perPage));
    }

    // Add the Vessel Class
    public function addVesselClass(Request $request)
    {
        $vesselClass = new VesselClass();
        $vesselClass->name = request('name');
        $vesselClass->company_id = request('company_id');
        $vesselClass->save();

        return response()->json(['success' => true, 'message' => 'Vessel Class Added.']);
    }

    // Update the Vessel Class
    public function updateVesselClass($id, Request $reqeuet)
    {
        $vesselClass = VesselClass::find($id);
        $vesselClass->name = request('name');
        $vesselClass->company_id = request('company_id');
        $vesselClass->save();

        return response()->json(['success' => true, 'message' => 'Vessel Class updated.']);
    }

    // Remove the Vessel Class
    public function destroyVesselClass($id)
    {
        $vesselClass = VesselClass::find($id);
        $vesselClass->delete();
        Vessel::where('vessel_class_id', $id)->update([
            'vessel_class_id' => NULL
        ]);

        return response()->json(['success' => true, 'message' => 'Vessel Class destroyed.']);
    }

    // Get Vessel data of Vessel Class
    public function getVessels(VesselClass $vesselClass, Request $request)
    {
        $perPage = empty(request('per_page')) ? 10 : (int)request('per_page');

        $data = [];


        $data['vessel_class'] = VesselClass::select('vessel_classes.id', 'vessel_classes.name', 'c.id as company_id', 'c.name as company_name')
                                            ->leftJoin('companies as c', 'c.id', '=', 'vessel_classes.company_id')
                                            ->where('vessel_classes.id', $vesselClass->id)
                                            ->get();

        $query = $request->get('query');

        $vesselQuery = Vessel::where('vessel_class_id', $vesselClass->id)
                                ->orderBy('updated_at', 'desc');

        if(!empty($query) && strlen($query) > 2) {
            $uids = Vessel::search($query)->get('id')->pluck('id');
            $vesselQuery->whereIn('id', $uids);
        }

        $data['vessels'] = $vesselQuery->paginate($perPage);
        
        return $data;
    }

    // Get Vessel Class Data
    public function getIndividualVesselClassInfo(VesselClass $vesselClass)
    {
        return VesselClass::select('vessel_classes.id', 'vessel_classes.name', 'c.id as company_id', 'c.name as company_name')
                            ->leftJoin('companies as c', 'c.id', '=', 'vessel_classes.company_id')
                            ->where('vessel_classes.id', $vesselClass->id)
                            ->get();
    }

    // Add Vessel Class
    public function addNote(VesselClass $vesselClass, Request $request)
    {
        $vesselClass->note = request('note');
        if($vesselClass->save()) {
            return response()->json(['success' => true, 'message' => 'Note added.']);
        }

        return response()->json(['success' => true, 'message' => 'Something unexpected happened.']);
    }

    // Get Vessel Class Note
    public function getNote(VesselClass $vesselClass)
    {
        return $vesselClass;
    }

    // Get Vessel Class id, name
    public function getAllVesselClass()
    {
        return VesselClass::select('id', 'name')->get();
    }

    // Vessel Class File Process
    public function getFilesCount(VesselClass $vesselClass)
    {
        $files = [];
        $vesselClassDirectory = 'files/vessel_classes/' . $vesselClass->id . '/';
        $directories = Storage::disk('gcs')->directories($vesselClassDirectory);
        foreach ($directories as $directory) {
            $filesInFolder = Storage::disk('gcs')->files($vesselClassDirectory . pathinfo($directory)['basename']);
            $files[pathinfo($directory)['basename']] = [
                'count' => count($filesInFolder)
            ];
        }
        return response()->json($files);
    }

    public function getFilesDOC(VesselClass $vesselClass, $type)
    {
        $files = [];
        $directory = 'files/vessel_classes/' . $vesselClass->id . '/' . $type . '/';
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

    public function destroyFileDOC(VesselClass $vesselClass, $type, $fileName)
    {
        $directory = 'files/vessel_classes/' . $vesselClass->id . '/' . $type . '/';
        if (Storage::disk('gcs')->delete($directory . $fileName)) {
            return response()->json(['success' => true, 'message' => 'File deleted.']);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
    }

    public function bulkDestroy(Request $request, VesselClass $vesselClass, $type)
    {
        $removeData = $request->all();
        for($i = 0; $i < count($removeData); $i ++) {
            $directory = 'files/vessel_classes/' . $vesselClass->id . '/' . $type . '/';
            Storage::disk('gcs')->delete($directory . $removeData[$i]['name']);
        }
        return response()->json(['success' => true, 'message' => 'File deleted.']);
    }

    public function downloadFileDOCForce(VesselClass $vesselClass, $type, $fileName)
    {
        ini_set('memory_limit', '-1');

        $directory = 'files/vessel_classes/' . $vesselClass->id . '/' . $type . '/';
        return response()->streamDownload(function() use ($directory, $fileName) {
            echo Storage::disk('gcs')->get($directory . $fileName);
        }, $fileName, [
                'Content-Type' => 'application/octet-stream'
            ]);
    }

    public function uploadFileDOC(VesselClass $vesselClass, $type, Request $request)
    {

        set_time_limit(0);
        ini_set('memory_limit', '-1');

      //  add_cors_headers_group_cdt_individual($request);

        $fileName = $request->file->getClientOriginalName();
        $directory = 'files/vessel_classes/' . $vesselClass->id . '/' . $type . '/';

        if (Storage::disk('gcs')->exists($directory . $fileName)) {
            $fileName = date('m-d-Y_h:ia - ') . $fileName;
        }
        if (Storage::disk('gcs')->putFileAs($directory, \request('file'), $fileName)) {
            return response()->json(['success' => true, 'message' => 'File uploaded.', 'name' => $fileName]);
        }
        return response()->json(['success' => false, 'message' => 'Something unexpected happened.']);
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

    public function getVesselsOfVesselClassCompany(VesselClass $vesselClass)
    {
        return Vessel::select('id', 'name')
                        ->where('company_id', $vesselClass->company_id)->get();
    }

}
