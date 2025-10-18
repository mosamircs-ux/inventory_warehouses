<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\WarehouseResource;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('location', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $warehouses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WarehouseResource::collection($warehouses),
            'meta' => [
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
                'per_page' => $warehouses->perPage(),
                'total' => $warehouses->total(),
            ]
        ]);
    }

    public function show($id)
    {
        $warehouse = Warehouse::with(['stocks.inventoryItem'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    public function getInventory($id)
    {
        $inventory = Cache::tags(['warehouse', "warehouse-{$id}"])
            ->remember("warehouse-{$id}-inventory", 3600, function () use ($id) {
                return Warehouse::with(['stocks.inventoryItem'])
                    ->findOrFail($id)
                    ->stocks;
            });

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ]);
    }

    public function store(StoreWarehouseRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المستودع بنجاح',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    public function update(UpdateWarehouseRequest $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($request->validated());

        Cache::tags(['warehouse', "warehouse-{$warehouse->id}"])->flush();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المستودع بنجاح',
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مستودع به مخزون غير صفر',
            ], 422);
        }

        $warehouse->delete();

        Cache::tags(['warehouse', "warehouse-{$warehouse->id}"])->flush();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستودع بنجاح',
        ]);
    }
}
