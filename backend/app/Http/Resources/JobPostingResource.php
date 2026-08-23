<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'job_type' => $this->job_type,
            'number_of_positions' => $this->status,
            'pay_rate' => $this->pay_rate,
            'status' => $this->status,

            'event' => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'event_date' => $this->event->event_date,
                'location' => $this->event->location,
            ],

            'recruiter' => [
                'id' => $this->recruiter->id,
                'name' => $this->recruiter->name
            ],
        ];
    }
}
