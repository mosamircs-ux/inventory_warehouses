<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

class Warehouse extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'name',
        'location',
        'is_active',
    ];

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [];

    protected $appends = [
        'full_address',
        'total_items',
        'status_label',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'warehouse_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    public function inventoryItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            InventoryItem::class,  
            Stock::class,          
            'warehouse_id',        
            'id',                  
            'id',                  
            'inventory_item_id'    
        );
    }

    public function allTransfers(): Builder
    {
        return StockTransfer::where('from_warehouse_id', $this->id)
            ->orWhere('to_warehouse_id', $this->id);
    }

    protected function totalItems(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->stocks()->sum('quantity'),
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->is_active ? 'نشط' : 'غير نشط',
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => ucfirst($value),
            set: fn(string $value) => strtolower(trim($value)),
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%");
        });
    }

    public function scopeHasInventoryItem(Builder $query, int $itemId): Builder
    {
        return $query->whereHas('stocks', function($q) use ($itemId) {
            $q->where('inventory_item_id', $itemId)
              ->where('quantity', '>', 0);
        });
    }

    public function scopeWithLowStock(Builder $query): Builder
    {
        return $query->whereHas('stocks', function($q) {
            $q->whereColumn('quantity', '<', 'inventory_items.min_stock_level');
        });
    }

    public function getInventory(bool $fresh = false): \Illuminate\Support\Collection
    {

        if ($fresh) {
            $this->clearInventoryCache();
        }

        return Cache::tags(['warehouse', "warehouse-{$this->id}"])
            ->remember("warehouse-{
                $this->id}-inventory", 
                3600, 
                function() {
                    return $this->stocks->load('inventoryItem');
                }
            );
        }
    }