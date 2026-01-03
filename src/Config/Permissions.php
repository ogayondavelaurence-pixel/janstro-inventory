<?php

namespace Janstro\InventorySystem\Config;

/**
 * ============================================================================
 * PERMISSIONS CONFIGURATION v2.5 (FIXED)
 * ============================================================================
 * Path: src/Config/Permissions.php
 * 
 * PRODUCTION FIXES:
 * ✅ Staff can now VIEW suppliers (for creating POs)
 * ✅ Staff can now VIEW customers (for creating SOs)
 * ✅ Aligned with RBAC.js permissions
 * ✅ Admin/Superadmin retain full CRUD access
 * ============================================================================
 */

class Permissions
{
    /**
     * Define role hierarchy levels
     */
    const ROLE_LEVELS = [
        'staff' => 1,
        'admin' => 2,
        'superadmin' => 3,
    ];

    /**
     * ========================================================================
     * MODULE PERMISSIONS
     * ========================================================================
     * Format: 'module' => [
     *     'action' => ['role1', 'role2'],
     * ]
     */
    private static $permissions = [

        // ====================================================================
        // INVENTORY MANAGEMENT
        // ====================================================================
        'items' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'adjust_stock' => ['admin', 'superadmin'],
            'view_movements' => ['staff', 'admin', 'superadmin'],
            'view_overview' => ['staff', 'admin', 'superadmin'],  // ✅ ADD THIS
        ],

