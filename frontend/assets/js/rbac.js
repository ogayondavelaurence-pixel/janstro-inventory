/**
 * ============================================================================
 * JANSTRO IMS - RBAC v2.4 PRODUCTION
 * ============================================================================
 * Path: frontend/assets/js/rbac.js
 *
 * PRODUCTION CHANGES:
 * ✅ Removed 18+ development console.logs
 * ✅ Kept critical security logging only
 * ✅ Maintained all role-based access control
 * ✅ Enhanced production security monitoring
 * ✅ Preserved Purchase Requisitions & Analytics permissions
 * ============================================================================
 */

(function (window) {
  "use strict";

  const RBAC = {
    config: {
      roles: {
        superadmin: 3,
        admin: 2,
        staff: 1,
      },

      pages: {
        // ===== PUBLIC =====
        index: ["public"],
        "forgot-password": ["public"],
        "reset-password": ["public"],

        // ===== AUTHENTICATED =====
        "profile-settings": ["staff", "admin", "superadmin"],
        "privacy-settings": ["staff", "admin", "superadmin"],
        profile: ["staff", "admin", "superadmin"],

        // ===== STAFF =====
        dashboard: ["staff", "admin", "superadmin"],
        inventory: ["staff", "admin", "superadmin"],
        materials: ["staff", "admin", "superadmin"],
        "stock-movements": ["staff", "admin", "superadmin"],
        "goods-receipt": ["staff", "admin", "superadmin"],
        "purchase-orders": ["staff", "admin", "superadmin"],
        "sales-orders": ["staff", "admin", "superadmin"],
        "purchase-requisitions": ["staff", "admin", "superadmin"],

        // ===== ADMIN =====
        suppliers: ["admin", "superadmin"],
        customers: ["admin", "superadmin"],
        invoices: ["admin", "superadmin"],
        reports: ["admin", "superadmin"],
        "analytics-dashboard": ["admin", "superadmin"],
        "stock-requirements": ["admin", "superadmin"],
        "bom-management": ["admin", "superadmin"],

        // ===== SUPERADMIN =====
        users: ["superadmin"],
        "audit-logs": ["superadmin"],
        "email-settings": ["superadmin"],
        "system-settings": ["superadmin"],
        "deletion-approvals": ["superadmin"],
      },

      actions: {
        inventory: {
          view: ["staff", "admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
          adjust: ["admin", "superadmin"],
        },

        materials: {
          view: ["staff", "admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
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

        purchase_requisitions: {
          view: ["staff", "admin", "superadmin"],
          approve: ["admin", "superadmin"],
          reject: ["admin", "superadmin"],
          convert_to_po: ["admin", "superadmin"],
        },

        bills_of_materials: {
          view: ["staff", "admin", "superadmin"],
          create: ["admin", "superadmin"],
          edit: ["admin", "superadmin"],
          delete: ["superadmin"],
          explosion: ["staff", "admin", "superadmin"],
        },

        suppliers: {
          view: ["staff", "admin", "superadmin"],
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
    getUser() {
      try {
        const data =
          localStorage.getItem("janstro_user") ||
          sessionStorage.getItem("janstro_user");

        if (!data) return null;

        const user = JSON.parse(data);
        user.role = (user.role || user.role_name || "").toLowerCase();

        if (!user.user_id || !user.username || !user.role) {
          console.error("RBAC: Invalid user structure");
          return null;
        }

        return user;
      } catch (e) {
        console.error("RBAC: Parse error", e.message);
        return null;
      }
    },

    getRole() {
      const user = this.getUser();
      return user ? user.role : null;
    },

    getRoleLevel(role) {
      return this.config.roles[role.toLowerCase()] || 0;
    },

    // ========================================================================
    // PERMISSION CHECKS
    // ========================================================================
    hasPageAccess(page, role) {
      const allowed = this.config.pages[page];
      if (!allowed) return false;

      if (allowed.includes("public")) {
        return true;
      }

      return allowed.includes(role.toLowerCase());
    },

    can(module, action) {
      const user = this.getUser();
      if (!user) return false;

      const modActions = this.config.actions[module];
      if (!modActions) return false;

      const allowed = modActions[action];
      if (!allowed) return false;

      return allowed.includes(user.role);
    },

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
    enforce() {
      const user = this.getUser();
      const path = window.location.pathname.split("/").pop();
      const page = path.replace(".html", "") || "index";

      const publicPages = ["index", "forgot-password", "reset-password", ""];
      if (publicPages.includes(page)) {
        return true;
      }

      if (!user || !user.role) {
        console.error("RBAC: Not authenticated, redirecting to login");
        window.location.href = "index.html";
        return false;
      }

      if (page === "profile-settings" || page === "profile") {
        return true;
      }

      if (!this.hasPageAccess(page, user.role)) {
        console.error(
          `RBAC: Access denied - ${user.role} cannot access "${page}"`
        );
        this.showAccessDenied(user.role, page);
        setTimeout(() => (window.location.href = "dashboard.html"), 2000);
        return false;
      }

      return true;
    },

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
    filterSidebar() {
      const user = this.getUser();
      if (!user) return;

      document.querySelectorAll(".menu-item").forEach((item) => {
        const href = item.getAttribute("href");
        if (!href) return;

        const page = href.replace(".html", "").split("/").pop();
        const hasAccess = this.hasPageAccess(page, user.role);

        item.style.display = hasAccess ? "" : "none";
      });
    },

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
        }
      });
    },

    enforceActions(module, actionMap) {
      this.hideUnauthorized(module, actionMap);
    },

    // ========================================================================
    // WORKFLOW VALIDATION
    // ========================================================================
    validateWorkflow(action) {
      const user = this.getUser();
      if (!user) return false;

      const workflows = {
        create_po: () => this.can("purchase_orders", "create"),
        approve_po: () => this.can("purchase_orders", "approve"),
        receive_goods: () => this.can("purchase_orders", "receive"),

        create_so: () => this.can("sales_orders", "create"),
        process_invoice: () => this.can("sales_orders", "invoice"),

        approve_pr: () => this.can("purchase_requisitions", "approve"),
        reject_pr: () => this.can("purchase_requisitions", "reject"),
        convert_pr_to_po: () =>
          this.can("purchase_requisitions", "convert_to_po"),

        create_customer: () => this.can("customers", "create"),
        create_supplier: () => this.can("suppliers", "create"),
        create_item: () => this.can("inventory", "create"),

        manage_users: () => this.can("users", "view"),
        view_audit_logs: () => this.can("audit_logs", "view"),
      };

      const result = workflows[action] ? workflows[action]() : false;

      if (!result) {
        console.warn(`RBAC: Workflow "${action}" denied for ${user.role}`);
      }

      return result;
    },

    // ========================================================================
    // HELPER METHODS
    // ========================================================================
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

    isValidRole(role) {
      return this.config.roles.hasOwnProperty(role.toLowerCase());
    },

    getAllRoles() {
      return Object.keys(this.config.roles);
    },

    // ========================================================================
    // DEBUGGING HELPERS (Production-Safe)
    // ========================================================================
    debugPermissions() {
      const user = this.getUser();
      if (!user) {
        console.log("RBAC: No user logged in");
        return;
      }

      console.log("=".repeat(50));
      console.log("RBAC DEBUG INFO");
      console.log("=".repeat(50));
      console.log("User:", user.username);
      console.log("Role:", user.role);
      console.log("Role Level:", this.getRoleLevel(user.role));
      console.log("Accessible Pages:", this.getAccessiblePages().length);
      console.log("=".repeat(50));

      // PR Permissions
      console.log("Purchase Requisitions:");
      console.log("  - View:", this.can("purchase_requisitions", "view"));
      console.log("  - Approve:", this.can("purchase_requisitions", "approve"));
      console.log("  - Reject:", this.can("purchase_requisitions", "reject"));
      console.log(
        "  - Convert to PO:",
        this.can("purchase_requisitions", "convert_to_po")
      );
      console.log("=".repeat(50));
    },

    // ========================================================================
    // SECURITY MONITORING
    // ========================================================================
    logSecurityEvent(event, details = "") {
      // Only log critical security events in production
      const criticalEvents = [
        "ACCESS_DENIED",
        "PRIVILEGE_ESCALATION_ATTEMPT",
        "UNAUTHORIZED_ACTION",
      ];

      if (criticalEvents.some((e) => event.includes(e))) {
        console.error(`[SECURITY] ${event}:`, details);
      }
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
})(window);
