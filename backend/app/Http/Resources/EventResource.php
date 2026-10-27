<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'event_date' => $this->event_date->format('Y-m-d'),
            'location' => $this->location,
            'status' => $this->status,
            'client' => $this->whenLoaded('client', function () {
                return $this->client === null ? null : [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                ];
            }),
        ];
    }
}