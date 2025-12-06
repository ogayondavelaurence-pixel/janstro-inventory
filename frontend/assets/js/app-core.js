/**
 * ============================================================================
 * JANSTRO IMS - APP CORE v3.2 COMPLETE (SIDEBAR & THEME FIX)
 * ============================================================================
 * Path: frontend/assets/js/app-core.js
 *
 * WHAT THIS FILE DOES:
 * - Initializes the entire application
 * - Manages user authentication and session
 * - Controls sidebar navigation (mobile + desktop)
 * - Handles theme switching (light/dark mode)
 * - Renders dynamic sidebar based on user role
 * - Manages global event listeners
 *
 * CHANGELOG v3.2:
 * ✅ CRITICAL: Fixed mobile sidebar toggle (no duplicate listeners)
 * ✅ Fixed dark mode inconsistencies (sidebar now dark in dark mode)
 * ✅ Improved theme persistence across page loads
 * ✅ Enhanced sidebar state management
 * ✅ Added proper cleanup for event listeners
 * ✅ Improved WCAG accessibility compliance
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
 * ============================================================================
 */

(function (window, document) {
  "use strict";

  const AppCore = {
    // ========================================================================
    // STATE MANAGEMENT
    // ========================================================================
    currentUser: null,
    currentTheme: "light",
    sidebar: null,
    overlay: null,
    sidebarInitialized: false,
    themeToggleButton: null,

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    async init() {
      console.log("🚀 Janstro IMS v3.2 Initializing...");

      await this.checkAuth();
      this.initTheme();
      this.initSidebar();
      this.attachGlobalListeners();

      console.log("✅ Application Ready");
    },

    // ========================================================================
    // AUTHENTICATION & ROUTE GUARD
    // ========================================================================
    async checkAuth() {
      const currentPage =
        window.location.pathname.split("/").pop() || "index.html";
      const publicPages = ["index.html", "inquiry.html", ""];

      console.log("🔐 Auth Check:", currentPage);

      // Public pages - redirect if already authenticated
      if (publicPages.includes(currentPage)) {
        if (API.isAuthenticated()) {
          console.log("✅ Already authenticated, redirecting to dashboard");
          window.location.href = "dashboard.html";
          return;
        }
        console.log("✅ Public page access granted");
        return;
      }

      // Protected pages - require authentication
      if (!API.isAuthenticated()) {
        console.warn("⚠️ Not authenticated, redirecting to login");
        window.location.href = "index.html";
        return;
      }

      // Validate token
      try {
        const response = await API.getCurrentUser();
        if (response.success && response.data) {
          this.currentUser = response.data;
          console.log("✅ User authenticated:", this.currentUser.username);
        } else {
          throw new Error("Invalid token");
        }
      } catch (error) {
        console.error("❌ Token validation failed:", error);
        API.clearToken();
        window.location.href = "index.html";
        return;
      }

      // Enforce RBAC
      if (window.RBAC && !RBAC.enforce()) {
        console.error("❌ RBAC enforcement failed");
        return;
      }

      console.log("✅ Auth check passed");
    },

    // ========================================================================
    // THEME SYSTEM (FIXED - DARK MODE CONSISTENCY)
    // ========================================================================
    initTheme() {
      // Load saved theme or detect system preference
      this.currentTheme = localStorage.getItem("janstro_theme") || "light";

      if (!localStorage.getItem("janstro_theme")) {
        if (
          window.matchMedia &&
          window.matchMedia("(prefers-color-scheme: dark)").matches
        ) {
          this.currentTheme = "dark";
        }
      }

      this.applyTheme(this.currentTheme);
      this.createThemeToggle();

      // Listen for system theme changes
      if (window.matchMedia) {
        window
          .matchMedia("(prefers-color-scheme: dark)")
          .addEventListener("change", (e) => {
            if (!localStorage.getItem("janstro_theme")) {
              this.applyTheme(e.matches ? "dark" : "light");
            }
          });
      }

      console.log(`🎨 Theme initialized: ${this.currentTheme}`);
    },

    /**
     * Apply theme to entire application (FIXED)
     */
    applyTheme(theme) {
      document.documentElement.setAttribute("data-theme", theme);
      this.currentTheme = theme;
      localStorage.setItem("janstro_theme", theme);

      // Update theme toggle icon
      this.updateThemeToggleIcon();

      // Update chart themes if present
      this.updateChartThemes();

      // CRITICAL FIX: Update sidebar gradient for dark mode
      this.updateSidebarTheme(theme);

      console.log(`✅ Theme applied: ${theme}`);
    },

    /**
     * Update sidebar colors based on theme (CRITICAL FIX)
     */
    updateSidebarTheme(theme) {
      const sidebar = document.querySelector(".sidebar");
      if (!sidebar) return;

      if (theme === "dark") {
        // Dark mode: Use dark gradient
        sidebar.style.background =
          "linear-gradient(180deg, #1e293b 0%, #0f172a 100%)";
      } else {
        // Light mode: Use original violet gradient
        sidebar.style.background =
          "linear-gradient(180deg, #667eea 0%, #764ba2 100%)";
      }
    },

    toggleTheme() {
      const newTheme = this.currentTheme === "light" ? "dark" : "light";
      this.applyTheme(newTheme);

      if (window.Accessibility) {
        window.Accessibility.announce(
          `Theme changed to ${newTheme} mode`,
          "polite"
        );
      }
    },

    updateThemeToggleIcon() {
      const toggle = this.themeToggleButton;
      if (toggle) {
        toggle.innerHTML =
          this.currentTheme === "light"
            ? '<i class="bi bi-moon-stars-fill"></i>'
            : '<i class="bi bi-sun-fill"></i>';
        toggle.setAttribute(
          "aria-label",
          this.currentTheme === "light"
            ? "Switch to dark mode"
            : "Switch to light mode"
        );
      }
    },

    createThemeToggle() {
      if (document.getElementById("themeToggle")) return;

      const button = document.createElement("button");
      button.id = "themeToggle";
      button.className = "theme-toggle";
      button.setAttribute("aria-label", "Toggle dark mode");
      button.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
      button.addEventListener("click", () => this.toggleTheme());

      document.body.appendChild(button);
      this.themeToggleButton = button;
    },

    updateChartThemes() {
      if (!window.Chart) return;

      const isDark = this.currentTheme === "dark";

      Chart.defaults.color = isDark ? "#94a3b8" : "#6b7280";
      Chart.defaults.borderColor = isDark ? "#334155" : "#e5e7eb";
      Chart.defaults.scale.grid.color = isDark ? "#334155" : "#e5e7eb";
      Chart.defaults.scale.ticks.color = isDark ? "#94a3b8" : "#6b7280";

      if (window.ChartSystem?.charts) {
        Object.values(window.ChartSystem.charts).forEach((chart) => {
          if (chart?.update) {
            chart.update("none");
          }
        });
      }
    },

    // ========================================================================
    // SIDEBAR MANAGEMENT (CRITICAL FIX)
    // ========================================================================
    initSidebar() {
      const container = document.getElementById("sidebarContainer");
      if (!container) {
        console.warn("⚠️ Sidebar container not found");
        return;
      }

      container.innerHTML = this.renderSidebar();

      this.sidebar = document.getElementById("mainSidebar");
      this.overlay = this.createOverlay();

      // Apply current theme to sidebar immediately
      this.updateSidebarTheme(this.currentTheme);

      this.attachSidebarListeners();
      this.sidebarInitialized = true;
      console.log("✅ Sidebar initialized");
    },

    renderSidebar() {
      if (!this.currentUser) return "";

      const user = this.currentUser;
      const currentPage = window.location.pathname.split("/").pop();

      const menu = this.getMenuConfig();
      let menuHTML = "";

      for (const sectionKey in menu) {
        const section = menu[sectionKey];
        const accessibleItems = section.items.filter((item) =>
          this.hasAccess(item)
        );

        if (accessibleItems.length) {
          menuHTML += `<div class="menu-section">${section.title}</div>`;
          accessibleItems.forEach((item) => {
            const isActive = currentPage === item.url ? "active" : "";
            menuHTML += `
              <a href="${item.url}" class="menu-item ${isActive}">
                <i class="bi bi-${item.icon}"></i>
                <span>${item.label}</span>
              </a>`;
          });
        }
      }

      return `
        <div class="sidebar" id="mainSidebar">
          <div class="sidebar-header">
            <i class="bi bi-sun"></i>
            <h4>Janstro Prime</h4>
            <small>Solar IMS</small>
            <div class="user-role-badge">
              <i class="bi bi-person-badge"></i> ${user.role.toUpperCase()}
            </div>
          </div>
          <div class="sidebar-menu">
            ${menuHTML}
          </div>
          <div class="sidebar-footer">
            <div class="user-info">
              <i class="bi bi-person-circle"></i>
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  ${user.name || user.username}
                </div>
                <small style="opacity:0.8;">${this.getRoleDescription(
                  user.role
                )}</small>
              </div>
            </div>
            <button class="btn btn-danger btn-sm w-100 mt-2" onclick="API.logout()">
              <i class="bi bi-box-arrow-right"></i> Logout
            </button>
          </div>
        </div>`;
    },

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
          title: "Inventory (MMBE)",
          items: [
            {
              id: "inventory",
              label: "Stock Overview",
              icon: "box",
              url: "inventory.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              id: "stock-movements",
              label: "Material Docs (MB51)",
              icon: "arrow-left-right",
              url: "stock-movements.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              id: "stock-requirements",
              label: "Requirements (MD04)",
              icon: "clipboard-data",
              url: "stock-requirements.html",
              roles: ["admin", "superadmin"],
            },
          ],
        },
        procurement: {
          title: "Procurement (MM)",
          items: [
            {
              id: "purchase-orders",
              label: "Purchase Orders (ME21N)",
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
          ],
        },
        sales: {
          title: "Sales (SD)",
          items: [
            {
              id: "inquiries",
              label: "Customer Inquiries",
              icon: "envelope",
              url: "inquiries.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              id: "customers",
              label: "Customer Master",
              icon: "person-lines-fill",
              url: "customers.html",
              roles: ["admin", "superadmin"],
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
              roles: ["admin", "superadmin"],
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
        admin: {
          title: "Administration",
          items: [
            {
              id: "users",
              label: "Users",
              icon: "people",
              url: "users.html",
              roles: ["superadmin"],
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
      };
    },

    hasAccess(item) {
      if (!this.currentUser?.role) return false;
      return item.roles.includes(this.currentUser.role.toLowerCase());
    },

    getRoleDescription(role) {
      const desc = {
        staff: "Operations Staff",
        admin: "Administrator",
        superadmin: "Super Administrator",
      };
      return desc[role.toLowerCase()] || role;
    },

    createOverlay() {
      let overlay = document.querySelector(".sidebar-overlay");
      if (!overlay) {
        overlay = document.createElement("div");
        overlay.className = "sidebar-overlay";
        document.body.appendChild(overlay);
      }
      return overlay;
    },

    // ========================================================================
    // SIDEBAR EVENT LISTENERS (CRITICAL FIX - NO DUPLICATES)
    // ========================================================================
    attachSidebarListeners() {
      // CRITICAL FIX: Clone button to remove ALL old listeners
      const toggleBtn = document.querySelector(".mobile-menu-toggle");
      if (toggleBtn) {
        const newBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);

        // Attach fresh listener
        newBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.toggleSidebar();
        });
        console.log("✅ Mobile menu button initialized");
      }

      // Overlay click to close
      if (this.overlay) {
        this.overlay.addEventListener("click", (e) => {
          e.stopPropagation();
          this.closeSidebar();
        });
      }

      // Menu items auto-close on mobile
      if (this.sidebar) {
        this.sidebar.querySelectorAll(".menu-item").forEach((item) => {
          item.addEventListener("click", () => {
            if (window.innerWidth <= 768) {
              setTimeout(() => this.closeSidebar(), 100);
            }
          });
        });
      }

      // Window resize handler
      window.addEventListener("resize", () => {
        if (window.innerWidth > 768 && this.isSidebarOpen()) {
          this.closeSidebar();
        }
      });

      // Escape key to close
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && this.isSidebarOpen()) {
          this.closeSidebar();
        }
      });
    },

    toggleSidebar() {
      if (this.isSidebarOpen()) {
        this.closeSidebar();
      } else {
        this.openSidebar();
      }
    },

    openSidebar() {
      if (!this.sidebar || !this.overlay) return;

      console.log("📂 Opening sidebar");
      this.sidebar.classList.add("mobile-show");
      this.overlay.classList.add("active");
      this.overlay.style.display = "block";
      document.body.style.overflow = "hidden";
    },

    closeSidebar() {
      if (!this.sidebar || !this.overlay) return;

      console.log("📁 Closing sidebar");
      this.sidebar.classList.remove("mobile-show");
      this.overlay.classList.remove("active");
      this.overlay.style.display = "none";
      document.body.style.overflow = "";
    },

    isSidebarOpen() {
      return this.sidebar?.classList.contains("mobile-show") || false;
    },

    // ========================================================================
    // GLOBAL EVENT LISTENERS
    // ========================================================================
    attachGlobalListeners() {
      // Form submission loading states
      document.addEventListener("submit", (e) => {
        const form = e.target;
        if (form.classList.contains("needs-loading")) {
          const submitBtn = form.querySelector('[type="submit"]');
          if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML =
              '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            setTimeout(() => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalText;
            }, 30000);
          }
        }
      });

      // Global error handler
      window.addEventListener("error", (event) => {
        console.error("Global error:", event.error);
      });

      // Unhandled promise rejection handler
      window.addEventListener("unhandledrejection", (event) => {
        console.error("Unhandled promise rejection:", event.reason);
      });
    },
  };

  // ========================================================================
  // AUTO-INITIALIZATION
  // ========================================================================
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => AppCore.init());
  } else {
    AppCore.init();
  }

  // ========================================================================
  // GLOBAL EXPORT
  // ========================================================================
  window.AppCore = AppCore;

  console.log("✅ App Core v3.2 Complete Loaded");
  console.log("🔧 Sidebar toggle fixed");
  console.log("🎨 Dark mode consistency fixed");
})(window, document);
