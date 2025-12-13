/**
 * ============================================================================
 * JANSTRO IMS - RBAC (Role-Based Access Control) v2.2 CRITICAL FIX
 * ============================================================================
 * Path: frontend/assets/js/rbac.js
 *
 * 🚨 CRITICAL FIXES IN THIS VERSION:
 * ✅ SUPERADMIN can now access Profile Settings (WAS BLOCKED)
 * ✅ ALL users (staff, admin, superadmin) can access profile-settings.html
 * ✅ Added explicit whitelist for email-settings (superadmin only)
 * ✅ Added public access for forgot-password pages
 * ✅ Fixed getRole() method (was missing)
 * ✅ Fixed user.role vs user.role_name handling
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
 * ============================================================================
 */

(function (window) {
  "use strict";

  const RBAC = {
    // ========================================================================
    // CONFIGURATION
    // ========================================================================
    config: {
      roles: {
        superadmin: 3,
        admin: 2,
        staff: 1,
      },

      pages: {
        // ===== PUBLIC PAGES (NO AUTH REQUIRED) =====
        index: ["public"],
        inquiry: ["public"],
        "forgot-password": ["public"], // ✅ NEW
        "reset-password": ["public"], // ✅ NEW

        // ===== ALL AUTHENTICATED USERS =====
        "profile-settings": ["staff", "admin", "superadmin"], // ✅ CRITICAL FIX
        profile: ["staff", "admin", "superadmin"], // ✅ CRITICAL FIX

        // ===== STAFF ACCESS =====
        dashboard: ["staff", "admin", "superadmin"],
        inventory: ["staff", "admin", "superadmin"],
        "stock-movements": ["staff", "admin", "superadmin"],
        "goods-receipt": ["staff", "admin", "superadmin"],
        "purchase-orders": ["staff", "admin", "superadmin"],
        "sales-orders": ["staff", "admin", "superadmin"],
        inquiries: ["staff", "admin", "superadmin"],

        // ===== ADMIN ACCESS =====
        suppliers: ["admin", "superadmin"],
        customers: ["admin", "superadmin"],
        invoices: ["admin", "superadmin"],
        reports: ["admin", "superadmin"],
        "stock-requirements": ["admin", "superadmin"],

        // ===== SUPERADMIN ONLY =====
        users: ["superadmin"],
        "audit-logs": ["superadmin"],
        "email-settings": ["superadmin"], // ✅ CRITICAL FIX
        "system-settings": ["superadmin"],
      },

      // ACTION-LEVEL PERMISSIONS
      actions: {
        inventory: {
          view: ["staff", "admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
          adjust: ["admin", "superadmin"],
        },

        purchase_orders: {
          view: ["staff", "admin", "superadmin"],
          create: ["staff", "admin", "superadmin"],
          approve: ["admin", "superadmin"],
          receive: ["staff", "admin", "superadmin"],
          cancel: ["admin", "superadmin"],
        },

        sales_orders: {
          view: ["staff", "admin", "superadmin"],
          create: ["staff", "admin", "superadmin"],
          invoice: ["admin", "superadmin"],
          cancel: ["admin", "superadmin"],
        },

        inquiries: {
          view: ["staff", "admin", "superadmin"],
          update: ["staff", "admin", "superadmin"],
          convert: ["admin", "superadmin"],
          delete: ["admin", "superadmin"],
        },

        suppliers: {
          view: ["admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
        },

        customers: {
          view: ["admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
        },

        invoices: {
          view: ["admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
        },

        reports: {
          view: ["admin", "superadmin"],
          export: ["admin", "superadmin"],
        },

        users: {
          view: ["superadmin"],
          create: ["superadmin"],
          edit: ["superadmin"],
          delete: ["superadmin"],
        },

        audit_logs: {
          view: ["superadmin"],
          export: ["superadmin"],
        },
      },
    },

    // ========================================================================
    // USER MANAGEMENT
    // ========================================================================

    /**
     * Get current user from localStorage (FIXED)
     */
    getUser() {
      try {
        const data =
          localStorage.getItem("janstro_user") ||
          sessionStorage.getItem("janstro_user");

        if (!data) {
          console.warn("⚠️ RBAC: No user data found");
          return null;
        }

        const user = JSON.parse(data);

        // ✅ CRITICAL FIX: Handle both role and role_name
        user.role = (user.role || user.role_name || "").toLowerCase();

        // Validate user structure
        if (!user.user_id || !user.username || !user.role) {
          console.error("❌ RBAC: Invalid user structure", user);
          return null;
        }

        console.log(`✅ RBAC: User loaded - ${user.username} (${user.role})`);
        return user;
      } catch (e) {
        console.error("❌ RBAC: Parse error", e);
        return null;
      }
    },

    /**
     * Get user's role (CRITICAL FIX - WAS MISSING)
     */
    getRole() {
      const user = this.getUser();
      return user ? user.role : null;
    },

    /**
     * Get role level (1=staff, 2=admin, 3=superadmin)
     */
    getRoleLevel(role) {
      return this.config.roles[role.toLowerCase()] || 0;
    },

    // ========================================================================
    // PERMISSION CHECKS
    // ========================================================================

    /**
     * Check if user can access a page
     */
    hasPageAccess(page, role) {
      const allowed = this.config.pages[page];
      if (!allowed) {
        console.warn(`⚠️ RBAC: Unknown page "${page}"`);
        return false;
      }

      // ✅ Allow public pages
      if (allowed.includes("public")) {
        return true;
      }

      return allowed.includes(role.toLowerCase());
    },

    /**
     * Check if user can perform an action
     */
    can(module, action) {
      const user = this.getUser();
      if (!user) return false;

      const modActions = this.config.actions[module];
      if (!modActions) {
        console.warn(`⚠️ RBAC: Unknown module "${module}"`);
        return false;
      }

      const allowed = modActions[action];
      if (!allowed) {
        console.warn(
          `⚠️ RBAC: Unknown action "${action}" in module "${module}"`
        );
        return false;
      }

      return allowed.includes(user.role);
    },

    /**
     * Check if user has minimum role level
     */
    hasMinRole(minimumRole) {
      const user = this.getUser();
      if (!user) return false;

      const userLevel = this.getRoleLevel(user.role);
      const minLevel = this.getRoleLevel(minimumRole);

      return userLevel >= minLevel;
    },

    // ========================================================================
    // PAGE-LEVEL ENFORCEMENT
    // ========================================================================

    /**
     * Enforce access control on current page
     */
    enforce() {
      const user = this.getUser();
      const path = window.location.pathname.split("/").pop();
      const page = path.replace(".html", "") || "index";

      console.log(`🔍 RBAC: Checking access for page "${page}"`);

      // ✅ Public pages (no auth required)
      const publicPages = [
        "index",
        "inquiry",
        "forgot-password",
        "reset-password",
        "",
      ];
      if (publicPages.includes(page)) {
        console.log(`✅ RBAC: Public page "${page}" - access granted`);
        return true;
      }

      // Check authentication
      if (!user || !user.role) {
        console.error(`❌ RBAC: Not authenticated, redirecting to login`);
        window.location.href = "index.html";
        return false;
      }

      // ✅ CRITICAL FIX: Profile pages accessible to ALL authenticated users
      if (page === "profile-settings" || page === "profile") {
        console.log(
          `✅ RBAC: Profile page accessible to all users - ${user.role} granted`
        );
        return true;
      }

      // Check page access
      if (!this.hasPageAccess(page, user.role)) {
        console.error(
          `❌ RBAC: Access denied - ${user.role} cannot access "${page}"`
        );
        this.showAccessDenied(user.role, page);
        setTimeout(() => (window.location.href = "dashboard.html"), 2000);
        return false;
      }

      console.log(`✅ RBAC: Access granted - ${user.role} → "${page}"`);
      return true;
    },

    /**
     * Show access denied screen
     */
    showAccessDenied(role, page) {
      document.body.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-family:system-ui;text-align:center;padding:20px;">
          <div style="font-size:5rem;margin-bottom:20px;">🚫</div>
          <h1 style="margin:0 0 10px;font-size:2.5rem;">Access Denied</h1>
          <p style="opacity:0.9;font-size:1.2rem;margin:10px 0;">
            Role: <strong>${role.toUpperCase()}</strong> cannot access <strong>"${page}"</strong>
          </p>
          <p style="opacity:0.7;margin-top:20px;">Redirecting to dashboard...</p>
        </div>
      `;
    },

    // ========================================================================
    // UI ELEMENT CONTROL
    // ========================================================================

    /**
     * Filter sidebar menu based on role
     */
    filterSidebar() {
      const user = this.getUser();
      if (!user) return;

      document.querySelectorAll(".menu-item").forEach((item) => {
        const href = item.getAttribute("href");
        if (!href) return;

        const page = href.replace(".html", "").split("/").pop();
        const hasAccess = this.hasPageAccess(page, user.role);

        item.style.display = hasAccess ? "" : "none";

        if (!hasAccess) {
          console.log(`🔒 RBAC: Hiding menu item "${page}"`);
        }
      });
    },

    /**
     * Hide unauthorized UI elements
     */
    hideUnauthorized(module, actionMap) {
      const user = this.getUser();
      if (!user) return;

      Object.entries(actionMap).forEach(([action, selector]) => {
        if (!this.can(module, action)) {
          document.querySelectorAll(selector).forEach((el) => {
            el.style.display = "none";
            el.disabled = true;
            el.classList.add("rbac-hidden");
          });
          console.log(
            `🔒 RBAC: Hiding ${selector} (${module}.${action} denied)`
          );
        }
      });
    },

    /**
     * Apply action-level restrictions to page
     */
    enforceActions(module, actionMap) {
      this.hideUnauthorized(module, actionMap);
    },

    // ========================================================================
    // WORKFLOW VALIDATION
    // ========================================================================

    /**
     * Validate business workflow actions
     */
    validateWorkflow(action) {
      const user = this.getUser();
      if (!user) return false;

      const workflows = {
        // Customer Inquiry → Admin converts to SO
        convert_inquiry: () => this.can("inquiries", "convert"),

        // Staff creates PO → Staff receives goods
        create_po: () => this.can("purchase_orders", "create"),
        approve_po: () => this.can("purchase_orders", "approve"),
        receive_goods: () => this.can("purchase_orders", "receive"),

        // Admin creates SO → Admin processes invoice
        create_so: () => this.can("sales_orders", "create"),
        process_invoice: () => this.can("sales_orders", "invoice"),

        // Master data - Admin only
        create_customer: () => this.can("customers", "create"),
        create_supplier: () => this.can("suppliers", "create"),
        create_item: () => this.can("inventory", "create"),

        // User management - Superadmin only
        manage_users: () => this.can("users", "view"),
        view_audit_logs: () => this.can("audit_logs", "view"),
      };

      const result = workflows[action] ? workflows[action]() : false;
      console.log(`🔍 RBAC: Workflow "${action}" → ${result ? "✅" : "❌"}`);
      return result;
    },

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Get accessible pages for current user
     */
    getAccessiblePages() {
      const user = this.getUser();
      if (!user) return [];

      const pages = [];
      Object.entries(this.config.pages).forEach(([page, roles]) => {
        if (roles.includes("public") || roles.includes(user.role)) {
          pages.push(page);
        }
      });

      return pages;
    },

    /**
     * Get role description
     */
    getRoleDescription(role) {
      const descriptions = {
        staff: "Operations Staff - Can create orders and receive goods",
        admin:
          "Administrator - Manages suppliers, customers, and approves orders",
        superadmin:
          "Super Administrator - Full system access including user management",
      };

      return descriptions[role.toLowerCase()] || "Unknown role";
    },

    /**
     * Check if role is valid
     */
    isValidRole(role) {
      return this.config.roles.hasOwnProperty(role.toLowerCase());
    },

    /**
     * Get all available roles
     */
    getAllRoles() {
      return Object.keys(this.config.roles);
    },

    // ========================================================================
    // DEBUGGING HELPERS
    // ========================================================================

    /**
     * Log current permissions for debugging
     */
    debugPermissions() {
      const user = this.getUser();
      if (!user) {
        console.log("❌ No user logged in");
        return;
      }

      console.log("========================================");
      console.log("🔐 RBAC DEBUG INFO");
      console.log("========================================");
      console.log("User:", user.username);
      console.log("Role:", user.role);
      console.log("Role Level:", this.getRoleLevel(user.role));
      console.log("Accessible Pages:", this.getAccessiblePages());
      console.log("========================================");
    },
  };

  // ========================================================================
  // AUTO-INITIALIZATION
  // ========================================================================
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      RBAC.enforce();
      RBAC.filterSidebar();
    });
  } else {
    RBAC.enforce();
    RBAC.filterSidebar();
  }

  // ========================================================================
  // GLOBAL EXPORT
  // ========================================================================
  window.RBAC = RBAC;

  console.log("✅ RBAC v2.2 CRITICAL FIX Loaded");
  console.log("🔒 Role-based access control active");
  console.log("👤 Current role:", RBAC.getRole() || "Not authenticated");
})(window);
