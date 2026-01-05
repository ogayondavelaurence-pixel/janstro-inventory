<?php

namespace Janstro\InventorySystem\Models;

/**
 * ============================================================================
 * ITEM MODEL - ERP COMPLIANT v2.0
 * ============================================================================
 */
class Item
{
    public int $item_id;
    public string $item_name;
    public string $sku;
    public int $category_id;
    public string $category_name;
    public int $quantity;
    public string $unit;
    public int $reorder_level;
    public float $unit_price;
    public string $status;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct()
    {
        // Empty constructor for flexible instantiation
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name ?? 'Uncategorized',
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'reorder_level' => $this->reorder_level,
            'unit_price' => $this->unit_price,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get total value (quantity × unit_price)
     */
    public function getTotalValue(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }

    /**
     * Check if item is low stock
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    /**
     * Check if out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity === 0;
    }
}
