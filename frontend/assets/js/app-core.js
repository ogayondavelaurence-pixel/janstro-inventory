/**
 * ============================================================================
 * JANSTRO IMS - APP CORE v8.3 PRODUCTION (DROPDOWN FIX)
 * ============================================================================
 * FIXES APPLIED:
 * ✅ Dropdown menu proper Bootstrap 5 structure
 * ✅ Z-index hierarchy corrected (navbar > dropdown > overlay > sidebar)
 * ✅ Click/touch event handling improved
 * ✅ Mobile dropdown behavior fixed
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
    isMobileDevice: false,
    touchStartX: 0,
    sidebarSwipeActive: false,

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    async init() {
      this.detectMobileDevice();
      await this.checkAuth();

      if (this.currentUser) {
        this.initTheme();
        this.initNavbar();
        this.initSidebar();
        this.fixARIAIssues();

        if (this.isMobileDevice) {
          this.initMobileFeatures();
        }

        this.attachGlobalListeners();
      }
    },

    detectMobileDevice() {
      this.isMobileDevice =
        window.innerWidth <= 768 ||
        "ontouchstart" in window ||
        navigator.maxTouchPoints > 0;

      if (this.isMobileDevice) {
        document.body.classList.add("mobile-device");
      } else {
        document.body.classList.add("desktop-device");
      }
    },

    // ========================================================================
    // AUTHENTICATION CHECK
    // ========================================================================
    async checkAuth() {
      const currentPage =
        window.location.pathname.split("/").pop() || "index.html";
      const publicPages = [
        "index.html",
        "forgot-password.html",
        "reset-password.html",
        "",
      ];

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
        console.error("Authentication failed:", error.message);
        API.clearToken();
        window.location.href = "index.html";
        return;
      }

      if (window.RBAC && !RBAC.enforce()) {
        return;
      }
    },

    // ========================================================================
    // ✅ FIXED NAVBAR WITH WORKING DROPDOWN
    // ========================================================================
    initNavbar() {
      if (!this.currentUser) return;

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

      const dropdownMenuHTML = this.buildSettingsDropdown();

      const navbarHtml = `
        <nav class="navbar" style="position: relative; z-index: 1050;">
          <a href="dashboard.html" class="navbar-brand" style="text-decoration: none;">
            <div class="navbar-logo">
              <img src="assets/images/janstro-logo.png" 
                   alt="Janstro Prime Logo" 
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <span class="logo-icon" style="display: none;">☀️</span>
            </div>
            <div class="navbar-title">
              <strong>Janstro Prime</strong>
              <small>Renewable Energy Solutions</small>
            </div>
          </a>

          <div class="navbar-right">
            <!-- ✅ FIXED: Proper Bootstrap 5 dropdown structure -->
            <div class="dropdown">
              <a class="nav-link dropdown-toggle" 
                 href="#" 
                 id="settingsDropdown" 
                 role="button"
                 data-bs-toggle="dropdown" 
                 aria-expanded="false"
                 style="cursor: pointer; display: flex; align-items: center; gap: 0.75rem; position: relative; z-index: 1051;">
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

              <!-- ✅ FIXED: Dropdown menu with proper z-index and positioning -->
              <ul class="dropdown-menu dropdown-menu-end" 
                  aria-labelledby="settingsDropdown"
                  style="position: absolute; z-index: 1052; min-width: 250px; margin-top: 0.5rem;">
                ${dropdownMenuHTML}
              </ul>
            </div>
          </div>
        </nav>
      `;

      const existingNavbar = document.querySelector(".navbar");
      if (existingNavbar) {
        existingNavbar.remove();
      }

      document.body.insertAdjacentHTML("afterbegin", navbarHtml);

      // ✅ ENHANCEMENT: Ensure Bootstrap dropdown is properly initialized
      setTimeout(() => {
        const dropdownElement = document.getElementById("settingsDropdown");
        if (dropdownElement && window.bootstrap?.Dropdown) {
          // Force Bootstrap to recognize the dropdown
          new bootstrap.Dropdown(dropdownElement);
        }
      }, 100);
    },

    buildSettingsDropdown() {
      const role = this.currentUser.role.toLowerCase();
      let html = "";

      if (role === "superadmin") {
        html +=
          '<li class="dropdown-header"><i class="bi bi-gear-fill"></i> SYSTEM SETTINGS</li>';
        html += `
          <li><a class="dropdown-item" href="email-settings.html"><i class="bi bi-envelope"></i> Email Settings</a></li>
          <li><a class="dropdown-item" href="users.html"><i class="bi bi-people"></i> User Management</a></li>
          <li><a class="dropdown-item" href="audit-logs.html"><i class="bi bi-file-text"></i> Audit Logs</a></li>
          <li><a class="dropdown-item" href="deletion-approvals.html"><i class="bi bi-exclamation-triangle"></i> Deletion Requests</a></li>
        `;
        html += '<li><hr class="dropdown-divider"></li>';
      }

      html +=
        '<li class="dropdown-header"><i class="bi bi-person-circle"></i> MY ACCOUNT</li>';
      html += `
        <li><a class="dropdown-item" href="profile-settings.html"><i class="bi bi-person-gear"></i> Profile Settings</a></li>
        <li><a class="dropdown-item" href="privacy-settings.html"><i class="bi bi-shield-check"></i> Privacy & Security</a></li>
      `;

      html += '<li><hr class="dropdown-divider"></li>';
      html += `<li><a class="dropdown-item" href="#" onclick="API.logout(); return false;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>`;

      return html;
    },

    refreshNavbarProfilePicture(newProfilePicture) {
      const avatarContainer = document.getElementById("navbarProfileAvatar");
      if (!avatarContainer) return;

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
    // ✅ FIXED SIDEBAR
    // ========================================================================
    initSidebar() {
      const container = document.getElementById("sidebarContainer");
      if (!container) return;

      container.innerHTML = this.renderSidebar();
      this.sidebar = document.getElementById("mainSidebar");
      this.overlay = this.createOverlay();
      this.attachSidebarListeners();

      if (this.isMobileDevice) {
        this.initMobileSidebar();
      }

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

      return `<div class="sidebar" id="mainSidebar" style="z-index: 1046;"><div class="sidebar-menu">${menuHTML}</div></div>`;
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
              label: "Materials Master Data",
              icon: "box-seam",
              url: "materials.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Inventory List",
              icon: "boxes",
              url: "inventory.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Stock Overview",
              icon: "box",
              url: "stock-movements.html",
              roles: ["staff", "admin", "superadmin"],
            },
            {
              label: "Stock Requirements",
              icon: "clipboard-data",
              url: "stock-requirements.html",
              roles: ["admin", "superadmin"],
            },
            {
              label: "Bill of Materials",
              icon: "diagram-3",
              url: "bom-management.html",
              roles: ["admin", "superadmin"],
            },
          ],
        },
        procurement: {
          title: "Procurement",
          items: [
            {
              label: "Purchase Requisitions",
              icon: "clipboard-check",
              url: "purchase-requisitions.html",
              roles: ["staff", "admin", "superadmin"],
            },
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
              label: "Analytics Dashboard",
              icon: "graph-up-arrow",
              url: "analytics-dashboard.html",
              roles: ["admin", "superadmin"],
            },
            {
              label: "Standard Reports",
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
        overlay.style.zIndex = "1045";
        document.body.appendChild(overlay);
      }
      return overlay;
    },

    // ✅ FIXED: Proper z-index and event handling
    attachSidebarListeners() {
      const toggleBtn = document.querySelector(".mobile-menu-toggle");
      if (toggleBtn) {
        const newBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);
        newBtn.style.zIndex = "1040";

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
        this.sidebar.style.zIndex = "1046";

        this.sidebar.querySelectorAll(".menu-item").forEach((item) => {
          item.addEventListener("click", () => {
            if (window.innerWidth <= 768) {
              setTimeout(() => this.closeSidebar(), 100);
            }
          });
        });
      }

      window.addEventListener("resize", () => {
        if (window.innerWidth > 768) {
          this.closeSidebar();
          if (this.sidebar) {
            this.sidebar.style.transform = "";
          }
        }
      });

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && this.isSidebarOpen()) {
          this.closeSidebar();
        }
      });
    },

    initMobileSidebar() {
      if (!this.sidebar) return;

      this.sidebar.addEventListener("touchstart", (e) => {
        this.touchStartX = e.touches[0].clientX;
        this.sidebarSwipeActive = true;
      });

      this.sidebar.addEventListener("touchmove", (e) => {
        if (!this.sidebarSwipeActive || !this.isSidebarOpen()) return;

        const touchX = e.touches[0].clientX;
        const deltaX = touchX - this.touchStartX;

        if (deltaX < 0) {
          const translateX = Math.max(deltaX, -280);
          this.sidebar.style.transform = `translateX(${translateX}px)`;
          this.sidebar.style.transition = "none";
        }
      });

      this.sidebar.addEventListener("touchend", (e) => {
        if (!this.sidebarSwipeActive) return;

        const touchX = e.changedTouches[0].clientX;
        const deltaX = touchX - this.touchStartX;

        this.sidebar.style.transition = "";
        this.sidebar.style.transform = "";

        if (deltaX < -100) {
          this.closeSidebar();
        }

        this.sidebarSwipeActive = false;
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

      if (window.Accessibility) {
        window.Accessibility.announce("Navigation menu opened", "polite");
      }
    },

    closeSidebar() {
      if (!this.sidebar || !this.overlay) return;
      this.sidebar.classList.remove("mobile-show");
      this.overlay.classList.remove("active");
      this.overlay.style.display = "none";
      document.body.style.overflow = "";

      if (window.Accessibility) {
        window.Accessibility.announce("Navigation menu closed", "polite");
      }
    },

    isSidebarOpen() {
      return this.sidebar?.classList.contains("mobile-show") || false;
    },

    initMobileFeatures() {
      this.initKeyboardAvoidance();
      this.handleOrientationChange();
      this.fixMobileViewportHeight();
      this.initBackButtonHandling();
    },

    initKeyboardAvoidance() {
      const inputs = document.querySelectorAll("input, textarea, select");

      inputs.forEach((input) => {
        input.addEventListener("focus", () => {
          document.body.classList.add("keyboard-active");
          setTimeout(() => {
            input.scrollIntoView({ behavior: "smooth", block: "center" });
          }, 300);
        });

        input.addEventListener("blur", () => {
          document.body.classList.remove("keyboard-active");
        });
      });
    },

    handleOrientationChange() {
      window.addEventListener("orientationchange", () => {
        setTimeout(() => {
          this.detectMobileDevice();
          if (this.isSidebarOpen()) {
            this.closeSidebar();
          }
        }, 200);
      });
    },

    fixMobileViewportHeight() {
      const updateViewportHeight = () => {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty("--vh", `${vh}px`);
      };

      updateViewportHeight();
      window.addEventListener("resize", updateViewportHeight);
    },

    initBackButtonHandling() {
      window.addEventListener("popstate", () => {
        if (this.isSidebarOpen()) {
          this.closeSidebar();
          history.pushState(null, null, location.href);
        }
      });
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
        console.error("Global error:", event.error?.message || "Unknown error");
      });

      window.addEventListener("unhandledrejection", (event) => {
        console.error(
          "Unhandled promise rejection:",
          event.reason?.message || "Unknown reason"
        );
      });

      let resizeTimer;
      window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
          this.detectMobileDevice();
        }, 250);
      });
    },

    fixARIAIssues() {
      document.addEventListener("shown.bs.modal", function (e) {
        const modal = e.target;
        const firstInput = modal.querySelector(
          'input:not([type="hidden"]), textarea, select, button'
        );
        if (firstInput) {
          setTimeout(() => firstInput.focus(), 100);
        }
      });

      document.addEventListener("focusin", function (e) {
        if (e.target.hasAttribute("aria-hidden")) {
          e.target.removeAttribute("aria-hidden");
        }
      });

      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (
            mutation.type === "attributes" &&
            mutation.attributeName === "class"
          ) {
            const target = mutation.target;
            if (
              target.classList.contains("show") &&
              target.classList.contains("modal")
            ) {
              target.removeAttribute("aria-hidden");
              const dialog = target.querySelector(".modal-dialog");
              if (dialog) dialog.removeAttribute("aria-hidden");
            }
          }
        });
      });

      document.querySelectorAll(".modal").forEach((modal) => {
        observer.observe(modal, { attributes: true });
      });
    },

    showMobileToast(message, type = "info") {
      if (window.Utils && window.Utils.showToast) {
        window.Utils.showToast(message, type);
      }
    },

    isLandscape() {
      return window.innerWidth > window.innerHeight;
    },

    getSafeAreaInsets() {
      return {
        top: parseInt(
          getComputedStyle(document.documentElement).getPropertyValue(
            "--sat"
          ) || "0"
        ),
        right: parseInt(
          getComputedStyle(document.documentElement).getPropertyValue(
            "--sar"
          ) || "0"
        ),
        bottom: parseInt(
          getComputedStyle(document.documentElement).getPropertyValue(
            "--sab"
          ) || "0"
        ),
        left: parseInt(
          getComputedStyle(document.documentElement).getPropertyValue(
            "--sal"
          ) || "0"
        ),
      };
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => AppCore.init());
  } else {
    AppCore.init();
  }

  window.AppCore = AppCore;
})(window, document);
