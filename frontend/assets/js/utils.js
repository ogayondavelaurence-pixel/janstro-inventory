/**
 * Janstro Inventory System - Utility Functions
 * Helper functions for common tasks
 * Version: 2.0.0
 */

const Utils = {
  /**
   * Format date to readable string
   */
  formatDate(dateString) {
    if (!dateString) return "N/A";

    const date = new Date(dateString);
    const options = {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    };

    return date.toLocaleDateString("en-US", options);
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
   * Format number as currency (Philippine Peso)
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
   * Show loading spinner
   */
  showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
      element.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading data...</p>
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
                    <i class="bi bi-exclamation-triangle-fill"></i>
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
                    <i class="bi bi-check-circle-fill"></i>
                    <strong>Success:</strong> ${message}
                </div>
            `;
    }
  },

  /**
   * Show toast notification
   */
  showToast(message, type = "success") {
    const toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
      // Create toast container if it doesn't exist
      const container = document.createElement("div");
      container.id = "toastContainer";
      container.className = "toast-container position-fixed top-0 end-0 p-3";
      container.style.zIndex = "9999";
      document.body.appendChild(container);
    }

    const toastId = `toast-${Date.now()}`;
    const iconClass =
      type === "success"
        ? "bi-check-circle-fill"
        : type === "error"
        ? "bi-exclamation-triangle-fill"
        : "bi-info-circle-fill";
    const bgClass =
      type === "success"
        ? "bg-success"
        : type === "error"
        ? "bg-danger"
        : "bg-info";

    const toastHTML = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${bgClass} text-white">
                    <i class="bi ${iconClass} me-2"></i>
                    <strong class="me-auto">${
                      type.charAt(0).toUpperCase() + type.slice(1)
                    }</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

    document
      .getElementById("toastContainer")
      .insertAdjacentHTML("beforeend", toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    // Remove toast element after it's hidden
    toastElement.addEventListener("hidden.bs.toast", () => {
      toastElement.remove();
    });
  },

  /**
   * Confirm action with modal
   */
  async confirmAction(title, message) {
    return new Promise((resolve) => {
      const modalHTML = `
                <div class="modal fade" id="confirmModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${message}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      // Remove existing modal if any
      const existingModal = document.getElementById("confirmModal");
      if (existingModal) {
        existingModal.remove();
      }

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
   * Validate phone number (Philippine format)
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
   * Update page title with user info
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
            // Escape commas and quotes
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
    link.setAttribute("download", filename);
    link.style.visibility = "hidden";

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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
};

// Make Utils available globally
window.Utils = Utils;
