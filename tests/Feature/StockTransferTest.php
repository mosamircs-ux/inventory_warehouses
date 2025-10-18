<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
    public function test_successful_stock_transfer()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->create();
        
        Stock::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity' => 50
        ]);
        
        $response = $this->postJson('/api/stock-transfers', [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 20
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('stock_transfers', [
            'quantity' => 20,
            'status' => 'completed'
        ]);
    }

}
