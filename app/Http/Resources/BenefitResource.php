<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'created_at' =>  \Carbon\Carbon::parse($this->created_at)->format('Y-m-d'),
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'uflex' => $this->uflex,
            'company' => json_decode($this->company),
            'categories' => json_decode($this->categories),
            'periods' => json_decode($this->currentPeriod ?? $this->periods->last()),
            'sub_categories' => json_decode($this->categoryOptions),
            'member_count' => $this->member_plan_links_count ?? 0,
        ];
    }
}
