<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\SupplierRepository;
use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;

class SupplierService
{
    private SupplierRepository $supplierRepo;
    private PurchaseOrderRepository $orderRepo;

    public function __construct()
    {
        $this->supplierRepo = new SupplierRepository();
        $this->orderRepo = new PurchaseOrderRepository();
    }

    /* Get all suppliers */
    public function getAllSuppliers(): array
    {
        $suppliers = $this->supplierRepo->getAll();
        return array_map(fn($supplier) => $supplier->toArray(), $suppliers);
    }

    /*Get supplier by ID */
    public function getSupplierById(int $supplierId): ?array
    {
        $supplier = $this->supplierRepo->findById($supplierId);
        return $supplier ? $supplier->toArray() : null;
    }

    /* Create new supplier */
    public function createSupplier(array $data): array
    {
        // Validate required fields
        if (empty($data['supplier_name'])) {
            throw new \Exception('Supplier name is required');
        }

        // Validate email if provided
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email format');
            }
        }

        $supplierId = $this->supplierRepo->create($data);

        if (!$supplierId) {
            throw new \Exception('Failed to create supplier');
        }

        return [
            'supplier_id' => $supplierId,
            'message' => 'Supplier created successfully'
        ];
    }

    /*Update supplier */
    public function updateSupplier(int $supplierId, array $data): bool
    {
        // Check if supplier exists
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) {
            throw new \Exception('Supplier not found');
        }

        // Validate email if provided
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email format');
            }
        }

        return $this->supplierRepo->update($supplierId, $data);
    }

    /* Delete supplier */
    public function deleteSupplier(int $supplierId): bool
    {
        // Check if supplier exists
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) {
            throw new \Exception('Supplier not found');
        }

        // Check if supplier has active purchase orders
        $orders = $this->orderRepo->getAll();
        $hasActiveOrders = false;

        foreach ($orders as $order) {
            if ($order->supplier_id === $supplierId && $order->status === 'pending') {
                $hasActiveOrders = true;
                break;
            }
        }

        if ($hasActiveOrders) {
            throw new \Exception('Cannot delete supplier with active purchase orders');
        }

        return $this->supplierRepo->delete($supplierId);
    }

    /* Get supplier's purchase orders */
    public function getSupplierOrders(int $supplierId): array
    {
        // Verify supplier exists
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) {
            throw new \Exception('Supplier not found');
        }

        // Get all orders and filter by supplier
        $allOrders = $this->orderRepo->getAll();
        $supplierOrders = array_filter($allOrders, function ($order) use ($supplierId) {
            return $order->supplier_id === $supplierId;
        });

        return array_map(fn($order) => $order->toArray(), array_values($supplierOrders));
    }
}
