<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryItem;
use App\Http\Resources\InventoryItemResource;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('warehouse_id')) {
            $query->whereHas('stocks', function($q) use ($request) {
                $q->where('warehouse_id', $request->warehouse_id)
                  ->where('quantity', '>', 0);
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $items = $query->with(['stocks.warehouse'])->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب قائمة المنتجات بنجاح',
            'data' => InventoryItemResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ]
        ]);
    }

    public function store(StoreInventoryItemRequest  $request)
    {
        $item = InventoryItem::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المنتج بنجاح',
            'data' => new InventoryItemResource($item)
        ], 201);
    }

    public function show(InventoryItem  $inventoryItem)
    {
        $inventoryItem->load(['stocks.warehouse']);

        return response()->json([
            'success' => true,
            'data' => new InventoryItemResource($inventoryItem)
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem)
    {
        $inventoryItem->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المنتج بنجاح',
            'data' => new InventoryItemResource($inventoryItem->fresh())
        ]);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        if ($inventoryItem->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المنتج لأنه يحتوي على مخزون في المستودعات'
            ], 422);
        }

        $inventoryItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج بنجاح'      
        ], 200);
    }
    
    public function stats(InventoryItem $inventoryItem)
    {
        $stats = [
            'total_quantity' => $inventoryItem->stocks()->sum('quantity'),
            'warehouses_count' => $inventoryItem->stocks()->distinct('warehouse_id')->count(),
            'low_stock_warehouses' => $inventoryItem->stocks()
                ->where('quantity', '<', $inventoryItem->min_stock_level)
                ->with('warehouse')
                ->get(),
            'transfers_count' => $inventoryItem->stockTransfers()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
