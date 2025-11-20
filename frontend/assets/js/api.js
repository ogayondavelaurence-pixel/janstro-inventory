/**
 * RBAC v7.0 - FIXED INITIALIZATION
 * Waits for API to be ready before executing
 */

const RBAC = {
  roleHierarchy: {
    superadmin: 4,
    admin: 3,
    manager: 2,
    staff: 1,
  },

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
    manager: [
      "dashboard",
      "inventory",
      "stock-movements",
      "purchase-orders",
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
      users: ["view", "create", "edit", "delete", "deactivate"],
    },
    admin: {
      inventory: ["view", "add", "edit", "adjust", "export"],
      purchaseOrders: ["view", "create", "edit", "approve", "receive"],
      salesOrders: ["view", "create", "edit", "approve", "invoice"],
      users: [],
    },
    manager: {
      inventory: ["view", "export"],
      purchaseOrders: ["view", "create"],
      salesOrders: ["view", "create"],
      users: [],
    },
    staff: {
      inventory: ["view"],
      purchaseOrders: ["view", "create"],
      salesOrders: ["view", "create"],
      users: [],
    },
  },

  // Wait for API to be ready
  waitForAPI() {
    return new Promise((resolve) => {
      if (typeof window.API !== "undefined" && window.API.getUser) {
        resolve();
      } else {
        const interval = setInterval(() => {
          if (typeof window.API !== "undefined" && window.API.getUser) {
            clearInterval(interval);
            resolve();
          }
        }, 50);

        // Timeout after 5 seconds
        setTimeout(() => {
          clearInterval(interval);
          console.error("❌ RBAC: API not available after 5 seconds");
          resolve();
        }, 5000);
      }
    });
  },

  getCurrentUser() {
    if (typeof window.API === "undefined") {
      console.error("❌ RBAC: API not loaded");
      return null;
    }
    return window.API.getUser();
  },

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

  can(role, module, action) {
    if (!role || !module || !action) return false;

    role = role.toLowerCase();
    const roleActions = this.actionPermissions[role];
    if (!roleActions) return false;

    const moduleActions = roleActions[module];
    if (!moduleActions) return false;

    return moduleActions.includes(action);
  },

  async enforcePage() {
    // Wait for API to be ready
    await this.waitForAPI();

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
      userInfo.insertBefore(badge, userInfo.firstChild);
    }
  },

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

  async init() {
    console.log("🔒 RBAC System v7.0 Initializing...");

    // Wait for API
    await this.waitForAPI();

    const hasAccess = await this.enforcePage();
    if (!hasAccess) return;

    this.filterSidebar();
    this.displayRoleBadge();
    this.initHamburgerMenu();

    console.log("✅ RBAC System Initialized Successfully");
  },
};

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RBAC.init());
} else {
  RBAC.init();
}

window.RBAC = RBAC;

console.log("✅ RBAC Module v7.0 Loaded");