        // ====================================================================
        // CATEGORIES
        // ====================================================================
        'categories' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
        ],

        // ====================================================================
        // PURCHASE ORDERS (SAP ME21N)
        // ====================================================================
        'purchase_orders' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],     // Staff can create
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'approve' => ['admin', 'superadmin'],
            'cancel' => ['admin', 'superadmin'],
            'mark_delivered' => ['staff', 'admin', 'superadmin'],
        ],

        // ====================================================================
        // PURCHASE REQUISITIONS (SAP ME51N)
        // ====================================================================
        'purchase_requisitions' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'approve' => ['admin', 'superadmin'],
            'reject' => ['admin', 'superadmin'],
            'convert_to_po' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // SALES ORDERS (SAP VA01)
        // ====================================================================
        'sales_orders' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],     // Staff can create
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'cancel' => ['admin', 'superadmin'],
            'mark_completed' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // GOODS RECEIPT (SAP MIGO)
        // ====================================================================
        'goods_receipt' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],     // Staff can receive
            'cancel' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // STOCK REQUIREMENTS (SAP MD04)
        // ====================================================================
        'stock_requirements' => [
            'view' => ['admin', 'superadmin'],
            'analyze' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // INVOICES (SAP VF01)
        // ====================================================================
        'invoices' => [
            'view' => ['admin', 'superadmin'],
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'send_email' => ['admin', 'superadmin'],
            'download_pdf' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // SUPPLIERS (SAP XK01/XK02) - FIXED
        // ====================================================================
        'suppliers' => [
            'view' => ['staff', 'admin', 'superadmin'],       // ✅ FIXED: Staff can view
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
        ],

        // ====================================================================
        // CUSTOMERS (SAP XD01/XD02) - FIXED
        // ====================================================================
        'customers' => [
            'view' => ['staff', 'admin', 'superadmin'],       // ✅ FIXED: Staff can view
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
        ],

        // ====================================================================
        // BILL OF MATERIALS (SAP CS01)
        // ====================================================================
        'bom' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['admin', 'superadmin'],
            'update' => ['admin', 'superadmin'],
            'delete' => ['superadmin'],
            'explosion' => ['staff', 'admin', 'superadmin'],
        ],

        // ====================================================================
        // TRANSACTIONS / STOCK MOVEMENTS (SAP MB51)
        // ====================================================================
        'transactions' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'create' => ['staff', 'admin', 'superadmin'],
            'export' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // REPORTS & ANALYTICS
        // ====================================================================
        'reports' => [
            'view' => ['admin', 'superadmin'],
            'export' => ['admin', 'superadmin'],
            'schedule' => ['superadmin'],
        ],

        'analytics' => [
            'view_dashboard' => ['admin', 'superadmin'],
            'export_data' => ['admin', 'superadmin'],
        ],

        // ====================================================================
        // USER MANAGEMENT
        // ====================================================================
        'users' => [
            'view' => ['superadmin'],
            'create' => ['superadmin'],
            'update' => ['superadmin'],
            'delete' => ['superadmin'],
            'reset_password' => ['superadmin'],
        ],

        // ====================================================================
        // AUDIT LOGS
        // ====================================================================
        'audit_logs' => [
            'view' => ['superadmin'],
            'export' => ['superadmin'],
            'delete' => ['superadmin'],
        ],

        // ====================================================================
        // SYSTEM SETTINGS
        // ====================================================================
        'settings' => [
            'view' => ['superadmin'],
            'update' => ['superadmin'],
        ],

        'email_settings' => [
            'view' => ['superadmin'],
            'update' => ['superadmin'],
            'test' => ['superadmin'],
        ],

        // ====================================================================
        // NOTIFICATIONS
        // ====================================================================
        'notifications' => [
            'view' => ['staff', 'admin', 'superadmin'],
            'mark_read' => ['staff', 'admin', 'superadmin'],
            'delete' => ['staff', 'admin', 'superadmin'],
        ],
    ];

    /**
     * ========================================================================
     * CHECK USER PERMISSION
     * ========================================================================
     * 
     * @param string $module - Module name (e.g., 'items', 'suppliers')
     * @param string $action - Action name (e.g., 'view', 'create')
     * @param string $userRole - User's role (e.g., 'staff', 'admin')
     * @return bool - True if user has permission
     */
    public static function can(string $module, string $action, string $userRole): bool
    {
        $userRole = strtolower($userRole);

        // Superadmin has access to everything
        if ($userRole === 'superadmin') {
            return true;
        }

        // Check if module exists
        if (!isset(self::$permissions[$module])) {
            error_log("Permissions: Module '$module' not found");
            return false;
        }

        // Check if action exists
        if (!isset(self::$permissions[$module][$action])) {
            error_log("Permissions: Action '$action' not found in module '$module'");
            return false;
        }

        // Check if user role is allowed
        return in_array($userRole, self::$permissions[$module][$action]);
    }

    /**
     * ========================================================================
     * CHECK MINIMUM ROLE LEVEL
     * ========================================================================
     * 
     * @param string $userRole - User's current role
     * @param string $minimumRole - Minimum required role
     * @return bool - True if user meets minimum requirement
     */
    public static function hasMinimumRole(string $userRole, string $minimumRole): bool
    {
        $userRole = strtolower($userRole);
        $minimumRole = strtolower($minimumRole);

        $userLevel = self::ROLE_LEVELS[$userRole] ?? 0;
        $minLevel = self::ROLE_LEVELS[$minimumRole] ?? 0;

        return $userLevel >= $minLevel;
    }

    /**
     * ========================================================================
     * GET ALL PERMISSIONS FOR ROLE
     * ========================================================================
     * 
     * @param string $role - Role name
     * @return array - All allowed actions grouped by module
     */
    public static function getRolePermissions(string $role): array
    {
        $role = strtolower($role);
        $allowed = [];

        foreach (self::$permissions as $module => $actions) {
            foreach ($actions as $action => $roles) {
                if (in_array($role, $roles)) {
                    $allowed[$module][] = $action;
                }
            }
        }

        return $allowed;
    }

    /**
     * ========================================================================
     * GET ROLE DESCRIPTION
     * ========================================================================
     */
    public static function getRoleDescription(string $role): string
    {
        $descriptions = [
            'staff' => 'Operations Staff - Can create orders, receive goods, and view masters',
            'admin' => 'Administrator - Manages suppliers, customers, and approves orders',
            'superadmin' => 'Super Administrator - Full system access including user management',
        ];

        return $descriptions[strtolower($role)] ?? 'Unknown role';
    }

    /**
     * ========================================================================
     * VALIDATE WORKFLOW PERMISSION
     * ========================================================================
     * Checks complex multi-step permissions (e.g., create PO requires view suppliers)
     */
    public static function canExecuteWorkflow(string $workflow, string $userRole): bool
    {
        $workflows = [
            'create_purchase_order' => [
                ['suppliers', 'view'],
                ['items', 'view'],
                ['purchase_orders', 'create'],
            ],
            'create_sales_order' => [
                ['customers', 'view'],
                ['items', 'view'],
                ['sales_orders', 'create'],
            ],
            'receive_goods' => [
                ['purchase_orders', 'view'],
                ['goods_receipt', 'create'],
            ],
            'generate_invoice' => [
                ['sales_orders', 'view'],
                ['invoices', 'create'],
            ],
        ];

        if (!isset($workflows[$workflow])) {
            return false;
        }

        foreach ($workflows[$workflow] as [$module, $action]) {
            if (!self::can($module, $action, $userRole)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ========================================================================
     * EXPORT FOR FRONTEND (JSON)
     * ========================================================================
     * Exports permissions in format compatible with RBAC.js
     */
    public static function exportForFrontend(): array
    {
        return [
            'permissions' => self::$permissions,
            'role_levels' => self::ROLE_LEVELS,
        ];
    }
}
