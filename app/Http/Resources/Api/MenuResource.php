<?php

namespace App\Http\Resources\Api;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Menu $menu */
        $menu = $this->resource;

        return [
            'id' => $menu->id,
            'location' => $menu->location,
            'name' => $menu->name,
            'items' => $this->transformItems($menu->items),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function transformItems($items): array
    {
        return $items->filter(fn (MenuItem $item) => $item->is_active)->map(fn (MenuItem $item): array => [
            'id' => $item->id,
            'label' => $item->label,
            'url' => $item->url,
            'target' => $item->target,
            'is_featured' => $item->is_featured,
            'badge' => $item->badge,
            'sort_order' => $item->sort_order,
            'children' => $item->children && $item->children->isNotEmpty()
                ? $this->transformItems($item->children)
                : [],
        ])->values()->all();
    }
}
