<?php

namespace App\Http\Resources\Api;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Testimonial $testimonial */
        $testimonial = $this->resource;

        return [
            'id' => $testimonial->id,
            'project_id' => $testimonial->project_id,
            'project_name' => $testimonial->project?->name,
            'name' => $testimonial->name,
            'role' => $testimonial->role,
            'avatar_url' => $testimonial->resolved_avatar_url,
            'rating' => $testimonial->rating,
            'review_text' => $testimonial->review_text,
            'review_date_human' => $testimonial->review_date_human,
            'sort_order' => $testimonial->sort_order,
        ];
    }
}
