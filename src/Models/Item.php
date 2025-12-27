<?php

namespace Janstro\InventorySystem\Models;

class Item
{
    public int $item_id;
    public string $item_name;
    public int $category_id;
    public string $category_name;
    public int $quantity;
    public string $unit;
    public int $reorder_level;
    public float $unit_price;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->item_id = (int)$data['item_id'];
        $this->item_name = $data['item_name'];
        $this->category_id = (int)$data['category_id'];
        $this->category_name = $data['category_name'] ?? '';
        $this->quantity = (int)$data['quantity'];
        $this->unit = $data['unit'] ?? 'pcs';
        $this->reorder_level = (int)($data['reorder_level'] ?? 10);
        $this->unit_price = (float)$data['unit_price'];
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    public function getTotalValue(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'reorder_level' => $this->reorder_level,
            'unit_price' => $this->unit_price,
            'total_value' => $this->getTotalValue(),
            'is_low_stock' => $this->isLowStock(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
