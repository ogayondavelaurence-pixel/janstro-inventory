/**
 * ============================================================================
 * JANSTRO IMS - APP CORE v7.1 FINAL (SIDEBAR LOGO REMOVED)
 * ============================================================================
 * Path: frontend/assets/js/app-core.js
 *
 * FINAL FIXES APPLIED:
 * ✅ Settings merged into profile dropdown (ONE dropdown only)
 * ✅ Sidebar logo completely removed (clean menu only)
 * ✅ Logo only in navbar (clickable to dashboard)
 * ✅ Perfect mobile + desktop responsiveness
 * ============================================================================
 */

(function (window, document) {
  "use strict";

  const AppCore = {
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
      console.log("🚀 Janstro IMS v7.1 FINAL Initializing...");

      await this.checkAuth();

      if (this.currentUser) {
        this.initTheme();
        this.initNavbar();
        this.initSidebar();
        this.attachGlobalListeners();
        console.log(
          `✅ Application Ready - User: ${this.currentUser.username} (${this.currentUser.role})`
        );
      }
    },

    // ========================================================================
    // AUTHENTICATION CHECK & USER LOADING
    // ========================================================================
    async checkAuth() {
      const currentPage =
        window.location.pathname.split("/").pop() || "index.html";
      const publicPages = ["index.html", "inquiry.html", ""];

      if (publicPages.includes(currentPage)) {
        if (API.isAuthenticated()) {
          window.location.href = "dashboard.html";
          return;
        }
        return;
      }

      if (!API.isAuthenticated()) {
        window.location.href = "index.html";
        return;
      }

      try {
        const response = await API.getCurrentUser();

        if (response.success && response.data) {
          this.currentUser = response.data;

          this.currentUser.role = (
            this.currentUser.role ||
            this.currentUser.role_name ||
            ""
          ).toLowerCase();

          console.log("✅ User authenticated:", {
            username: this.currentUser.username,
            role: this.currentUser.role,
            user_id: this.currentUser.user_id,
            has_profile_picture: !!this.currentUser.profile_picture,
          });

          if (
            !this.currentUser.role ||
            !["staff", "admin", "superadmin"].includes(this.currentUser.role)
          ) {
            throw new Error("Invalid user role");
          }
        } else {
          throw new Error("Invalid token");
        }
      } catch (error) {
        console.error("❌ Token validation failed:", error);
        API.clearToken();
        window.location.href = "index.html";
        return;
      }

      if (window.RBAC && !RBAC.enforce()) {
        return;
      }
    },

    // ========================================================================
    // ✅ NAVBAR (MERGED DROPDOWN - ONE DROPDOWN ONLY)
    // ========================================================================
    initNavbar() {
      if (!this.currentUser) return;

      // Profile avatar HTML
      let profileAvatarHTML = "";
      if (this.currentUser.profile_picture) {
        profileAvatarHTML = `
          <img src="${this.currentUser.profile_picture}" 
               alt="${this.currentUser.name || this.currentUser.username}" 
               style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff;">
        `;
      } else {
        const initials = this.getInitials(
          this.currentUser.name || this.currentUser.username
        );
        profileAvatarHTML = `
          <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); 
                      display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px;">
            ${initials}
          </div>
        `;
      }

      // ✅ Build merged dropdown menu
      const dropdownMenuHTML = this.buildMergedDropdownMenu();

      const navbarHtml = `
        <nav class="navbar">
          <!-- ✅ CLICKABLE LOGO - Goes to Dashboard -->
          <a href="dashboard.html" class="navbar-brand" style="text-decoration: none;">
            <div class="navbar-logo">☀️</div>
            <div class="navbar-title">
              <strong>Janstro Prime</strong>
              <small>Solar IMS</small>
            </div>
          </a>

          <div class="navbar-right">
            <!-- ✅ SINGLE MERGED DROPDOWN (Profile + Settings) -->
            <div class="dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" 
                 data-bs-toggle="dropdown" aria-expanded="false">
                <div class="profile-avatar" id="navbarProfileAvatar">
                  ${profileAvatarHTML}
                </div>
                <div class="profile-info">
                  <div class="profile-name">${
                    this.currentUser.name || this.currentUser.username
                  }</div>
                  <div class="profile-role">${this.getRoleLabel(
                    this.currentUser.role
                  )}</div>
                </div>
                <i class="bi bi-chevron-down"></i>
              </a>

              <!-- ✅ MERGED DROPDOWN MENU -->
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                ${dropdownMenuHTML}
              </ul>
            </div>
          </div>
        </nav>
      `;

      // Remove existing navbar if present
      const existingNavbar = document.querySelector(".navbar");
      if (existingNavbar) {
        existingNavbar.remove();
      }

      document.body.insertAdjacentHTML("afterbegin", navbarHtml);
    },

    // ========================================================================
    // ✅ BUILD MERGED DROPDOWN MENU (Settings + Profile in ONE dropdown)
    // ========================================================================
    buildMergedDropdownMenu() {
      const role = this.currentUser.role.toLowerCase();
      let html = "";

      // ✅ SECTION 1: System Settings (Superadmin Only)
      if (role === "superadmin") {
        html +=
          '<li class="dropdown-header"><i class="bi bi-gear"></i> SYSTEM SETTINGS</li>';
        html += `
          <li>
            <a class="dropdown-item" href="email-settings.html">
              <i class="bi bi-envelope-gear"></i>
              Email Settings
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="users.html">
              <i class="bi bi-people"></i>
              User Management
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="audit-logs.html">
              <i class="bi bi-file-text"></i>
              Audit Logs
            </a>
          </li>
        `;
        html += '<li><hr class="dropdown-divider"></li>';
      }

      // ✅ SECTION 2: My Account (All Users)
      html +=
        '<li class="dropdown-header"><i class="bi bi-person-circle"></i> MY ACCOUNT</li>';
      html += `
        <li>
          <a class="dropdown-item" href="profile-settings.html">
            <i class="bi bi-person-gear"></i>
            Profile Settings
          </a>
        </li>
      `;

      // ✅ SECTION 3: Logout (All Users)
      html += '<li><hr class="dropdown-divider"></li>';
      html += `
        <li>
          <a class="dropdown-item" href="#" onclick="API.logout(); return false;">
            <i class="bi bi-box-arrow-right"></i>
            Logout
          </a>
        </li>
      `;

      return html;
    },

    // ========================================================================
    // REFRESH NAVBAR PROFILE PICTURE
    // ========================================================================
    refreshNavbarProfilePicture(newProfilePicture) {
      console.log("🔄 Refreshing navbar profile picture...");

      const avatarContainer = document.getElementById("navbarProfileAvatar");
      if (!avatarContainer) {
        console.warn("⚠️ Navbar avatar container not found");
        return;
      }

      this.currentUser.profile_picture = newProfilePicture;

      let profileAvatarHTML = "";
      if (newProfilePicture) {
        profileAvatarHTML = `
          <img src="${newProfilePicture}" 
               alt="${this.currentUser.name || this.currentUser.username}" 
               style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff;">
        `;
      } else {
        const initials = this.getInitials(
          this.currentUser.name || this.currentUser.username
        );
        profileAvatarHTML = `
          <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); 
                      display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px;">
            ${initials}
          </div>
        `;
      }

      avatarContainer.innerHTML = profileAvatarHTML;
      console.log("✅ Navbar profile picture refreshed");
    },

    getInitials(name) {
      if (!name) return "?";
      const parts = name.trim().split(" ");
      if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
      return (
        parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
      ).toUpperCase();
    },

    getRoleLabel(role) {
      const labels = {
        staff: "Operations Staff",
        admin: "Administrator",
        superadmin: "Super Administrator",
      };
      return labels[role?.toLowerCase()] || role;
    },

    // ========================================================================
    // THEME SYSTEM
    // ========================================================================
    initTheme() {
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

      if (window.matchMedia) {
        window
          .matchMedia("(prefers-color-scheme: dark)")
          .addEventListener("change", (e) => {
            if (!localStorage.getItem("janstro_theme")) {
              this.applyTheme(e.matches ? "dark" : "light");
            }
          });
      }
    },

    applyTheme(theme) {
      document.documentElement.setAttribute("data-theme", theme);
      this.currentTheme = theme;
      localStorage.setItem("janstro_theme", theme);
      this.updateThemeToggleIcon();
      this.updateChartThemes();
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
    // ✅ SIDEBAR (CLEAN - NO LOGO, MENU ONLY)
    // ========================================================================
    initSidebar() {
      const container = document.getElementById("sidebarContainer");
      if (!container) return;

      container.innerHTML = this.renderSidebar();
      this.sidebar = document.getElementById("mainSidebar");
      this.overlay = this.createOverlay();
      this.attachSidebarListeners();
      this.sidebarInitialized = true;
    },

    renderSidebar() {
      if (!this.currentUser) return "";

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

      // ✅ CRITICAL FIX: CLEAN SIDEBAR - NO LOGO SECTION
      return `
        <div class="sidebar" id="mainSidebar">
          <div class="sidebar-menu">
            ${menuHTML}
          </div>
        </div>`;
    },

    getMenuConfig() {
      return {
        main: {
          title: "Main",
          items: [
            {
              label: "Dashboard",
              icon: "speedometer2",
              url: "dashboard.html",
              roles: ["staff", "admin", "superadmin"],
            },
          ],
        },
        inventory: {
          title: "Inventory",
          items: [
            {
              label: "Stock Overview",
              icon: "box",
              url: "inventory.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Stock Movements",
              icon: "arrow-left-right",
              url: "stock-movements.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Stock Requirements",
              icon: "clipboard-data",
              url: "stock-requirements.html",
              roles: ["admin", "superadmin"],
            },
          ],
        },
        procurement: {
          title: "Procurement",
          items: [
            {
              label: "Purchase Orders",
              icon: "cart-plus",
              url: "purchase-orders.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Goods Receipt",
              icon: "box-arrow-in-down",
              url: "goods-receipt.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Suppliers",
              icon: "truck",
              url: "suppliers.html",
              roles: ["admin", "superadmin"],
            },
          ],
        },
        sales: {
          title: "Sales",
          items: [
            {
              label: "Customer Inquiries",
              icon: "envelope",
              url: "inquiries.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Customers",
              icon: "person-lines-fill",
              url: "customers.html",
              roles: ["admin", "superadmin"],
            },
            {
              label: "Sales Orders",
              icon: "cart-check",
              url: "sales-orders.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Invoices",
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
              label: "Analytics",
              icon: "graph-up",
              url: "reports.html",
              roles: ["admin", "superadmin"],
            },
          ],
        },
      };
    },

    hasAccess(item) {
      if (!this.currentUser?.role) return false;
      return item.roles.includes(this.currentUser.role.toLowerCase());
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

    attachSidebarListeners() {
      const toggleBtn = document.querySelector(".mobile-menu-toggle");
      if (toggleBtn) {
        const newBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);

        newBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.toggleSidebar();
        });
      }

      if (this.overlay) {
        this.overlay.addEventListener("click", (e) => {
          e.stopPropagation();
          this.closeSidebar();
        });
      }

      if (this.sidebar) {
        this.sidebar.querySelectorAll(".menu-item").forEach((item) => {
          item.addEventListener("click", () => {
            if (window.innerWidth <= 768) {
              setTimeout(() => this.closeSidebar(), 100);
            }
          });
        });
      }

      window.addEventListener("resize", () => {
        if (window.innerWidth > 768 && this.isSidebarOpen()) {
          this.closeSidebar();
        }
      });

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
      this.sidebar.classList.add("mobile-show");
      this.overlay.classList.add("active");
      this.overlay.style.display = "block";
      document.body.style.overflow = "hidden";
    },

    closeSidebar() {
      if (!this.sidebar || !this.overlay) return;
      this.sidebar.classList.remove("mobile-show");
      this.overlay.classList.remove("active");
      this.overlay.style.display = "none";
      document.body.style.overflow = "";
    },

    isSidebarOpen() {
      return this.sidebar?.classList.contains("mobile-show") || false;
    },

    attachGlobalListeners() {
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

      window.addEventListener("error", (event) => {
        console.error("Global error:", event.error);
      });

      window.addEventListener("unhandledrejection", (event) => {
        console.error("Unhandled promise rejection:", event.reason);
      });
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => AppCore.init());
  } else {
    AppCore.init();
  }

  window.AppCore = AppCore;

  console.log("✅ App Core v7.1 FINAL Loaded - Sidebar Logo Removed");
})(window, document);
