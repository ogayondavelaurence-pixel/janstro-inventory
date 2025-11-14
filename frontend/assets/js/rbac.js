/**
 * Janstro Inventory System - Complete RBAC Implementation
 * Version: 3.0.0 (Production Ready)
 *
 * Features:
 * - Page-level access control
 * - Feature-level permissions
 * - Dynamic sidebar rendering
 * - Button/form visibility control
 * - Session management
 */
const RBAC = {
  /**
   * Role hierarchy (higher number = more permissions)
   */
  ROLES: {
    superadmin: 4,
    admin: 3,
    manager: 2,
    staff: 1,
  },
  /**
   * Page access requirements
   */
  PAGE_ACCESS: {
    "dashboard.html": ["staff", "manager", "admin", "superadmin"],
    "inventory.html": ["staff", "manager", "admin", "superadmin"],
    "purchase-orders.html": ["manager", "admin", "superadmin"],
    "sales-orders.html": ["staff", "manager", "admin", "superadmin"],
    "goods-receipt.html": ["manager", "admin", "superadmin"],
    "suppliers.html": ["manager", "admin", "superadmin"],
    "reports.html": ["manager", "admin", "superadmin"],
    "stock-movements.html": ["staff", "manager", "admin", "superadmin"],
    "users.html": ["admin", "superadmin"],
  },

  /**
   * Feature permissions
   */
  PERMISSIONS: {
    // Inventory
    "inventory.view": ["staff", "manager", "admin", "superadmin"],
    "inventory.create": ["manager", "admin", "superadmin"],
    "inventory.edit": ["manager", "admin", "superadmin"],
    "inventory.delete": ["superadmin"],
    "inventory.export": ["manager", "admin", "superadmin"],

    // Purchase Orders
    "po.view": ["staff", "manager", "admin", "superadmin"],
    "po.create": ["manager", "admin", "superadmin"],
    "po.approve": ["admin", "superadmin"],
    "po.receive": ["manager", "admin", "superadmin"],
    "po.cancel": ["admin", "superadmin"],

    // Sales Orders
    "sales.view": ["staff", "manager", "admin", "superadmin"],
    "sales.create": ["staff", "manager", "admin", "superadmin"],
    "sales.process": ["manager", "admin", "superadmin"],
    "sales.cancel": ["admin", "superadmin"],

    // Suppliers
    "suppliers.view": ["manager", "admin", "superadmin"],
    "suppliers.create": ["admin", "superadmin"],
    "suppliers.edit": ["admin", "superadmin"],
    "suppliers.delete": ["superadmin"],

    // Users
    "users.view": ["admin", "superadmin"],
    "users.create": ["superadmin"],
    "users.edit": ["admin", "superadmin"],
    "users.delete": ["superadmin"],
    "users.roles": ["superadmin"],

    // Reports
    "reports.view": ["manager", "admin", "superadmin"],
    "reports.export": ["manager", "admin", "superadmin"],
    "reports.financial": ["admin", "superadmin"],
  },

  /**
   * Menu structure
   */
  MENU_ITEMS: [
    {
      section: "Main",
      items: [
        {
          title: "Dashboard",
          icon: "bi-speedometer2",
          href: "dashboard.html",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
        {
          title: "Inventory",
          icon: "bi-box-seam",
          href: "inventory.html",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
        {
          title: "Stock Movements",
          icon: "bi-arrow-left-right",
          href: "stock-movements.html",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Operations",
      items: [
        {
          title: "Purchase Orders",
          icon: "biRetryJContinue-cart-plus",
          href: "purchase-orders.html",
          roles: ["manager", "admin", "superadmin"],
        },
        {
          title: "Goods Receipt",
          icon: "bi-box-arrow-in-down",
          href: "goods-receipt.html",
          roles: ["manager", "admin", "superadmin"],
        },
        {
          title: "Sales Orders",
          icon: "bi-cart-check",
          href: "sales-orders.html",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
        {
          title: "Suppliers",
          icon: "bi-truck",
          href: "suppliers.html",
          roles: ["manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Analytics",
      items: [
        {
          title: "Reports",
          icon: "bi-graph-up",
          href: "reports.html",
          roles: ["manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Administration",
      items: [
        {
          title: "User Management",
          icon: "bi-people",
          href: "users.html",
          roles: ["admin", "superadmin"],
        },
      ],
    },
  ],
  /**
   * Initialize RBAC system
   */
  init() {
    console.log("🔒 Initializing RBAC system...");

    // Check authentication
    if (!API.isAuthenticated()) {
      console.warn("⚠️ User not authenticated - redirecting to login");
      window.location.href = "index.html";
      return false;
    }

    const user = API.getUser();
    if (!user || !user.role) {
      console.error("❌ Invalid user session");
      API.logout();
      return false;
    }

    console.log("✅ User authenticated:", user.username, "- Role:", user.role);

    // Check page access
    const currentPage = this.getCurrentPage();
    if (!this.canAccessPage(currentPage)) {
      console.error("❌ Access denied to page:", currentPage);
      Utils.showToast(
        `Access denied. Required role: ${this.getRequiredRole(currentPage)}`,
        "error"
      );
      window.location.href = "dashboard.html";
      return false;
    }

    // Render sidebar
    this.renderSidebar();

    // Apply permission-based UI restrictions
    this.applyPermissions();

    console.log("✅ RBAC initialized successfully");
    return true;
  },

  /**
   * Get current page filename
   */
  getCurrentPage() {
    const path = window.location.pathname;
    return path.substring(path.lastIndexOf("/") + 1) || "index.html";
  },

  /**
   * Check if user can access current page
   */
  canAccessPage(page) {
    const user = API.getUser();
    if (!user) return false;

    const requiredRoles = this.PAGE_ACCESS[page];
    if (!requiredRoles) return true; // No restriction

    return requiredRoles.includes(user.role);
  },

  /**
   * Get required role for page
   */
  getRequiredRole(page) {
    const roles = this.PAGE_ACCESS[page];
    return roles ? roles.join(", ") : "Any";
  },

  /**
   * Check if user has permission
   */
  hasPermission(permission) {
    const user = API.getUser();
    if (!user) return false;

    const allowedRoles = this.PERMISSIONS[permission];
    if (!allowedRoles) {
      console.warn("⚠️ Permission not defined:", permission);
      return false;
    }

    return allowedRoles.includes(user.role);
  },

  /**
   * Get user role level
   */
  getUserRoleLevel(role) {
    return this.ROLES[role] || 0;
  },

  /**
   * Check if user is admin or superadmin
   */
  isAdmin() {
    const user = API.getUser();
    return user && ["admin", "superadmin"].includes(user.role);
  },

  /**
   * Check if user is manager or above
   */
  isManager() {
    const user = API.getUser();
    return user && ["manager", "admin", "superadmin"].includes(user.role);
  },

  /**
   * Get permission denied message
   */
  getPermissionMessage(permission) {
    const allowedRoles = this.PERMISSIONS[permission];
    if (!allowedRoles) return "Permission not defined";
    return `This action requires one of the following roles: ${allowedRoles.join(
      ", "
    )}`;
  },

  /**
   * Render sidebar menu
   */
  renderSidebar() {
    const user = API.getUser();
    if (!user) return;

    const sidebarMenu = document.getElementById("sidebarMenu");
    if (!sidebarMenu) {
      console.warn("⚠️ Sidebar menu element not found");
      return;
    }

    let html = "";

    this.MENU_ITEMS.forEach((section) => {
      // Filter items user can access
      const accessibleItems = section.items.filter((item) =>
        item.roles.includes(user.role)
      );

      if (accessibleItems.length === 0) return;

      // Add section header
      html += `<div class="menu-section">${section.section}</div>`;

      // Add menu items
      accessibleItems.forEach((item) => {
        const currentPage = this.getCurrentPage();
        const isActive = currentPage === item.href;

        html += `
                <a href="${item.href}" class="menu-item ${
          isActive ? "active" : ""
        }">
                    <i class="bi ${item.icon}"></i>
                    <span>${item.title}</span>
                </a>
            `;
      });
    });

    sidebarMenu.innerHTML = html;
    console.log("✅ Sidebar rendered with role-based menu");
  },

  /**
   * Apply permissions to UI elements
   */
  applyPermissions() {
    // Hide elements with data-permission attribute
    document.querySelectorAll("[data-permission]").forEach((element) => {
      const permission = element.getAttribute("data-permission");

      if (!this.hasPermission(permission)) {
        // Hide element
        element.style.display = "none";

        // Disable if it's a button/input
        if (element.tagName === "BUTTON" || element.tagName === "INPUT") {
          element.disabled = true;
        }

        console.log(
          `🔒 Hidden element due to missing permission: ${permission}`
        );
      }
    });

    // Hide elements with data-role attribute
    document.querySelectorAll("[data-role]").forEach((element) => {
      const requiredRoles = element
        .getAttribute("data-role")
        .split(",")
        .map((r) => r.trim());
      const user = API.getUser();

      if (!user || !requiredRoles.includes(user.role)) {
        element.style.display = "none";

        if (element.tagName === "BUTTON" || element.tagName === "INPUT") {
          element.disabled = true;
        }

        console.log(
          `🔒 Hidden element due to insufficient role: ${requiredRoles.join(
            ", "
          )}`
        );
      }
    });

    console.log("✅ Permission-based UI restrictions applied");
  },

  /**
   * Show access denied modal
   */
  showAccessDenied(message) {
    Utils.showToast(message || "Access denied", "error", 5000);
  },
};
// Auto-initialize RBAC on page load
document.addEventListener("DOMContentLoaded", function () {
  // Skip RBAC for login page
  const currentPage = window.location.pathname.split("/").pop();
  if (currentPage === "index.html" || currentPage === "") {
    return;
  }
  // Initialize RBAC
  RBAC.init();
});
// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = RBAC;
}
