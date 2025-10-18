<?php

namespace App\Notifications;

use App\Events\LowStockDetectedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected LowStockDetectedEvent $event;

    public function __construct(LowStockDetectedEvent $event)
    {
        $this->event = $event;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
    public function toMail(object $notifiable)
    {
        $details = $this->event->getEventDetails();
        $shortage = $details['shortage'];
        
        return (new MailMessage)
            ->subject(' تنبيه: انخفاض مخزون - ' . $this->event->inventoryItem->name)
            ->greeting('مرحباً ' . $notifiable->name . ',')
            ->line('نود إعلامك بأن مخزون أحد المنتجات قد انخفض عن الحد الأدنى.')
            ->line('**تفاصيل المنتج:**')
            ->line(' اسم المنتج: ' . $details['item_name'])
            ->line(' رمز SKU: ' . $details['item_sku'])
            ->line(' المستودع: ' . $details['warehouse_name'])
            ->line(' الكمية الحالية: ' . $details['current_quantity'])
            ->line(' الحد الأدنى المطلوب: ' . $details['min_required'])
            ->line(' النقص: ' . $shortage . ' وحدة')
            ->action('عرض المخزون', url('/warehouses/' . $this->event->warehouse->id . '/inventory'))
            ->line('يرجى اتخاذ الإجراء المناسب لتجديد المخزون.')
            //->priority(MailMessage::PRIORITY_HIGH)
            ->when($shortage >= 20, function ($mail) {
                return $mail->error()
                    ->line('⚠️ **تنبيه عاجل:** النقص في المخزون حاد جداً!');
            });
    }

    public function toArray(object $notifiable)
    {
        return [
            'type' => 'low_stock',
            'inventory_item_id' => $this->event->inventoryItem->id,
            'warehouse_id' => $this->event->warehouse->id,
            'details' => $this->event->getEventDetails(),
        ];
    }
}
