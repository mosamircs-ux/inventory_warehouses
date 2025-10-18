<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => number_format($this->price, 2),
            'min_stock_level' => $this->min_stock_level,
            'total_stock' => $this->when(
                $this->relationLoaded('stocks'),
                $this->stocks->sum('quantity')
            ),
            'warehouses' => WarehouseStockResource::collection(
                $this->whenLoaded('stocks')
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
