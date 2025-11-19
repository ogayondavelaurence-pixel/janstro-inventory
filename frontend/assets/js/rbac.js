/**
 * JANSTRO IMS - COMPLETE RBAC SYSTEM v5.0
 * Enforces both PAGE-LEVEL and ACTION-LEVEL permissions
 * Date: 2025-11-19
 */

const RBAC = {
  // ============================================
  // ROLE HIERARCHY
  // ============================================
  roleHierarchy: {
    superadmin: 4,
    admin: 3,
    manager: 2,
    staff: 1,
  },

  // ============================================
  // PAGE-LEVEL PERMISSIONS (what pages each role can access)
  // ============================================
  pagePermissions: {
    superadmin: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
      "goods-receipt",
      "suppliers",
      "sales-orders",
      "reports",
      "users",
      "settings",
      "audit-logs",
    ],
    admin: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
      "goods-receipt",
      "suppliers",
      "sales-orders",
      "reports",
    ],
    staff: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
      "goods-receipt",
      "sales-orders",
    ],
  },

  // ============================================
  // ACTION-LEVEL PERMISSIONS (what actions each role can perform)
  // ============================================
  actionPermissions: {
    superadmin: {
      inventory: ["view", "add", "edit", "adjust", "delete", "export"],
      purchaseOrders: [
        "view",
        "create",
        "edit",
        "approve",
        "cancel",
        "receive",
      ],
      salesOrders: ["view", "create", "edit", "approve", "cancel", "invoice"],
      stockMovements: ["view", "adjust", "export"],
      suppliers: ["view", "create", "edit", "delete"],
      reports: ["view", "export"],
      users: ["view", "create", "edit", "delete", "deactivate"],
    },
    admin: {
      inventory: ["view", "add", "edit", "adjust", "export"],
      purchaseOrders: ["view", "create", "edit", "approve", "receive"],
      salesOrders: ["view", "create", "edit", "approve", "invoice"],
      stockMovements: ["view", "export"],
      suppliers: ["view", "create", "edit"],
      reports: ["view", "export"],
      users: [],
    },
    staff: {
      inventory: ["view"],
      purchaseOrders: ["view", "create"],
      salesOrders: ["view", "create"],
      stockMovements: ["view"],
      suppliers: ["view"],
      reports: [],
      users: [],
    },
  },

  // ============================================
  // UTILITY: Get Current User
  // ============================================
  getCurrentUser() {
    const userJson = localStorage.getItem("janstro_user");
    if (!userJson) return null;

    try {
      const user = JSON.parse(userJson);
      if (!user || !user.role) return null;
      return user;
    } catch (err) {
      console.error("RBAC: Invalid user data", err);
      return null;
    }
  },

  // ============================================
  // CHECK PAGE ACCESS
  // ============================================
  hasPageAccess(pageName, userRole) {
    if (!pageName || !userRole) return false;

    const role = userRole.toLowerCase();
    const allowedPages = this.pagePermissions[role];

    if (!allowedPages) {
      console.warn(`RBAC: Unknown role "${role}"`);
      return false;
    }

    return allowedPages.includes(pageName);
  },

  // ============================================
  // CHECK ACTION ACCESS
  // ============================================
  can(role, module, action) {
    if (!role || !module || !action) return false;

    role = role.toLowerCase();

    const roleActions = this.actionPermissions[role];
    if (!roleActions) return false;

    const moduleActions = roleActions[module];
    if (!moduleActions) return false;

    return moduleActions.includes(action);
  },

  // ============================================
  // ENFORCE PAGE ACCESS (Block unauthorized access)
  // ============================================
  enforcePage() {
    const user = this.getCurrentUser();

    // Not logged in - redirect to login
    if (!user || !user.role) {
      console.warn("RBAC: No user found, redirecting to login");
      window.location.href = "index.html";
      return false;
    }

    // Get current page name
    const path = window.location.pathname.split("/").pop();
    const pageName = path.replace(".html", "");

    // Skip check for login page
    if (pageName === "index" || pageName === "") {
      return true;
    }

    // Check if user has access to this page
    if (!this.hasPageAccess(pageName, user.role)) {
      console.warn(
        `RBAC: User "${user.username}" (${user.role}) denied access to "${pageName}"`
      );
      alert(
        `Access Denied: You don't have permission to access this page.\n\nYour role: ${user.role.toUpperCase()}`
      );
      window.location.href = "dashboard.html";
      return false;
    }

    console.log(
      `✅ RBAC: User "${user.username}" (${user.role}) granted access to "${pageName}"`
    );
    return true;
  },

  // ============================================
  // FILTER SIDEBAR MENU BY ROLE
  // ============================================
  filterSidebar() {
    const user = this.getCurrentUser();
    if (!user || !user.role) return;

    const menuItems = document.querySelectorAll(".menu-item");

    menuItems.forEach((item) => {
      const href = item.getAttribute("href");
      if (!href) return;

      const pageName = href.replace(".html", "").split("/").pop();

      if (!this.hasPageAccess(pageName, user.role)) {
        item.style.display = "none";
      } else {
        item.style.display = "";
      }
    });
  },

  // ============================================
  // HIDE BUTTONS BASED ON ACTION PERMISSIONS
  // ============================================
  enforceActions(moduleName, actionMap) {
    const user = this.getCurrentUser();
    if (!user || !user.role) return;

    const role = user.role.toLowerCase();

    // actionMap example: { 'create': '#addBtn', 'edit': '.editBtn', 'delete': '.deleteBtn' }
    Object.keys(actionMap).forEach((action) => {
      const selector = actionMap[action];

      if (!this.can(role, moduleName, action)) {
        const elements = document.querySelectorAll(selector);
        elements.forEach((el) => {
          el.style.display = "none";
          el.disabled = true;
        });
      }
    });
  },

  // ============================================
  // SHOW ROLE BADGE IN UI
  // ============================================
  displayRoleBadge() {
    const user = this.getCurrentUser();
    if (!user) return;

    // Add role badge to user info if it doesn't exist
    const userInfo = document.querySelector(".user-info");
    if (userInfo && !document.getElementById("roleBadge")) {
      const badge = document.createElement("span");
      badge.id = "roleBadge";
      badge.className = "badge bg-primary ms-2";
      badge.textContent = user.role.toUpperCase();
      badge.style.fontSize = "11px";
      badge.style.padding = "6px 12px";
      badge.style.borderRadius = "20px";
      userInfo.insertBefore(badge, userInfo.firstChild);
    }
  },

  // ============================================
  // INITIALIZE RBAC ON EVERY PAGE
  // ============================================
  init() {
    console.log("🔒 RBAC System v5.0 Initializing...");

    // Enforce page access
    if (!this.enforcePage()) {
      return; // Stop if access denied
    }

    // Filter sidebar menu
    this.filterSidebar();

    // Display role badge
    this.displayRoleBadge();

    console.log("✅ RBAC System Initialized Successfully");
  },
};

// ============================================
// AUTO-INITIALIZE ON PAGE LOAD
// ============================================
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RBAC.init());
} else {
  RBAC.init();
}

// Export for use in other scripts
window.RBAC = RBAC;

console.log("✅ RBAC Module Loaded - Full Page & Action Enforcement Active");
