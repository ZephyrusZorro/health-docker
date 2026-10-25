<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'bed_number'          => $this->bed_number,
            'label'               => $this->bed_number,           // frontend-friendly alias
            'status'              => $this->status,
            'daily_rate_override' => $this->daily_rate_override !== null ? (float) $this->daily_rate_override : null,
            'notes'               => $this->notes,
            'room'                => $this->whenLoaded('room', fn () => [
                'id'         => $this->room?->id,
                'code'       => $this->room?->code,
                'name'       => $this->room?->name,
                'capacity'   => $this->room?->capacity,
                'daily_rate' => $this->room?->daily_rate !== null ? (float) $this->room->daily_rate : null,
            ]),
            'ward' => $this->whenLoaded('room', fn () => $this->room?->ward ? [
                'id'       => $this->room->ward->id,
                'code'     => $this->room->ward->code,
                'name'     => $this->room->ward->name,
                'category' => $this->room->ward->category,
            ] : null),
        ];
    }
}
