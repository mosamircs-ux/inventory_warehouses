<?php

namespace App\Http\Controllers;

use App\Events\LowStockDetected;
use App\Events\LowStockDetectedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\InventoryItem;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $query = StockTransfer::with([
            'fromWarehouse:id,name,location',
            'toWarehouse:id,name,location',
            'inventoryItem:id,name,sku',
            'transferredBy:id,name,email'
        ]);

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('transferred_by', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $transfers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب قائمة عمليات النقل بنجاح', 
            'data' => StockTransferResource::collection($transfers),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ]
        ]);
    }

    public function store(StockTransferRequest $request)
    {
        DB::beginTransaction();

        try {
            $fromWarehouse = Warehouse::lockForUpdate()->findOrFail($request->from_warehouse_id);
            $toWarehouse = Warehouse::lockForUpdate()->findOrFail($request->to_warehouse_id);
            $item = InventoryItem::findOrFail($request->inventory_item_id);

            if (!$fromWarehouse->isActive()) {
                throw new \Exception('source warehouse is not active');
            }

            if (!$toWarehouse->isActive()) {
                throw new \Exception('destination warehouse is not active');
            }

            $fromStock = Stock::where([
                'warehouse_id' => $fromWarehouse->id,
                'inventory_item_id' => $item->id,
            ])->lockForUpdate()->first();

            if (!$fromStock) {
                throw new \Exception('item is not found in the source warehouse');
            }

            if ($fromStock->quantity < $request->quantity) {
                throw new \Exception(
                    "the requested quantity ({$request->quantity}) is not available. the available quantity: {$fromStock->quantity}"
                );
            }

            if (!$toWarehouse->hasCapacity($request->quantity)) {
                throw new \Exception('destination warehouse reached its maximum capacity');
            }

            $fromStock->decrement('quantity', $request->quantity);

            $toStock = Stock::firstOrCreate([
                'warehouse_id' => $toWarehouse->id,
                'inventory_item_id' => $item->id,
            ], ['quantity' => 0]);

            $toStock->increment('quantity', $request->quantity);

            $transfer = StockTransfer::create([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'inventory_item_id' => $item->id,
                'quantity' => $request->quantity,
                'transferred_by' => auth()->id(),
                'status' => 'completed',
                'notes' => $request->notes,
                'completed_at' => now(),
            ]);

            $this->checkAndDispatchLowStockEvents($fromStock, $toStock, $item);

            $this->clearWarehousesCache([$fromWarehouse->id, $toWarehouse->id]);

            DB::commit();

            Log::channel('inventory')->info('Stock Transfer Completed', [
                'transfer_id' => $transfer->id,
                'from_warehouse' => $fromWarehouse->name,
                'to_warehouse' => $toWarehouse->name,
                'item' => $item->name,
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'stock transfer completed successfully',
                'data' => new StockTransferResource($transfer->load([
                    'fromWarehouse',
                    'toWarehouse',
                    'inventoryItem',
                    'transferredBy'
                ])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('inventory')->error('Stock Transfer Failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل نقل المخزون',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'inventoryItem',
            'transferredBy'
        ]);

        return response()->json([
            'success' => true,
            'data' => new StockTransferResource($stockTransfer)
        ]);
    }

    public function cancel(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'عملية النقل ملغاة مسبقاً'
            ], 422);
        }

        if ($stockTransfer->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء عملية نقل غير مكتملة'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $fromStock = Stock::where([
                'warehouse_id' => $stockTransfer->from_warehouse_id,
                'inventory_item_id' => $stockTransfer->inventory_item_id,
            ])->lockForUpdate()->firstOrFail();

            $toStock = Stock::where([
                'warehouse_id' => $stockTransfer->to_warehouse_id,
                'inventory_item_id' => $stockTransfer->inventory_item_id,
            ])->lockForUpdate()->firstOrFail();

            if ($toStock->quantity < $stockTransfer->quantity) {
                throw new \Exception('the quantity is not available in the destination warehouse for cancellation');
            }

            $fromStock->increment('quantity', $stockTransfer->quantity);
            $toStock->decrement('quantity', $stockTransfer->quantity);

            $stockTransfer->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            $this->clearWarehousesCache([
                $stockTransfer->from_warehouse_id,
                $stockTransfer->to_warehouse_id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء عملية النقل بنجاح',
                'data' => new StockTransferResource($stockTransfer->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'فشل إلغاء عملية النقل',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function stats(Request $request)
    {
        $stats = Cache::remember('stock-transfers-stats', 300, function() {
            return [
                'total_transfers' => StockTransfer::count(),
                'completed_transfers' => StockTransfer::where('status', 'completed')->count(),
                'cancelled_transfers' => StockTransfer::where('status', 'cancelled')->count(),
                'pending_transfers' => StockTransfer::where('status', 'pending')->count(),
                'total_quantity_transferred' => StockTransfer::where('status', 'completed')
                    ->sum('quantity'),
                'transfers_today' => StockTransfer::whereDate('created_at', today())->count(),
                'most_active_warehouse' => $this->getMostActiveWarehouse(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    protected function checkAndDispatchLowStockEvents(
        Stock $fromStock, 
        Stock $toStock, 
        InventoryItem $item
    ): void {
        $fromStock->refresh();
        $toStock->refresh();

        if ($fromStock->quantity < $item->min_stock_level) {
            LowStockDetectedEvent::dispatch(
                $item,
                $fromStock->warehouse,
                $fromStock->quantity,
                $item->min_stock_level,
                $fromStock
            );
        }

        if ($toStock->quantity < $item->min_stock_level) {
            LowStockDetectedEvent::dispatch(
                $item,
                $toStock->warehouse,
                $toStock->quantity,
                $item->min_stock_level,
                $toStock
            );
        }
    }

    protected function clearWarehousesCache(array $warehouseIds)
    {
        foreach ($warehouseIds as $warehouseId) {
            Cache::tags(['warehouse', "warehouse-{$warehouseId}"])->flush();
        }
    }


    protected function getMostActiveWarehouse()
    {
        $warehouse = Warehouse::withCount([
            'outgoingTransfers',
            'incomingTransfers'
        ])
        ->orderByDesc(DB::raw('outgoing_transfers_count + incoming_transfers_count'))
        ->first();

        if (!$warehouse) {
            return null;
        }

        return [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'total_transfers' => $warehouse->outgoing_transfers_count + $warehouse->incoming_transfers_count,
        ];
    }
}
