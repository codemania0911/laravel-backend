<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\Vessel;
use Illuminate\Support\Facades\Storage;

class VesselReportExport implements FromView, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $company;

    public function __construct($company = null)
    {
        $this->company = $company;
    }

    public function view() : View
    {
        //
        $folderLists = [
            'damage_stability_models',
            'drawings',
            'drills-and-exercises',
            'prefire_plan_certification',
            'prefire_plans',
            'stability-booklet'
        ];
        $companyName = $this->company->name;
        $vessels = Vessel::where('company_id', $this->company->id)
                        ->whereIn('active_field_id', [2, 3])
                        ->get();
        
        $i = 0;
        $totalCount = 0;
        foreach($vessels as $vessel)
        {
            $fileCount = 0;
            $companyVesselFileInfo[$i]['files'] = [];
            foreach($folderLists as $location)
            {
                $directory = 'files/new/' . $location . '/' . $vessel->id . '/';
                $filesInFolder = Storage::disk('gcs')->files($directory);
                $fileCount += count($filesInFolder);
                foreach ($filesInFolder as $path) {
                    $companyVesselFileInfo[$i]['files'][] = [
                        'name' => pathinfo($path)['basename'],
                        'size' => $this->formatBytes(Storage::disk('gcs')->size($directory . pathinfo($path)['basename'])),
                        'ext' => pathinfo($path)['extension'] ?? null,
                        'created_at' => date("Y-m-d", Storage::disk('gcs')->lastModified($directory . pathinfo($path)['basename']))
                    ];
                }
            }
            $totalCount += $fileCount;
            $companyVesselFileInfo[$i]['vessel_name'] = $vessel->name . '(' . $fileCount . ')';
            $i ++;
        }

        $companyVesselFileInfo['company_name'] = $companyName . '(' . $totalCount . ')';
        return view('reports.vessel', ['companyVesselFileInformations' => $companyVesselFileInfo]);
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
}
