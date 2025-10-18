<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'warehouse_location' => $this->warehouse?->location,
            'quantity' => $this->quantity,
            'is_low_stock' => $this->quantity < $this->inventoryItem?->min_stock_level,
        ];
    }
}
