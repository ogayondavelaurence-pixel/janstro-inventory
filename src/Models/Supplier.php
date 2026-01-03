<?php

namespace Janstro\InventorySystem\Models;

class Supplier
{
    public int $supplier_id;
    public string $supplier_name;
    public ?string $contact_info;
    public ?string $address;
    public ?string $email;
    public string $created_at;

    public function __construct(array $data)
    {
        $this->supplier_id = (int)$data['supplier_id'];
        $this->supplier_name = $data['supplier_name'];
        $this->contact_info = $data['contact_info'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'contact_info' => $this->contact_info,
            'address' => $this->address,
            'email' => $this->email,
            'created_at' => $this->created_at
        ];
    }
}
