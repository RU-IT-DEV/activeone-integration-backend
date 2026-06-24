<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TinyMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => "$this->first_name $this->middle_name $this->last_name $this->suffix",
            'email' => $this->email,
            'flexicare_id' => $this->flexicare_id,
            'employee_no' => $this->employee_no,
            'principal_id' => $this->principal_id,
        ];
    }
}
