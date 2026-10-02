<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PlaceDetailResource extends PlaceListResource
{
    public function toArray(Request $request): array
    {
        $data = (new PlaceListResource($this->resource))->resolve($request);

        $place = $this->resource;
        $hours = $this->hoursFor($place)->keyBy(fn ($hour) => (int) $hour->day);

        $data['description'] = $place->description;

        $data['hours'] = [];
        for ($day = 0; $day < 7; $day++) {
            $row = $hours->get($day);

            $data['hours'][] = $row !== null
                ? [
                    'day' => $day,
                    'opens' => self::hm($row->opens),
                    'closes' => self::hm($row->closes),
                    'is_closed' => (bool) $row->is_closed,
                    'note' => $row->note,
                ]
                : [
                    'day' => $day,
                    'opens' => null,
                    'closes' => null,
                    'is_closed' => true,
                    'note' => null,
                ];
        }

        $data['photos'] = $place->photos
            ->map(fn ($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'is_cover' => (bool) $photo->is_cover,
                'status' => $photo->status,
            ])
            ->values()
            ->all();

        $data['submitted_by'] = $place->submittedBy ? [
            'id' => $place->submittedBy->id,
            'name' => $place->submittedBy->name,
        ] : null;

        $data['approved_by'] = $place->approvedBy ? [
            'id' => $place->approvedBy->id,
            'name' => $place->approvedBy->name,
        ] : null;

        $data['approved_at'] = $place->approved_at;
        $data['source_url'] = $place->source_url;

        return $data;
    }
}