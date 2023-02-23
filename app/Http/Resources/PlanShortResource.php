<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanShortResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->plan_holder_name,
            'label' => $this->plan_number ? $this->plan_holder_name . ' - ' . $this->plan_number : $this->plan_holder_name,
            'plan_number' => $this->plan_number,
            'active' => $this->active
        ];
    }
}
