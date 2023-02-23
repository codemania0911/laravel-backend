<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class UserNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'user' => $this->creator->first_name . ' ' . $this->creator->last_name,
            'user_id' => $this->creator_id,
            'has_photo' => User::where('id', $this->creator_id)->first()->has_photo,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i') . ' - (' . $this->created_at->diffForHumans() . ')' : '-//-'
        ];
    }
}
