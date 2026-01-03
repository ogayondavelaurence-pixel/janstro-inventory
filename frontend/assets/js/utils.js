/**
 * ============================================================================
 * JANSTRO IMS - UTILITY FUNCTIONS v7.3 (arrayToCSV FIXED)
 * ============================================================================
 * Path: frontend/assets/js/utils.js
 *
 * CHANGELOG v7.3:
 * ✅ Added missing arrayToCSV() method (Line 311-334)
 * ✅ Fixed purchase-requisitions.html export error
 * ============================================================================
 */

const Utils = {
  // ========================================================================
  // LOADING STATES
  // ========================================================================

  /**
   * Show/hide loading overlay
   * @param {boolean} show - Show or hide
   * @param {string} message - Loading message
   */
  loadingState(show, message = "Loading...") {
    let loader = document.querySelector(".loading-overlay");

    if (show) {
      if (!loader) {
        loader = document.createElement("div");
        loader.className = "loading-overlay";
        loader.style.cssText = `
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0,0,0,0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 9999;
          flex-direction: column;
          color: white;
          backdrop-filter: blur(4px);
        `;
        document.body.appendChild(loader);
      }
      loader.innerHTML = `
        <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p style="font-size: 1.1rem;">${message}</p>
      `;
      loader.style.display = "flex";
    } else {
      if (loader) {
        loader.remove();
      }
    }
  },

  // ========================================================================
  // TOAST NOTIFICATIONS
  // ========================================================================

  /**
   * Show toast notification (Bootstrap 5 compatible)
   * @param {string} message - Message to display
   * @param {string} type - success, error, warning, info
   * @param {number} duration - Duration in ms (default 3000)
   */
  showToast(message, type = "info", duration = 3000) {
    const toastContainer = this.getOrCreateToastContainer();

    // Map types to Bootstrap classes
    const typeMap = {
      success: "success",
      error: "danger",
      warning: "warning",
      info: "info",
    };

    const bgClass = typeMap[type] || "info";

    // Create toast element
    const toastId = `toast-${Date.now()}`;
    const toast = document.createElement("div");
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${bgClass} border-0`;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");

    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi bi-${this.getToastIcon(type)} me-2"></i>
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;

    toastContainer.appendChild(toast);

    // Initialize Bootstrap toast
    if (window.bootstrap && window.bootstrap.Toast) {
      const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: duration,
      });
      bsToast.show();

      toast.addEventListener("hidden.bs.toast", () => {
        toast.remove();
      });
    } else {
      // Fallback if Bootstrap is not available
      toast.style.display = "block";
      setTimeout(() => {
        toast.remove();
      }, duration);
    }
  },

  /**
   * ✅ NEW: Show alert (alias for showToast - fixes users.html:326 error)
   * @param {string} message - Message to display
   * @param {string} type - success, error, warning, info
   * @param {number} duration - Duration in ms (default 3000)
   */
  showAlert(message, type = "info", duration = 3000) {
    return this.showToast(message, type, duration);
  },

  /**
   * Get icon for toast type
   */
  getToastIcon(type) {
    const icons = {
      success: "check-circle-fill",
      error: "exclamation-triangle-fill",
      warning: "exclamation-circle-fill",
      info: "info-circle-fill",
    };
    return icons[type] || icons.info;
  },

  /**
   * Get or create toast container
   */
  getOrCreateToastContainer() {
    let container = document.querySelector(".toast-container");
    if (!container) {
      container = document.createElement("div");
      container.className = "toast-container position-fixed top-0 end-0 p-3";
      container.style.zIndex = "9999";
      document.body.appendChild(container);
    }
    return container;
  },

  // ========================================================================
  // FORMATTING
  // ========================================================================

  /**
   * Format currency (Philippine Peso)
   * @param {number} amount - Amount to format
   * @returns {string} Formatted currency
   */
  formatCurrency(amount) {
    const num = parseFloat(amount || 0);
    return "PHP " + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, "$&,");
  },

  /**
   * Format date to readable format
   * @param {string|Date} date - Date to format
   * @param {boolean} includeTime - Include time
   * @returns {string} Formatted date
   */
  formatDate(date, includeTime = false) {
    if (!date) return "N/A";

    const d = new Date(date);
    if (isNaN(d.getTime())) return "Invalid Date";

    const options = {
      year: "numeric",
      month: "short",
      day: "numeric",
    };

    if (includeTime) {
      options.hour = "2-digit";
      options.minute = "2-digit";
    }

    return d.toLocaleDateString("en-US", options);
  },

  /**
   * Format datetime to readable format
   * @param {string|Date} datetime - Datetime to format
   * @returns {string} Formatted datetime
   */
  formatDateTime(datetime) {
    return this.formatDate(datetime, true);
  },

  /**
   * Format phone number (Philippine format)
   * @param {string} phone - Phone number
   * @returns {string} Formatted phone
   */
  formatPhone(phone) {
    if (!phone) return "N/A";

    // Remove non-digits
    const cleaned = phone.replace(/\D/g, "");

    // Format: 09XX-XXX-XXXX or +63 9XX-XXX-XXXX
    if (cleaned.startsWith("63") && cleaned.length === 12) {
      return `+63 ${cleaned.slice(2, 5)}-${cleaned.slice(5, 8)}-${cleaned.slice(
        8
      )}`;
    } else if (cleaned.startsWith("9") && cleaned.length === 10) {
      return `0${cleaned.slice(0, 3)}-${cleaned.slice(3, 6)}-${cleaned.slice(
        6
      )}`;
    } else if (cleaned.startsWith("09") && cleaned.length === 11) {
      return `${cleaned.slice(0, 4)}-${cleaned.slice(4, 7)}-${cleaned.slice(
        7
      )}`;
    }

    return phone;
  },

  /**
   * Format file size
   * @param {number} bytes - File size in bytes
   * @returns {string} Formatted size
   */
  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  },

  /**
   * Truncate text with ellipsis
   * @param {string} text - Text to truncate
   * @param {number} length - Max length
   * @returns {string} Truncated text
   */
  truncate(text, length = 50) {
    if (!text) return "";
    return text.length > length ? text.substring(0, length) + "..." : text;
  },

  // ========================================================================
  // VALIDATION
  // ========================================================================

  /**
   * Validate email format
   * @param {string} email - Email to validate
   * @returns {boolean} Valid or not
   */
  validateEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
  },

  /**
   * Validate Philippine phone number
   * @param {string} phone - Phone to validate
   * @returns {boolean} Valid or not
   */
  validatePhone(phone) {
    // Accepts: 09XXXXXXXXX or +639XXXXXXXXX
    const pattern = /^(\+?639|09)\d{9}$/;
    return pattern.test(phone.replace(/\D/g, ""));
  },

  /**
   * Validate required fields
   * @param {object} data - Data object
   * @param {array} requiredFields - Required field names
   * @returns {object} {valid: boolean, missing: array}
   */
  validateRequired(data, requiredFields) {
    const missing = requiredFields.filter(
      (field) => !data[field] || data[field] === ""
    );
    return {
      valid: missing.length === 0,
      missing: missing,
    };
  },

  // ========================================================================
  // DATA UTILITIES
  // ========================================================================

  /**
   * Safe array check before operations
   * @param {any} data - Data to check
   * @returns {array} Array or empty array
   */
  ensureArray(data) {
    return Array.isArray(data) ? data : [];
  },

  /**
   * Deep clone object
   * @param {object} obj - Object to clone
   * @returns {object} Cloned object
   */
  deepClone(obj) {
    return JSON.parse(JSON.stringify(obj));
  },

  /**
   * Group array by key
   * @param {array} array - Array to group
   * @param {string} key - Key to group by
   * @returns {object} Grouped object
   */
  groupBy(array, key) {
    return array.reduce((result, item) => {
      const group = item[key];
      if (!result[group]) {
        result[group] = [];
      }
      result[group].push(item);
      return result;
    }, {});
  },

  // ========================================================================
  // DEBOUNCE
  // ========================================================================

  /**
   * Debounce function for search inputs
   * @param {function} func - Function to debounce
   * @param {number} wait - Wait time in ms
   * @returns {function} Debounced function
   */
  debounce(func, wait = 300) {
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

  // ========================================================================
  // URL & QUERY PARAMS
  // ========================================================================

  /**
   * Get query parameter from URL
   * @param {string} param - Parameter name
   * @returns {string|null} Parameter value
   */
  getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  },

  /**
   * Update query parameter
   * @param {string} param - Parameter name
   * @param {string} value - Parameter value
   */
  updateQueryParam(param, value) {
    const url = new URL(window.location);
    url.searchParams.set(param, value);
    window.history.pushState({}, "", url);
  },

  // ========================================================================
  // CLIPBOARD
  // ========================================================================

  /**
   * Copy text to clipboard
   * @param {string} text - Text to copy
   */
  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      this.showToast("Copied to clipboard!", "success");
    } catch (err) {
      this.showToast("Failed to copy", "error");
    }
  },

  // ========================================================================
  // ✅ EXPORT DATA (FIXED - arrayToCSV added)
  // ========================================================================

  /**
   * Download data as JSON file
   * @param {any} data - Data to export
   * @param {string} filename - Filename
   */
  downloadJSON(data, filename = "data.json") {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: "application/json" });
    this.downloadBlob(blob, filename);
  },

  /**
   * ✅ NEW: Convert array to CSV string
   * @param {array} data - Array of objects
   * @returns {string} CSV string
   */
  arrayToCSV(data) {
    if (!Array.isArray(data) || data.length === 0) {
      return "";
    }

    // Get headers from first object
    const headers = Object.keys(data[0]);
    const csvRows = [];

    // Add headers
    csvRows.push(headers.join(","));

    // Add rows
    data.forEach((row) => {
      const values = headers.map((header) => {
        const value = row[header] || "";
        // Escape quotes and wrap in quotes if contains comma
        const escaped = String(value).replace(/"/g, '""');
        return escaped.includes(",") ? `"${escaped}"` : escaped;
      });
      csvRows.push(values.join(","));
    });

    return csvRows.join("\n");
  },

  /**
   * Download data as CSV file
   * @param {array} data - Data array
   * @param {string} filename - Filename
   */
  downloadCSV(data, filename = "data.csv") {
    if (!Array.isArray(data) || data.length === 0) {
      this.showToast("No data to export", "warning");
      return;
    }

    const csv = this.arrayToCSV(data);
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    this.downloadBlob(blob, filename);
  },

  /**
   * Download blob as file
   * @param {Blob} blob - Blob to download
   * @param {string} filename - Filename
   */
  downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  },

  // ========================================================================
  // UI HELPERS
  // ========================================================================

  /**
   * Confirm dialog with custom message
   * @param {string} message - Confirmation message
   * @returns {boolean} User's choice
   */
  confirm(message) {
    return window.confirm(message);
  },

  /**
   * Alert dialog with custom message
   * @param {string} message - Alert message
   * @param {string} type - Type (info, success, error, warning)
   */
  alert(message, type = "info") {
    this.showToast(message, type, 5000);
  },

  /**
   * Get status badge color
   * @param {string} status - Status value
   * @returns {string} Bootstrap color class
   */
  getStatusColor(status) {
    const colors = {
      pending: "warning",
      approved: "info",
      completed: "success",
      cancelled: "danger",
      delivered: "success",
      active: "success",
      inactive: "secondary",
      new: "primary",
      in_progress: "warning",
      resolved: "success",
      closed: "secondary",
    };
    return colors[status?.toLowerCase()] || "secondary";
  },

  /**
   * Get priority badge color
   * @param {string} priority - Priority value
   * @returns {string} Bootstrap color class
   */
  getPriorityColor(priority) {
    const colors = {
      low: "info",
      medium: "warning",
      high: "danger",
      critical: "danger",
    };
    return colors[priority?.toLowerCase()] || "secondary";
  },

  /**
   * Sanitize HTML to prevent XSS
   * @param {string} str - String to sanitize
   * @returns {string} Sanitized string
   */
  sanitizeHTML(str) {
    const temp = document.createElement("div");
    temp.textContent = str;
    return temp.innerHTML;
  },

  /**
   * Generate random ID
   * @returns {string} Random ID
   */
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  },

  /**
   * Sleep/delay function
   * @param {number} ms - Milliseconds to wait
   * @returns {Promise} Promise that resolves after delay
   */
  sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  },

  // ========================================================================
  // USER & ROLE HELPERS
  // ========================================================================

  /**
   * Check if user has required role
   * @param {string|array} allowedRoles - Allowed roles
   * @returns {boolean} Has role or not
   */
  hasRole(allowedRoles) {
    if (window.API && window.API.hasRole) {
      return window.API.hasRole(allowedRoles);
    }
    return false;
  },

  /**
   * Get current user data
   * @returns {object|null} User data or null
   */
  getCurrentUser() {
    if (window.API && window.API.getCurrentUserData) {
      return window.API.getCurrentUserData();
    }
    return null;
  },
};

// ========================================================================
// EXPORT
// ========================================================================
window.Utils = Utils;
