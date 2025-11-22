/**
 * JANSTRO IMS - Dynamic Sidebar v3.0
 * Generates navigation based on user role
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
            id: "inventory",
            label: "Stock Overview (MMBE)",
            icon: "box",
            url: "inventory.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "stock-movements",
            label: "Material Documents (MB51)",
            icon: "arrow-left-right",
            url: "stock-movements.html",
            roles: ["staff", "admin", "superadmin"],
          },
        ],
      },

      procurement: {
        title: "Procurement (SAP: MM)",
        items: [
          {
            id: "purchase-orders",
            label: "Purchase Orders (ME21N)",
            icon: "cart-plus",
            url: "purchase-orders.html",
            roles: ["admin", "superadmin"],
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
        ],
      },

      sales: {
        title: "Sales (SAP: SD)",
        items: [
          {
            id: "customers",
            label: "Customer Master",
            icon: "person-lines-fill",
            url: "customers.html",
            roles: ["staff", "admin", "superadmin"],
          },
          {
            id: "sales-orders",
            label: "Sales Orders (VA01)",
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
        ],
      },

      reports: {
        title: "Reports",
        items: [
          {
            id: "reports",
            label: "Analytics",
            icon: "graph-up",
            url: "reports.html",
            roles: ["admin", "superadmin"],
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
        ],
      },
    };
  }

  hasAccess(item) {
    if (!this.user || !this.user.role) return false;
    return item.roles.includes(this.user.role.toLowerCase());
  }

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

    Object.keys(this.menuConfig).forEach((sectionKey) => {
      const section = this.menuConfig[sectionKey];
      const accessibleItems = section.items.filter((item) =>
        this.hasAccess(item)
      );

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
          <button class="btn btn-danger btn-sm w-100 mt-2" onclick="API.logout()">
            <i class="bi bi-box-arrow-right"></i> Logout
          </button>
        </div>
      </div>
    `;

    return html;
  }

  init(containerId = "sidebarContainer") {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = this.render();
    }
  }
}

window.JanstroSidebar = JanstroSidebar;

document.addEventListener("DOMContentLoaded", () => {
  const sidebar = new JanstroSidebar();
  sidebar.init();
});
