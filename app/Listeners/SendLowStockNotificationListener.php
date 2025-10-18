<?php

namespace App\Listeners;

use App\Events\LowStockDetectedEvent;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public ?string $connection = 'redis';


    public int $tries = 3;

    public int $timeout = 60;

    public int $delay = 0;

    public bool $afterCommit = true;

    public function __construct()
    {
        //
    }

    public function handle(LowStockDetectedEvent $event): void
    {
        $this->logLowStockEvent($event);

        $admins = $this->getAdmins();

        $this->notifyAdmins($admins, $event);

        $this->performAdditionalActions($event);
    }

    protected function logLowStockEvent(LowStockDetectedEvent $event): void
    {
        $details = $event->getEventDetails();
        
        Log::channel('inventory')->warning('Low Stock Detected', [
            'event' => 'low_stock_detected',
            'inventory_item_id' => $event->inventoryItem->id,
            'warehouse_id' => $event->warehouse->id,
            'current_stock' => $event->currentStock,
            'min_stock_level' => $event->minStockLevel,
            'shortage' => $details['shortage'],
            'details' => $details,
        ]);
    }

    protected function getAdmins()
    {
        return User::where('is_admin', true)
            ->orWhere('role', 'admin')
            ->get();
    }

    protected function notifyAdmins($admins, LowStockDetectedEvent $event): void
    {
        foreach ($admins as $admin) {
            Log::channel('notifications')->info('Low Stock Notification Sent (Simulated)', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'item' => $event->inventoryItem->name,
                'warehouse' => $event->warehouse->name,
                'message' => $this->buildNotificationMessage($event),
            ]);
        }
    }

    protected function buildNotificationMessage(LowStockDetectedEvent $event): string
    {
        return sprintf(
            'تنبيه: انخفاض مخزون المنتج "%s" (SKU: %s) في مستودع "%s". الكمية الحالية: %d، الحد الأدنى: %d',
            $event->inventoryItem->name,
            $event->inventoryItem->sku,
            $event->warehouse->name,
            $event->currentStock,
            $event->minStockLevel
        );
    }

    protected function performAdditionalActions(LowStockDetectedEvent $event): void
    {
        Log::info('Additional low stock actions performed', [
            'item_id' => $event->inventoryItem->id,
            'warehouse_id' => $event->warehouse->id,
        ]);
    }

    public function failed(LowStockDetectedEvent $event, \Throwable $exception): void
    {
        Log::error('Failed to process low stock notification', [
            'event' => 'low_stock_notification_failed',
            'item_id' => $event->inventoryItem->id,
            'warehouse_id' => $event->warehouse->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function shouldQueue(LowStockDetectedEvent $event): bool
    {
        return ($event->minStockLevel - $event->currentStock) >= 5;
    }

    public function withDelay(LowStockDetectedEvent $event): int
    {
        $shortage = $event->minStockLevel - $event->currentStock;
        
        if ($shortage >= 20) {
            return 0;
        }
        
        if ($shortage >= 10) {
            return 300; 
        }
        
        return 900;
    }
}
