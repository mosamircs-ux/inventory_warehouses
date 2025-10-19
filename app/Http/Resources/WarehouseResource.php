<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\WarehouseStockResource; // لتفصيل المنتجات والمخزون

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'location'     => $this->location,
            'capacity'     => $this->capacity,
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at->format('Y-m-d H:i:s'),

            'stocks'       => WarehouseStockResource::collection($this->whenLoaded('stocks')),

            'items_count'  => $this->whenCounted('stocks'),

            'total_quantity' => $this->whenLoaded('stocks', fn() => $this->stocks->sum('quantity')),

            'status_label' => $this->is_active ? 'نشط' : 'غير نشط',
        ];
    }
}
