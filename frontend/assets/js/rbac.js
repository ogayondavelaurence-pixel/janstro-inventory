/**
 * Janstro Inventory System - RBAC (Role-Based Access Control)
 * Version: 3.0.0
 * Enforces strict role-based permissions across the entire application
 */

const RBAC = {
  /**
   * Role hierarchy (higher number = more permissions)
   */
  ROLE_HIERARCHY: {
    staff: 1,
    manager: 2,
    admin: 3,
    superadmin: 4,
  },

  /**
   * Page access permissions
   * Maps page names to minimum required role
   */
  PAGE_PERMISSIONS: {
    // Public (requires any authenticated user)
    "dashboard.html": "staff",
    "index.html": null, // Login page - no auth needed

    // Inventory Management
    "inventory.html": "staff",
    "stock-movements.html": "staff",

    // Procurement (Staff can view, Manager+ can create)
    "purchase-orders.html": "staff",
    "goods-receipt.html": "manager", // Only managers+ can receive goods
    "suppliers.html": "manager", // Only managers+ can manage suppliers

    // Sales
    "sales-orders.html": "staff",

    // Reports
    "reports.html": "staff",

    // Administration (Admin only)
    "users.html": "admin",
  },

  /**
   * Feature permissions
   * Controls what actions users can perform
   */
  FEATURE_PERMISSIONS: {
    // Inventory
    "inventory.view": ["staff", "manager", "admin", "superadmin"],
    "inventory.export": ["staff", "manager", "admin", "superadmin"],

    // Purchase Orders
    "po.view": ["staff", "manager", "admin", "superadmin"],
    "po.create": ["manager", "admin", "superadmin"],
    "po.receive": ["manager", "admin", "superadmin"],

    // Suppliers
    "suppliers.view": ["staff", "manager", "admin", "superadmin"],
    "suppliers.create": ["manager", "admin", "superadmin"],
    "suppliers.edit": ["manager", "admin", "superadmin"],

    // Sales Orders
    "sales.view": ["staff", "manager", "admin", "superadmin"],
    "sales.create": ["staff", "manager", "admin", "superadmin"],
    "sales.process": ["manager", "admin", "superadmin"],

    // Reports
    "reports.view": ["staff", "manager", "admin", "superadmin"],
    "reports.export": ["manager", "admin", "superadmin"],

    // Users (Admin only)
    "users.view": ["admin", "superadmin"],
    "users.create": ["admin", "superadmin"],
    "users.edit": ["admin", "superadmin"],
    "users.delete": ["superadmin"], // Only superadmin can delete
  },

  /**
   * Menu items configuration with required roles
   */
  MENU_CONFIG: [
    {
      section: "Main",
      items: [
        {
          id: "dashboard",
          href: "dashboard.html",
          icon: "bi-speedometer2",
          label: "Dashboard",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Inventory",
      items: [
        {
          id: "inventory",
          href: "inventory.html",
          icon: "bi-box",
          label: "All Items",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
        {
          id: "stock-movements",
          href: "stock-movements.html",
          icon: "bi-arrow-left-right",
          label: "Stock Movements",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Procurement",
      items: [
        {
          id: "purchase-orders",
          href: "purchase-orders.html",
          icon: "bi-cart-plus",
          label: "Purchase Orders",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
        {
          id: "goods-receipt",
          href: "goods-receipt.html",
          icon: "bi-box-arrow-in-down",
          label: "Goods Receipt",
          roles: ["manager", "admin", "superadmin"], // Manager+ only
        },
        {
          id: "suppliers",
          href: "suppliers.html",
          icon: "bi-truck",
          label: "Suppliers",
          roles: ["manager", "admin", "superadmin"], // Manager+ only
        },
      ],
    },
    {
      section: "Sales",
      items: [
        {
          id: "sales-orders",
          href: "sales-orders.html",
          icon: "bi-cart-check",
          label: "Sales Orders",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Reports",
      items: [
        {
          id: "reports",
          href: "reports.html",
          icon: "bi-graph-up",
          label: "Analytics",
          roles: ["staff", "manager", "admin", "superadmin"],
        },
      ],
    },
    {
      section: "Administration",
      items: [
        {
          id: "users",
          href: "users.html",
          icon: "bi-people",
          label: "Users",
          roles: ["admin", "superadmin"], // Admin only
        },
      ],
    },
  ],

  /**
   * Get current user from API
   */
  getCurrentUser() {
    if (typeof API !== "undefined" && API.getUser) {
      return API.getUser();
    }
    return null;
  },

  /**
   * Get current user's role level
   */
  getUserRoleLevel(role) {
    return this.ROLE_HIERARCHY[role] || 0;
  },

  /**
   * Check if user has minimum required role
   * @param {string} userRole - Current user's role
   * @param {string} requiredRole - Minimum required role
   */
  hasRole(userRole, requiredRole) {
    if (!userRole || !requiredRole) return false;
    return (
      this.getUserRoleLevel(userRole) >= this.getUserRoleLevel(requiredRole)
    );
  },

  /**
   * Check if user can access a page
   * @param {string} pageName - Name of the page (e.g., 'users.html')
   */
  canAccessPage(pageName) {
    const user = this.getCurrentUser();

    // No authentication required (login page)
    if (this.PAGE_PERMISSIONS[pageName] === null) {
      return true;
    }

    // Require authentication for all other pages
    if (!user || !user.role) {
      return false;
    }

    const requiredRole = this.PAGE_PERMISSIONS[pageName];
    if (!requiredRole) {
      console.warn(`No permission defined for page: ${pageName}`);
      return true; // Allow if not defined (fail open for undefined pages)
    }

    return this.hasRole(user.role, requiredRole);
  },

  /**
   * Check if user has permission for a feature
   * @param {string} feature - Feature identifier (e.g., 'po.create')
   */
  hasPermission(feature) {
    const user = this.getCurrentUser();
    if (!user || !user.role) return false;

    const allowedRoles = this.FEATURE_PERMISSIONS[feature];
    if (!allowedRoles) {
      console.warn(`No permission defined for feature: ${feature}`);
      return false; // Fail closed for undefined features
    }

    return allowedRoles.includes(user.role);
  },

  /**
   * Protect current page - redirect if unauthorized
   * Call this at the top of every protected page
   */
  protectPage() {
    const user = this.getCurrentUser();
    const currentPage = window.location.pathname.split("/").pop();

    // Not logged in - redirect to login
    if (!user) {
      console.warn("🚫 Not authenticated - redirecting to login");
      window.location.href = "index.html";
      return false;
    }

    // Check page access
    if (!this.canAccessPage(currentPage)) {
      console.warn(`🚫 Access denied to ${currentPage} for role: ${user.role}`);

      if (typeof Utils !== "undefined" && Utils.showToast) {
        Utils.showToast("Access denied. Insufficient permissions.", "error");
      } else {
        alert("Access denied. Insufficient permissions.");
      }

      // Redirect to dashboard
      setTimeout(() => {
        window.location.href = "dashboard.html";
      }, 1500);

      return false;
    }

    return true;
  },

  /**
   * Render role-based sidebar menu
   * Only shows menu items the user has access to
   */
  renderSidebar() {
    const user = this.getCurrentUser();
    if (!user) return;

    const sidebar = document.querySelector(".sidebar-menu");
    if (!sidebar) return;

    let html = "";

    this.MENU_CONFIG.forEach((section) => {
      // Filter items based on user role
      const accessibleItems = section.items.filter((item) =>
        item.roles.includes(user.role)
      );

      // Only show section if user has access to at least one item
      if (accessibleItems.length > 0) {
        html += `<div class="menu-section">${section.section}</div>`;

        accessibleItems.forEach((item) => {
          const currentPage = window.location.pathname.split("/").pop();
          const isActive = currentPage === item.href ? "active" : "";

          html += `
            <a href="${item.href}" class="menu-item ${isActive}">
              <i class="bi ${item.icon}"></i>
              <span>${item.label}</span>
            </a>
          `;
        });
      }
    });

    sidebar.innerHTML = html;
  },

  /**
   * Hide UI elements based on permissions
   * Add data-permission="feature.action" to any element
   */
  enforceUIPermissions() {
    const elements = document.querySelectorAll("[data-permission]");

    elements.forEach((el) => {
      const permission = el.getAttribute("data-permission");

      if (!this.hasPermission(permission)) {
        // Hide unauthorized elements
        el.style.display = "none";

        // Also disable if it's an input/button
        if (
          el.tagName === "BUTTON" ||
          el.tagName === "INPUT" ||
          el.tagName === "SELECT"
        ) {
          el.disabled = true;
        }
      }
    });
  },

  /**
   * Show role badge in UI
   */
  displayRoleBadge() {
    const user = this.getCurrentUser();
    if (!user) return;

    const roleElement = document.getElementById("userRole");
    if (roleElement) {
      const roleColors = {
        staff: "#6c757d",
        manager: "#ffc107",
        admin: "#667eea",
        superadmin: "#dc3545",
      };

      roleElement.textContent = user.role.toUpperCase();
      roleElement.style.color = roleColors[user.role] || "#333";
    }
  },

  /**
   * Initialize RBAC on page load
   * Call this in every page's DOMContentLoaded event
   */
  init() {
    // Protect the page first
    if (!this.protectPage()) {
      return; // Stop if unauthorized
    }

    // Render role-based sidebar
    this.renderSidebar();

    // Enforce UI permissions
    this.enforceUIPermissions();

    // Display role badge
    this.displayRoleBadge();

    console.log(
      "✅ RBAC initialized for user:",
      this.getCurrentUser()?.username
    );
  },

  /**
   * Utility: Check if current user is admin
   */
  isAdmin() {
    const user = this.getCurrentUser();
    return user && ["admin", "superadmin"].includes(user.role);
  },

  /**
   * Utility: Check if current user is manager or above
   */
  isManager() {
    const user = this.getCurrentUser();
    return user && ["manager", "admin", "superadmin"].includes(user.role);
  },

  /**
   * Utility: Get user-friendly permission message
   */
  getPermissionMessage(feature) {
    const requiredRoles = this.FEATURE_PERMISSIONS[feature];
    if (!requiredRoles || requiredRoles.length === 0) {
      return "This feature is restricted.";
    }

    const rolesText = requiredRoles
      .map((r) => r.charAt(0).toUpperCase() + r.slice(1))
      .join(", ");
    return `This feature requires: ${rolesText} role.`;
  },
};

// Make RBAC available globally
window.RBAC = RBAC;

// Auto-initialize on DOMContentLoaded if not on login page
document.addEventListener("DOMContentLoaded", () => {
  const currentPage = window.location.pathname.split("/").pop();

  // Skip RBAC init on login page
  if (currentPage !== "index.html") {
    RBAC.init();
  }
});
