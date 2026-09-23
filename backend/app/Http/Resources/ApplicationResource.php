<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,

            'crew' => $this->whenLoaded('crew', function () {
                $profile = $this->crew->crewProfile;

                return [
                    'id' => $this->crew->id,
                    'name' => $this->crew->name,

                    'crew_profile' => $profile
                        ? [
                            'id' => $profile->id,
                            'bio' => $profile->bio,
                            'skills' => $profile->skills,
                            'experience_years' =>
                                $profile->experience_years,
                            'availability' =>
                                $profile->availability,
                        ]
                        : null,
                ];
            }),

            'job_posting' => $this->whenLoaded('jobPosting', function () {
                return [
                    'id' => $this->jobPosting->id,
                    'title' => $this->jobPosting->title,
                    'job_type' => $this->jobPosting->job_type,
                    'pay_rate' => $this->jobPosting->pay_rate,
                    'status' => $this->jobPosting->status,
                ];
            }),

            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ];
            }),
        ];
    }
}