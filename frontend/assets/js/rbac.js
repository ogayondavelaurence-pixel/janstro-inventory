/**
 * Janstro Prime - RBAC (Role-Based Access Control)
 * Page-Level + Action-Level Permissions
 * Version: 4.0.0 - Production Grade
 */

const RBAC = {
  // -------------------------------
  // PAGE-LEVEL PERMISSIONS
  // -------------------------------
  rolePermissions: {
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

  // -------------------------------
  // ACTION-LEVEL PERMISSIONS
  // -------------------------------
  actionPermissions: {
    superadmin: {
      inventory: ["view", "add", "edit", "adjust", "disable", "export"],
      purchaseOrders: ["view", "create", "edit", "approve", "cancel"],
      salesOrders: ["view", "create", "edit", "approve", "cancel"],
      stockMovements: ["view", "adjust"],
      suppliers: ["view", "create", "edit"],
      reports: ["view", "export"],
      users: ["view", "create", "edit", "disable"],
    },

    admin: {
      inventory: ["view", "add", "edit", "adjust"],
      purchaseOrders: ["view", "create", "edit"],
      salesOrders: ["view", "create"],
      stockMovements: ["view"],
      suppliers: ["view", "create"],
      reports: ["view"],
      users: [],
    },

    staff: {
      inventory: ["view"],
      purchaseOrders: ["view", "create"], // drafts only
      salesOrders: ["view", "create"], // drafts only
      stockMovements: ["view"],
      suppliers: [],
      reports: [],
      users: [],
    },
  },

  // -------------------------------
  // UTILS: GET USER
  // -------------------------------
  getCurrentUser() {
    const raw = localStorage.getItem("janstro_user");
    if (!raw) return null;

    try {
      const data = JSON.parse(raw);
      if (!data || typeof data !== "object") return null;
      return data;
    } catch (err) {
      console.error("RBAC: Invalid user JSON", err);
      return null;
    }
  },

  // -------------------------------
  // PAGE ACCESS CHECKER
  // -------------------------------
  hasAccess(pageName, userRole) {
    if (!pageName || !userRole) return false;

    const role = userRole.toLowerCase();
    const allowed = this.rolePermissions[role];

    if (!allowed) return false;

    return allowed.includes(pageName);
  },

  // -------------------------------
  // ACTION ACCESS CHECKER
  // -------------------------------
  can(role, module, action) {
    if (!role || !module || !action) return false;

    role = role.toLowerCase();

    const roleModules = this.actionPermissions[role];
    if (!roleModules) return false;

    const allowedActions = roleModules[module] || [];
    return allowedActions.includes(action);
  },

  // -------------------------------
  // ENFORCE MENU PERMISSIONS
  // -------------------------------
  enforceMenuAccess() {
    const user = this.getCurrentUser();

    if (!user || !user.role) {
      window.location.href = "index.html";
      return;
    }

    const userRole = user.role.toLowerCase();
    const menuItems = document.querySelectorAll(".menu-item");

    menuItems.forEach((item) => {
      const href = item.getAttribute("href");
      if (!href) return;

      const pageName = href.replace(".html", "").split("/").pop();
      const canAccess = this.hasAccess(pageName, userRole);

      if (!canAccess) {
        item.style.display = "none";
      } else {
        item.style.display = "";
      }
    });
  },

  // -------------------------------
  // BLOCK DIRECT URL ACCESS
  // -------------------------------
  checkPageAccess() {
    const user = this.getCurrentUser();

    if (!user || !user.role) {
      window.location.href = "index.html";
      return false;
    }

    const role = user.role.toLowerCase();

    const path = window.location.pathname.split("/").pop();
    const pageName = path.replace(".html", "");

    const allowed = this.rolePermissions[role] || [];

    if (!allowed.includes(pageName)) {
      window.location.href = "dashboard.html";
      return false;
    }

    return true;
  },

  // -------------------------------
  // HIDE BUTTONS BASED ON ACTION RULES
  // (CALL THIS IN PAGES)
  // -------------------------------
  enforceActionAccess(moduleName, actionMap) {
    const user = this.getCurrentUser();
    if (!user || !user.role) return;

    const role = user.role.toLowerCase();

    Object.keys(actionMap).forEach((action) => {
      const selector = actionMap[action]; // example → "#approveBtn"

      if (!this.can(role, moduleName, action)) {
        const el = document.querySelector(selector);
        if (el) el.style.display = "none";
      }
    });
  },

  // -------------------------------
  // INIT
  // -------------------------------
  init() {
    this.enforceMenuAccess();
    this.checkPageAccess();
  },
};

// Auto-init
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RBAC.init());
} else {
  RBAC.init();
}

window.RBAC = RBAC;

console.log("RBAC System Loaded with Page + Action-Level Security");
