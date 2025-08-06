<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EligibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           =>  $this->id,
            'name'         =>  $this->elgb_exam,
            'code'          => $this->elgb_exam_short_code,
            //'block_municipality'  =>  BlockMunicipalityResource::collection($this->whenLoaded('district')),
        ];
    }
}
