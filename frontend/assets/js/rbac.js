/**
 * JANSTRO IMS - Complete RBAC System v8.0
 * Role-Based Access Control aligned with system design
 * Roles: staff, admin, superadmin
 */

const RBAC = {
  // ============================================
  // ROLE HIERARCHY
  // ============================================
  roleHierarchy: {
    superadmin: 3, // Full system access
    admin: 2, // Operations + procurement management
    staff: 1, // Daily operations (SO, inquiry, goods receipt)
  },

  // ============================================
  // PAGE-LEVEL PERMISSIONS (Based on DFD & Flowcharts)
  // ============================================
  pagePermissions: {
    superadmin: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
      "goods-receipt",
      "suppliers",
      "customers",
      "sales-orders",
      "invoices",
      "reports",
      "users",
      "settings",
    ],
    admin: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
      "goods-receipt",
      "suppliers",
      "customers",
      "sales-orders",
      "invoices",
      "reports",
    ],
    staff: [
      "dashboard",
      "inventory",
      "stock-movements",
      "goods-receipt",
      "sales-orders",
      "customers",
    ],
  },

  // ============================================
  // ACTION-LEVEL PERMISSIONS
  // ============================================
  actionPermissions: {
    superadmin: {
      inventory: ["view", "add", "edit", "adjust", "delete"],
      purchaseOrders: ["view", "create", "approve", "receive"],
      salesOrders: ["view", "create", "invoice"],
      suppliers: ["view", "create", "edit", "delete"],
      customers: ["view", "create", "edit"],
      users: ["view", "create", "edit", "delete"],
      reports: ["view", "export"],
    },
    admin: {
      inventory: ["view", "add", "edit", "adjust"],
      purchaseOrders: ["view", "create", "approve", "receive"],
      salesOrders: ["view", "create", "invoice"],
      suppliers: ["view", "create", "edit"],
      customers: ["view", "create", "edit"],
      users: [],
      reports: ["view", "export"],
    },
    staff: {
      inventory: ["view"],
      purchaseOrders: [],
      salesOrders: ["view", "create"],
      suppliers: ["view"],
      customers: ["view", "create"],
      users: [],
      reports: ["view"],
    },
  },

  // ============================================
  // GET CURRENT USER
  // ============================================
  getCurrentUser() {
    const userJson = localStorage.getItem("janstro_user");
    if (!userJson) return null;

    try {
      const user = JSON.parse(userJson);
      if (!user || !user.role) return null;

      user.role = (user.role || user.role_name || "").toLowerCase();
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
  // ENFORCE PAGE ACCESS
  // ============================================
  enforcePage() {
    const user = this.getCurrentUser();

    if (!user || !user.role) {
      console.warn("RBAC: No user found, redirecting to login");
      window.location.href = "index.html";
      return false;
    }

    const path = window.location.pathname.split("/").pop();
    const pageName = path.replace(".html", "");

    if (pageName === "index" || pageName === "") {
      return true;
    }

    if (!this.hasPageAccess(pageName, user.role)) {
      console.warn(
        `RBAC: User "${user.username}" (${user.role}) denied access to "${pageName}"`
      );
      alert(
        `Access Denied\n\nYou don't have permission to access this page.\n\nYour role: ${user.role.toUpperCase()}`
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
  // FILTER SIDEBAR MENU
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
  // ENFORCE ACTIONS (HIDE BUTTONS)
  // ============================================
  enforceActions(moduleName, actionMap) {
    const user = this.getCurrentUser();
    if (!user || !user.role) return;

    const role = user.role.toLowerCase();

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
  // SHOW ROLE BADGE
  // ============================================
  displayRoleBadge() {
    const user = this.getCurrentUser();
    if (!user) return;

    const userInfo = document.querySelector(".user-info");
    if (userInfo && !document.getElementById("roleBadge")) {
      const badge = document.createElement("span");
      badge.id = "roleBadge";
      badge.className = "badge bg-primary ms-2";
      badge.textContent = user.role.toUpperCase();
      badge.style.fontSize = "11px";
      badge.style.padding = "6px 12px";
      badge.style.borderRadius = "20px";

      if (user.role === "superadmin") {
        badge.style.background = "#dc3545";
      } else if (user.role === "admin") {
        badge.style.background = "#667eea";
      } else {
        badge.style.background = "#6c757d";
      }

      userInfo.insertBefore(badge, userInfo.firstChild);
    }
  },

  // ============================================
  // MOBILE MENU TOGGLE
  // ============================================
  initHamburgerMenu() {
    if (!document.querySelector(".mobile-menu-toggle")) {
      const toggleBtn = document.createElement("button");
      toggleBtn.className = "mobile-menu-toggle";
      toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
      toggleBtn.setAttribute("aria-label", "Toggle menu");
      document.body.prepend(toggleBtn);

      const style = document.createElement("style");
      style.textContent = `
        .mobile-menu-toggle {
          display: none;
          position: fixed;
          top: 20px;
          left: 20px;
          z-index: 1001;
          background: linear-gradient(135deg, #667eea, #764ba2);
          color: white;
          border: none;
          padding: 10px 15px;
          border-radius: 8px;
          cursor: pointer;
          box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .sidebar.mobile-hidden {
          transform: translateX(-100%);
        }

        .sidebar.mobile-show {
          transform: translateX(0);
        }

        @media (max-width: 768px) {
          .mobile-menu-toggle {
            display: block;
          }
          
          .sidebar {
            transition: transform 0.3s ease;
          }
          
          .main-content {
            margin-left: 0;
          }
        }
      `;
      document.head.appendChild(style);
    }

    const toggleBtn = document.querySelector(".mobile-menu-toggle");
    const sidebar = document.querySelector(".sidebar");

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-show");
        sidebar.classList.toggle("mobile-hidden");
      });

      document.addEventListener("click", (e) => {
        if (window.innerWidth <= 768) {
          if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.add("mobile-hidden");
            sidebar.classList.remove("mobile-show");
          }
        }
      });
    }
  },

  // ============================================
  // INITIALIZE RBAC
  // ============================================
  init() {
    console.log("🔒 RBAC System v8.0 Initializing...");

    if (!this.enforcePage()) {
      return;
    }

    this.filterSidebar();
    this.displayRoleBadge();
    this.initHamburgerMenu();

    console.log("✅ RBAC System v8.0 Initialized Successfully");
  },
};

// ============================================
// AUTO-INITIALIZE
// ============================================
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RBAC.init());
} else {
  RBAC.init();
}

window.RBAC = RBAC;

console.log("✅ RBAC Module v8.0 Loaded - 3 Roles: staff, admin, superadmin");
