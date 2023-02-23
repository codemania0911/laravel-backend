<?php

namespace App\Http\Resources;

use App\Models\Company;
use function GuzzleHttp\Psr7\str;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $roles = $this->roles();
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => trim($this->email) ? $this->email : '',
            'mobile_number' => trim($this->mobile_number) ? $this->mobile_number : '',
	        'username' => $this->username,
            'role_id' => $this->role_id,
	        // 'roles' => $roles->pluck('name'),
            'resource_provider' => $this->response,
            'active' => $this->active,
            'company_id' => $this->company_id,
            'primary_company_id' => $this->primary_company_id,
            'response'   => $this->response,
            'vrp_import' => $this->vrp_import,
            'networks_active' => $this->networks_active,
            'capabilies_active' => $this->capabilies_active,
            'title' => $this->title,
            'occupation' => $this->occupation,
        ];
    }
}
