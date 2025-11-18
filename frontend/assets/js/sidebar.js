/**
 * Janstro Prime - RBAC Sidebar System
 * Dynamically filters menu based on user role
 * Version: 3.0.0
 */

class JanstroSidebar {
  constructor() {
    this.user = this.getUser();
    this.menuConfig = this.getMenuConfig();
  }

  getUser() {
    const userData = localStorage.getItem("janstro_user");
    return userData ? JSON.parse(userData) : null;
  }

  /**
   * Complete menu structure with role-based access control
   * Roles: staff, admin, superadmin
   */
  getMenuConfig() {
    return {
      main: {
        title: "Main",
        items: [
          {
            id: "dashboard",
            label: "Dashboard",
            icon: "speedometer2",
            url: "dashboard.html",
            roles: ["staff", "admin", "superadmin"],
          },
        ],
      },

      inventory: {
        title: "Inventory (SAP: MMBE)",
        items: [
          {
            id: "all-items",
            label: "All Items",
            icon: "box",
            url: "inventory.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "stock-movements",
            label: "Stock Movements (MB51)",
            icon: "arrow-left-right",
            url: "stock-movements.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "material-master",
            label: "Material Master Data",
            icon: "clipboard-data",
            url: "material-master.html",
            roles: ["admin", "superadmin"],
          },
          {
            id: "bom",
            label: "Bill of Materials",
            icon: "diagram-3",
            url: "bom.html",
            roles: ["admin", "superadmin"],
          },
        ],
      },

      procurement: {
        title: "Procurement (SAP: ME21N)",
        items: [
          {
            id: "purchase-orders",
            label: "Purchase Orders",
            icon: "cart-plus",
            url: "purchase-orders.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "goods-receipt",
            label: "Goods Receipt (MIGO)",
            icon: "box-arrow-in-down",
            url: "goods-receipt.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "suppliers",
            label: "Suppliers",
            icon: "truck",
            url: "suppliers.html",
            roles: ["admin", "superadmin"],
          },
          {
            id: "supplier-master",
            label: "Supplier Master Data",
            icon: "building",
            url: "supplier-master.html",
            roles: ["admin", "superadmin"],
          },
        ],
      },

      sales: {
        title: "Sales (SAP: VA01)",
        items: [
          {
            id: "sales-orders",
            label: "Sales Orders",
            icon: "cart-check",
            url: "sales-orders.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "invoices",
            label: "Invoices (VF01)",
            icon: "receipt",
            url: "invoices.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "customer-master",
            label: "Customer Master Data",
            icon: "person-lines-fill",
            url: "customer-master.html",
            roles: ["staff", "admin", "superadmin"],
          },
        ],
      },

      reports: {
        title: "Reports & Analytics",
        items: [
          {
            id: "analytics",
            label: "Analytics Dashboard",
            icon: "graph-up",
            url: "reports.html",
            roles: ["admin", "superadmin"],
          },
          {
            id: "inventory-reports",
            label: "Inventory Reports",
            icon: "file-earmark-bar-graph",
            url: "inventory-reports.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "audit-logs",
            label: "Audit Logs",
            icon: "file-text",
            url: "audit-logs.html",
            roles: ["superadmin"],
          },
        ],
      },

      administration: {
        title: "Administration",
        items: [
          {
            id: "users",
            label: "User Management",
            icon: "people",
            url: "users.html",
            roles: ["admin", "superadmin"],
          },
          {
            id: "roles",
            label: "Roles & Permissions",
            icon: "shield-check",
            url: "roles.html",
            roles: ["superadmin"],
          },
          {
            id: "system-settings",
            label: "System Settings",
            icon: "gear",
            url: "settings.html",
            roles: ["superadmin"],
          },
          {
            id: "backup",
            label: "Backup & Recovery",
            icon: "cloud-arrow-up",
            url: "backup.html",
            roles: ["superadmin"],
          },
        ],
      },
    };
  }

  /**
   * Check if user has access to menu item
   */
  hasAccess(item) {
    if (!this.user || !this.user.role) return false;
    return item.roles.includes(this.user.role.toLowerCase());
  }

  /**
   * Generate sidebar HTML with role-based filtering
   */
  render() {
    if (!this.user) {
      window.location.href = "index.html";
      return "";
    }

    let html = `
      <div class="sidebar">
        <div class="sidebar-header">
          <i class="bi bi-sun"></i>
          <h4>Janstro Prime</h4>
          <small>Solar IMS v3.0</small>
          <div class="user-role-badge">
            <i class="bi bi-person-badge"></i>
            ${this.user.role.toUpperCase()}
          </div>
        </div>
        <div class="sidebar-menu">
    `;

    // Iterate through menu sections
    Object.keys(this.menuConfig).forEach((sectionKey) => {
      const section = this.menuConfig[sectionKey];

      // Filter items by role
      const accessibleItems = section.items.filter((item) =>
        this.hasAccess(item)
      );

      // Only show section if user has access to at least one item
      if (accessibleItems.length > 0) {
        html += `<div class="menu-section">${section.title}</div>`;

        accessibleItems.forEach((item) => {
          const currentPage = window.location.pathname.split("/").pop();
          const isActive = currentPage === item.url ? "active" : "";

          html += `
            <a href="${item.url}" class="menu-item ${isActive}">
              <i class="bi bi-${item.icon}"></i>
              <span>${item.label}</span>
            </a>
          `;
        });
      }
    });

    html += `
        </div>
        <div class="sidebar-footer">
          <div class="user-info">
            <i class="bi bi-person-circle"></i>
            <span>${this.user.name || this.user.username}</span>
          </div>
        </div>
      </div>
    `;

    return html;
  }

  /**
   * Initialize sidebar and inject into DOM
   */
  init(containerId = "sidebarContainer") {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = this.render();
      this.addStyles();
    } else {
      console.error(`Container #${containerId} not found`);
    }
  }

  /**
   * Add required CSS styles
   */
  addStyles() {
    if (document.getElementById("janstro-sidebar-styles")) return;

    const style = document.createElement("style");
    style.id = "janstro-sidebar-styles";
    style.textContent = `
      .user-role-badge {
        margin-top: 15px;
        padding: 6px 12px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
      }

      .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        background: rgba(0, 0, 0, 0.1);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
      }

      .sidebar-footer .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        font-size: 14px;
        font-weight: 600;
      }

      .sidebar-footer .user-info i {
        font-size: 24px;
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Get list of accessible routes for current user
   */
  getAccessibleRoutes() {
    const routes = [];
    Object.keys(this.menuConfig).forEach((sectionKey) => {
      const section = this.menuConfig[sectionKey];
      section.items.forEach((item) => {
        if (this.hasAccess(item)) {
          routes.push(item.url);
        }
      });
    });
    return routes;
  }

  /**
   * Check if current user can access a specific route
   */
  canAccessRoute(url) {
    const accessibleRoutes = this.getAccessibleRoutes();
    return accessibleRoutes.includes(url);
  }
}

// Export for use in HTML pages
window.JanstroSidebar = JanstroSidebar;

// Auto-initialize on DOM load
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = new JanstroSidebar();
  sidebar.init();
});
