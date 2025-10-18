<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LowStockEventTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
    public function test_low_stock_event_is_fired_and_queued()
    {
        Event::fake();
        Queue::fake();
                
        Event::assertDispatched(LowStockDetected::class);
        Queue::assertPushed(SendLowStockNotification::class);
    }

}
