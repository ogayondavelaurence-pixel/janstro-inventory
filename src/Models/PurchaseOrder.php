<?php

namespace Janstro\InventorySystem\Models;

class PurchaseOrder
{
    public int $po_id;
    public int $supplier_id;
    public string $supplier_name;
    public int $item_id;
    public string $item_name;
    public int $quantity;
    public float $total_amount;
    public string $status;
    public int $created_by;
    public string $created_by_name;
    public string $po_date;

    public function __construct(array $data)
    {
        $this->po_id = (int)$data['po_id'];
        $this->supplier_id = (int)$data['supplier_id'];
        $this->supplier_name = $data['supplier_name'] ?? '';
        $this->item_id = (int)$data['item_id'];
        $this->item_name = $data['item_name'] ?? '';
        $this->quantity = (int)$data['quantity'];
        $this->total_amount = (float)$data['total_amount'];
        $this->status = $data['status'] ?? 'pending';
        $this->created_by = (int)$data['created_by'];
        $this->created_by_name = $data['created_by_name'] ?? '';
        $this->po_date = $data['po_date'] ?? date('Y-m-d H:i:s');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function toArray(): array
    {
        return [
            'po_id' => $this->po_id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_by_name' => $this->created_by_name,
            'po_date' => $this->po_date
        ];
    }
}
