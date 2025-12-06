<?php

namespace Janstro\InventorySystem\Config;

/**
 * RBAC Permissions Configuration - CORRECTED
 * Clear differentiation between Staff, Admin, and Superadmin
 * 
 * Path: src/Config/Permissions.php
 * Date: 2025-11-26
 */
class Permissions
{
    /**
     * Role Hierarchy
     * - Staff: Basic operations (view, create PO/SO, receive goods)
     * - Admin: All staff + manage suppliers/customers/reports
     * - Superadmin: All admin + user management + audit logs
     */
    const ROLES = [
        'superadmin' => ['level' => 3, 'inherit' => 'admin'],
        'admin' => ['level' => 2, 'inherit' => 'staff'],
        'staff' => ['level' => 1, 'inherit' => null]
    ];

    /**
     * Page Access Control
     * FIXED: Clear separation between admin and superadmin
     */
    const PAGE_ACCESS = [
        // ===================================
        // STAFF ACCESS (Level 1)
        // ===================================
        'dashboard' => ['staff', 'admin', 'superadmin'],
        'inventory' => ['staff', 'admin', 'superadmin'],
        'stock-movements' => ['staff', 'admin', 'superadmin'],
        'goods-receipt' => ['staff', 'admin', 'superadmin'],      // Staff can receive goods (MIGO)
        'purchase-orders' => ['staff', 'admin', 'superadmin'],    // Staff can create POs
        'sales-orders' => ['staff', 'admin', 'superadmin'],       // Staff can create SOs
        'inquiries' => ['staff', 'admin', 'superadmin'],          // Staff can view inquiries

        // ===================================
        // ADMIN ACCESS (Level 2)
        // ===================================
        'suppliers' => ['admin', 'superadmin'],                   // Only admin+
        'customers' => ['admin', 'superadmin'],                   // Only admin+
        'invoices' => ['admin', 'superadmin'],                    // Only admin+
        'reports' => ['admin', 'superadmin'],                     // Only admin+
        'stock-requirements' => ['admin', 'superadmin'],          // Material planning

        // ===================================
        // SUPERADMIN EXCLUSIVE (Level 3)
        // ===================================
        'users' => ['superadmin'],                                // ONLY superadmin
        'audit-logs' => ['superadmin'],                           // ONLY superadmin
        'system-settings' => ['superadmin']                       // ONLY superadmin
    ];

    /**
     * Action-Level Permissions
     * FIXED: Granular control differentiating admin vs superadmin
     */
    const ACTIONS = [
        'inventory' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['admin', 'superadmin'],                  // Only admin can add items
            'edit' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],                           // Only superadmin can delete
            'adjust' => ['admin', 'superadmin']                   // Stock adjustments
        ],

        'purchase_orders' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],         // Staff can create
            'approve' => ['admin', 'superadmin'],                 // Only admin can approve
            'receive' => ['staff', 'admin', 'superadmin'],        // Staff can receive (MIGO)
            'cancel' => ['admin', 'superadmin']
        ],

        'sales_orders' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],         // Staff can create
            'invoice' => ['admin', 'superadmin'],                 // Only admin can process invoice
            'cancel' => ['admin', 'superadmin']
        ],

        'inquiries' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'update' => ['staff', 'admin', 'superadmin'],         // Staff can update status
            'convert' => ['admin', 'superadmin'],                 // Only admin can convert to SO
            'delete' => ['admin', 'superadmin']
        ],

        'suppliers' => [
            'view' => ['admin', 'superadmin'],                    // Admin only
            'create' => ['admin', 'superadmin'],
            'edit' => ['admin', 'superadmin'],
            'delete' => ['superadmin']                            // Only superadmin can delete
        ],

        'customers' => [
            'view' => ['admin', 'superadmin'],                    // Admin only
            'create' => ['admin', 'superadmin'],
            'edit' => ['admin', 'superadmin'],
            'delete' => ['superadmin']
        ],

        'invoices' => [
            'view' => ['admin', 'superadmin'],                    // Admin only
            'create' => ['admin', 'superadmin'],
            'edit' => ['admin', 'superadmin'],
            'delete' => ['superadmin']
        ],

        'reports' => [
            'view' => ['admin', 'superadmin'],                    // Admin only
            'export' => ['admin', 'superadmin']
        ],

        'users' => [
            'view' => ['superadmin'],                             // ONLY superadmin
            'create' => ['superadmin'],
            'edit' => ['superadmin'],
            'delete' => ['superadmin']
        ],

        'audit_logs' => [
            'view' => ['superadmin'],                             // ONLY superadmin
            'export' => ['superadmin']
        ]
    ];

    /**
     * Check if user can access a page
     */
    public static function canAccess(string $role, string $page): bool
    {
        $role = strtolower($role);
        return in_array($role, self::PAGE_ACCESS[$page] ?? []);
    }

    /**
     * Check if user can perform an action
     */
    public static function canPerform(string $role, string $module, string $action): bool
    {
        $role = strtolower($role);
        $allowed = self::ACTIONS[$module][$action] ?? [];
        return in_array($role, $allowed);
    }

    /**
     * Get user's accessible pages
     */
    public static function getAccessiblePages(string $role): array
    {
        $role = strtolower($role);
        $pages = [];

        foreach (self::PAGE_ACCESS as $page => $roles) {
            if (in_array($role, $roles)) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Get role description for display
     */
    public static function getRoleDescription(string $role): string
    {
        $descriptions = [
            'staff' => 'Can view inventory, create orders, and receive goods',
            'admin' => 'Can manage suppliers, customers, reports, and approve orders',
            'superadmin' => 'Full system access including user management and audit logs'
        ];

        return $descriptions[strtolower($role)] ?? 'Unknown role';
    }
}
