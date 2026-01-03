<?php

namespace Janstro\InventorySystem\Models;

class User
{
    public int $user_id;
    public string $username;
    public string $name;
    public int $role_id;
    public string $role_name;
    public string $status;
    public ?string $contact_no;
    public string $created_at;

    public function __construct(array $data)
    {
        $this->user_id = (int)$data['user_id'];
        $this->username = $data['username'];
        $this->name = $data['name'] ?? '';
        $this->role_id = (int)$data['role_id'];
        $this->role_name = $data['role_name'] ?? '';
        $this->status = $data['status'] ?? 'active';
        $this->contact_no = $data['contact_no'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'username' => $this->username,
            'name' => $this->name,
            'role_id' => $this->role_id,
            'role_name' => $this->role_name,
            'status' => $this->status,
            'contact_no' => $this->contact_no,
            'created_at' => $this->created_at
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
