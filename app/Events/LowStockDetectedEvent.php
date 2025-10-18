<?php

namespace App\Events;

use App\Models\InventoryItem;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetectedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public InventoryItem $inventoryItem;

    public Warehouse $warehouse;

    public int $currentStock;

    public int $minStockLevel;

    public Stock $stock;

    public function __construct(
        InventoryItem $inventoryItem,
        Warehouse $warehouse,
        int $currentStock,
        int $minStockLevel,
        Stock $stock
    ) {
        $this->inventoryItem = $inventoryItem;
        $this->warehouse = $warehouse;
        $this->currentStock = $currentStock;
        $this->minStockLevel = $minStockLevel;
        $this->stock = $stock;
    }

    public function getEventDetails(): array
    {
        return [
            'item_name' => $this->inventoryItem->name,
            'item_sku' => $this->inventoryItem->sku,
            'warehouse_name' => $this->warehouse->name,
            'current_quantity' => $this->currentStock,
            'min_required' => $this->minStockLevel,
            'shortage' => $this->minStockLevel - $this->currentStock,
            'triggered_at' => now()->toDateTimeString(),
        ];
    }
}
