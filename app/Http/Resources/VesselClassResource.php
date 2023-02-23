<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Vessel;
use App\Models\Company;

class VesselClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $aa = $this->company->name;
        $bb = $this->vessels->count();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_id' => $this->company_id,
            'company_name' => $this->company->name,
            'vessel_count' => $this->vessels->count()
        ];
    }
}
