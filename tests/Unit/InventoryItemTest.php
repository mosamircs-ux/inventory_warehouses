<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InventoryItemTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
    public function test_prevents_over_transfer()
    {
        $item = InventoryItem::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = Stock::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10
        ]);
        
        $this->expectException(\Exception::class);
    }

}
