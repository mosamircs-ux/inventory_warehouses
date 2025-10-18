<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\UserResource;

class StockTransferResource extends JsonResource
{
    /**
     * تحويل عملية النقل إلى JSON متقدم.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'from_warehouse' => new WarehouseResource($this->whenLoaded('fromWarehouse')),
            'to_warehouse' => new WarehouseResource($this->whenLoaded('toWarehouse')),

            'inventory_item' => new InventoryItemResource($this->whenLoaded('inventoryItem')),

            'quantity' => $this->quantity,
            'status' => $this->status,

            'transferred_by' => new UserResource($this->whenLoaded('transferredBy')),


            'completed_at' => $this->when($this->status === 'completed' && $this->completed_at, fn() => $this->completed_at->format('Y-m-d H:i:s')),
            'cancelled_at' => $this->when($this->status === 'cancelled' && $this->cancelled_at, fn() => $this->cancelled_at->format('Y-m-d H:i:s')),
            'cancelled_by' => $this->when($this->status === 'cancelled' && $this->relationLoaded('cancelledBy'), fn() => new UserResource($this->cancelledBy)),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

        ];
    }
}
