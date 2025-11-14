/**
 * Janstro Inventory System - Enhanced Utility Functions
 * Version: 3.0.0 (Production-Ready)
 */

const Utils = {
  /**
   * Format date with caching
   */
  _dateCache: new Map(),

  formatDate(dateString) {
    if (!dateString) return "N/A";

    // Check cache
    if (this._dateCache.has(dateString)) {
      return this._dateCache.get(dateString);
    }

    const date = new Date(dateString);
    const formatted = date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });

    // Cache result
    this._dateCache.set(dateString, formatted);
    return formatted;
  },

  /**
   * Format date to YYYY-MM-DD
   */
  formatDateInput(dateString) {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toISOString().split("T")[0];
  },

  /**
   * Format currency (Philippine Peso)
   */
  formatCurrency(amount) {
    if (amount === null || amount === undefined) return "₱0.00";

    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP",
    }).format(amount);
  },

  /**
   * Format number with thousands separator
   */
  formatNumber(number) {
    if (number === null || number === undefined) return "0";
    return new Intl.NumberFormat("en-US").format(number);
  },

  /**
   * Show enhanced toast notification
   */
  showToast(message, type = "success", duration = 3000) {
    // Remove existing toasts
    const existing = document.querySelectorAll(".toast-notification");
    existing.forEach((t) => t.remove());

    const toast = document.createElement("div");
    toast.className = `toast-notification toast-${type}`;

    const icons = {
      success: "bi-check-circle-fill",
      error: "bi-exclamation-triangle-fill",
      warning: "bi-exclamation-circle-fill",
      info: "bi-info-circle-fill",
    };

    toast.innerHTML = `
      <i class="bi ${icons[type] || icons.info}"></i>
      <span>${message}</span>
    `;

    // Add styles if not present
    if (!document.getElementById("toast-styles")) {
      const style = document.createElement("style");
      style.id = "toast-styles";
      style.textContent = `
        .toast-notification {
          position: fixed;
          top: 20px;
          right: 20px;
          padding: 16px 24px;
          border-radius: 12px;
          color: white;
          font-weight: 600;
          font-size: 14px;
          z-index: 10000;
          display: flex;
          align-items: center;
          gap: 12px;
          box-shadow: 0 8px 24px rgba(0,0,0,0.15);
          animation: slideInRight 0.3s ease, fadeOut 0.3s ease ${
            duration - 300
          }ms forwards;
        }
        
        @keyframes slideInRight {
          from {
            transform: translateX(400px);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        
        @keyframes fadeOut {
          to {
            opacity: 0;
            transform: translateX(400px);
          }
        }
        
        .toast-success { background: linear-gradient(135deg, #34c759, #30d158); }
        .toast-error { background: linear-gradient(135deg, #ff3b30, #ff6961); }
        .toast-warning { background: linear-gradient(135deg, #ff9500, #ffb340); }
        .toast-info { background: linear-gradient(135deg, #0066cc, #3399ff); }
        
        .toast-notification i { font-size: 20px; }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.remove();
    }, duration);
  },

  /**
   * Show loading spinner
   */
  showLoading(elementId, message = "Loading...") {
    const element = document.getElementById(elementId);
    if (element) {
      element.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
          <p class="mt-3 text-muted">${message}</p>
        </div>
      `;
    }
  },

  /**
   * Show error message
   */
  showError(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
      element.innerHTML = `
        <div class="alert alert-danger" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <strong>Error:</strong> ${message}
        </div>
      `;
    }
  },

  /**
   * Show success message
   */
  showSuccess(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
      element.innerHTML = `
        <div class="alert alert-success" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i>
          <strong>Success:</strong> ${message}
        </div>
      `;
    }
  },

  /**
   * Confirm action with better UI
   */
  async confirmAction(title, message) {
    return new Promise((resolve) => {
      const modalHTML = `
        <div class="modal fade" id="confirmModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none;">
              <div class="modal-header" style="background: linear-gradient(135deg, #0066cc, #00a86b); color: white; border: none;">
                <h5 class="modal-title">${title}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body" style="padding: 30px;">
                ${message}
              </div>
              <div class="modal-footer" style="border: none; padding: 20px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBtn" style="background: linear-gradient(135deg, #0066cc, #00a86b); border: none; border-radius: 10px;">Confirm</button>
              </div>
            </div>
          </div>
        </div>
      `;

      // Remove existing modal
      const existingModal = document.getElementById("confirmModal");
      if (existingModal) existingModal.remove();

      document.body.insertAdjacentHTML("beforeend", modalHTML);

      const modal = new bootstrap.Modal(
        document.getElementById("confirmModal")
      );

      document.getElementById("confirmBtn").addEventListener("click", () => {
        modal.hide();
        resolve(true);
      });

      document
        .getElementById("confirmModal")
        .addEventListener("hidden.bs.modal", () => {
          document.getElementById("confirmModal").remove();
          resolve(false);
        });

      modal.show();
    });
  },

  /**
   * Validate email format
   */
  validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  },

  /**
   * Validate Philippine phone number
   */
  validatePhone(phone) {
    const re = /^(09|\+639)\d{9}$/;
    return re.test(phone);
  },

  /**
   * Sanitize input (prevent XSS)
   */
  sanitizeInput(input) {
    const div = document.createElement("div");
    div.textContent = input;
    return div.innerHTML;
  },

  /**
   * Get badge class for status
   */
  getStatusBadge(status) {
    const badges = {
      pending: "bg-warning text-dark",
      received: "bg-success",
      completed: "bg-success",
      cancelled: "bg-danger",
      active: "bg-success",
      inactive: "bg-secondary",
      low_stock: "bg-danger",
      in_stock: "bg-success",
    };
    return badges[status] || "bg-secondary";
  },

  /**
   * Get transaction type badge
   */
  getTransactionBadge(type) {
    const badges = {
      IN: "bg-success",
      OUT: "bg-danger",
      ADJUSTMENT: "bg-warning text-dark",
    };
    return badges[type] || "bg-secondary";
  },

  /**
   * Check if user has required role
   */
  hasRole(requiredRole) {
    const user = API.getUser();
    if (!user) return false;

    const roleHierarchy = {
      superadmin: 4,
      admin: 3,
      manager: 2,
      staff: 1,
    };

    const userLevel = roleHierarchy[user.role] || 0;
    const requiredLevel = roleHierarchy[requiredRole] || 0;

    return userLevel >= requiredLevel;
  },

  /**
   * Protect page by role
   */
  requireRole(requiredRole) {
    if (!API.isAuthenticated()) {
      window.location.href = "/janstro-inventory/frontend/index.html";
      return false;
    }

    if (!this.hasRole(requiredRole)) {
      window.location.href = "/janstro-inventory/frontend/dashboard.html";
      return false;
    }

    return true;
  },

  /**
   * Update page header with user info
   */
  updatePageHeader() {
    const user = API.getUser();
    if (user) {
      const userNameElement = document.getElementById("userName");
      const userRoleElement = document.getElementById("userRole");

      if (userNameElement) {
        userNameElement.textContent = user.name || user.username;
      }

      if (userRoleElement) {
        userRoleElement.textContent = user.role ? user.role.toUpperCase() : "";
      }
    }
  },

  /**
   * Export table to CSV
   */
  exportToCSV(data, filename) {
    if (!data || data.length === 0) {
      this.showToast("No data to export", "error");
      return;
    }

    const headers = Object.keys(data[0]);
    const csvContent = [
      headers.join(","),
      ...data.map((row) =>
        headers
          .map((header) => {
            const value = row[header];
            if (
              typeof value === "string" &&
              (value.includes(",") || value.includes('"'))
            ) {
              return `"${value.replace(/"/g, '""')}"`;
            }
            return value;
          })
          .join(",")
      ),
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);

    link.setAttribute("href", url);
    link.setAttribute(
      "download",
      `${filename}_${new Date().toISOString().split("T")[0]}.csv`
    );
    link.style.visibility = "hidden";

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    this.showToast("File exported successfully!", "success");
  },

  /**
   * Debounce function (for search inputs)
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Initialize tooltips (Bootstrap)
   */
  initTooltips() {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(
      (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
    );
  },

  /**
   * Validate stock availability
   */
  validateStock(available, requested) {
    if (requested <= 0) {
      this.showToast("Quantity must be greater than 0", "error");
      return false;
    }

    if (requested > available) {
      this.showToast(
        `Insufficient stock. Only ${available} units available`,
        "error"
      );
      return false;
    }

    return true;
  },

  /**
   * Format file size
   */
  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  },

  /**
   * Copy to clipboard
   */
  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      this.showToast("Copied to clipboard!", "success");
    } catch (error) {
      this.showToast("Failed to copy", "error");
    }
  },

  /**
   * Get relative time (e.g., "2 hours ago")
   */
  getRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return "Just now";
    if (diffMins < 60)
      return `${diffMins} minute${diffMins > 1 ? "s" : ""} ago`;
    if (diffHours < 24)
      return `${diffHours} hour${diffHours > 1 ? "s" : ""} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`;

    return this.formatDate(dateString);
  },

  /**
   * Generate random color
   */
  randomColor() {
    return "#" + Math.floor(Math.random() * 16777215).toString(16);
  },

  /**
   * Scroll to top smoothly
   */
  scrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
  },
};

// Make Utils available globally
window.Utils = Utils;
