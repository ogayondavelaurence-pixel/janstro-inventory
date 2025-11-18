/**
 * Janstro Inventory System - FIXED API Wrapper
 * Version: 3.1.0 - Production Ready
 */

const API = {
  // ✅ FIXED: Remove double port issue
  baseURL: (() => {
    const origin = window.location.origin;
    const path = "/janstro-inventory/public";

    // If already on port 8080, don't add it again
    if (origin.includes(":8080")) {
      return origin + path;
    }

    // Development: add port 8080
    if (origin.includes("localhost") || origin.includes("127.0.0.1")) {
      return origin.replace(/:\d+/, "") + ":8080" + path;
    }

    // Production: use origin as-is
    return origin + path;
  })(),

  tokenKey: "janstro_token",
  userKey: "janstro_user",

  getToken() {
    return localStorage.getItem(this.tokenKey);
  },

  setToken(token) {
    localStorage.setItem(this.tokenKey, token);
  },

  removeToken() {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  },

  getUser() {
    const userJson = localStorage.getItem(this.userKey);
    return userJson ? JSON.parse(userJson) : null;
  },

  setUser(user) {
    localStorage.setItem(this.userKey, JSON.stringify(user));
  },

  isAuthenticated() {
    return !!this.getToken();
  },

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;

    const config = {
      headers: {
        "Content-Type": "application/json",
        ...options.headers,
      },
      ...options,
    };

    const token = this.getToken();
    if (token) {
      config.headers["Authorization"] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(url, config);
      const data = await response.json().catch(() => ({}));

      if (response.status === 401) {
        this.removeToken();
        window.location.href = "/janstro-inventory/frontend/index.html";
        throw new Error("Session expired. Please login again.");
      }

      if (!response.ok) {
        throw new Error(data.message || "API request failed.");
      }

      return data;
    } catch (error) {
      console.error("API Error:", error);
      throw error;
    }
  },

  async get(endpoint) {
    return this.request(endpoint, { method: "GET" });
  },

  async post(endpoint, data) {
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  async put(endpoint, data) {
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },

  async delete(endpoint) {
    return this.request(endpoint, { method: "DELETE" });
  },

  // ============================================
  // AUTH
  // ============================================

  async login(username, password) {
    const response = await this.post("/auth/login", { username, password });
    if (response.success && response.data) {
      this.setToken(response.data.token);
      this.setUser(response.data.user);
    }
    return response;
  },

  logout() {
    this.removeToken();
    window.location.href = "/janstro-inventory/frontend/index.html";
  },

  async getCurrentUser() {
    return this.get("/auth/me");
  },

  // ============================================
  // INVENTORY
  // ============================================

  async getInventory() {
    return this.get("/inventory");
  },

  async getItem(id) {
    return this.get(`/inventory/${id}`);
  },

  async getInventoryStatus() {
    return this.get("/inventory/status");
  },

  async checkStock(id) {
    return this.get(`/inventory/check-stock?item_id=${id}`);
  },

  async getLowStockItems() {
    return this.get("/inventory/low-stock");
  },

  async getStockMovements(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/inventory/movements?${params}`);
  },

  // ✅ FIXED: Add missing getStockMovementsSummary
  async getStockMovementsSummary() {
    return this.get("/inventory/movements/summary");
  },

  // ============================================
  // PURCHASE ORDERS
  // ============================================

  async getPurchaseOrders() {
    return this.get("/purchase-orders");
  },

  async getPurchaseOrder(id) {
    return this.get(`/purchase-orders/${id}`);
  },

  async createPurchaseOrder(data) {
    return this.post("/purchase-orders", data);
  },

  async receiveGoods(id, data) {
    return this.post(`/purchase-orders/receive/${id}`, data);
  },

  async getPendingPurchaseOrders() {
    return this.get("/purchase-orders?status=pending");
  },

  // ============================================
  // SALES ORDERS
  // ============================================

  async getSalesOrders() {
    return this.get("/sales-orders");
  },

  async getSalesOrder(id) {
    return this.get(`/sales-orders/${id}`);
  },

  async createSalesOrder(data) {
    return this.post("/sales-orders", data);
  },

  async processInvoice(id, data) {
    return this.post(`/sales-orders/invoice/${id}`, data);
  },

  async getPendingSalesOrders() {
    return this.get("/sales-orders?status=pending");
  },

  // ============================================
  // SUPPLIERS
  // ============================================

  async getSuppliers() {
    return this.get("/suppliers");
  },

  async getSupplier(id) {
    return this.get(`/suppliers/${id}`);
  },

  async createSupplier(data) {
    return this.post("/suppliers", data);
  },

  async updateSupplier(id, data) {
    return this.put(`/suppliers/${id}`, data);
  },

  // ============================================
  // CUSTOMERS (NEW)
  // ============================================

  async getCustomers() {
    return this.get("/customers");
  },

  async getCustomer(id) {
    return this.get(`/customers/${id}`);
  },

  async createCustomer(data) {
    return this.post("/customers", data);
  },

  async updateCustomer(id, data) {
    return this.put(`/customers/${id}`, data);
  },

  // ============================================
  // USER MANAGEMENT
  // ============================================

  async getUsers() {
    return this.get("/users");
  },

  async createUser(data) {
    return this.post("/users", data);
  },

  async updateUser(id, data) {
    return this.put(`/users/${id}`, data);
  },

  async deactivateUser(id) {
    return this.delete(`/users/${id}`);
  },

  // ============================================
  // REPORTS
  // ============================================

  async getDashboardReport() {
    return this.get("/reports/dashboard");
  },

  async getInventoryReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/inventory?${params}`);
  },

  async getStockMovementReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/stock-movements?${params}`);
  },

  async getSalesReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/sales?${params}`);
  },

  // ============================================
  // HEALTH CHECK
  // ============================================

  async healthCheck() {
    return this.get("/health");
  },
};

// ✅ Log configuration on load
console.log("✅ API Configuration:", {
  baseURL: API.baseURL,
  environment:
    window.location.hostname === "localhost" ? "development" : "production",
});

window.API = API;

/**
 * ✅ FIXED Utils.js - Complete with escapeHtml
 */
const Utils = {
  /**
   * ✅ FIXED: Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    if (!text) return "";
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, (m) => map[m]);
  },

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
          <strong>Error:</strong> ${this.escapeHtml(message)}
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
          <strong>Success:</strong> ${this.escapeHtml(message)}
        </div>
      `;
    }
  },

  /**
   * Show toast notification
   */
  showToast(message, type = "success") {
    const toastContainer =
      document.getElementById("toastContainer") ||
      (() => {
        const container = document.createElement("div");
        container.id = "toastContainer";
        container.className = "toast-container position-fixed top-0 end-0 p-3";
        container.style.zIndex = "9999";
        document.body.appendChild(container);
        return container;
      })();

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
        <div class="toast-body">${this.escapeHtml(message)}</div>
      </div>
    `;

    toastContainer.insertAdjacentHTML("beforeend", toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

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
                <h5 class="modal-title">${this.escapeHtml(title)}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">${this.escapeHtml(message)}</div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
              </div>
            </div>
          </div>
        </div>
      `;

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
   * Validate phone number (Philippine format)
   */
  validatePhone(phone) {
    const re = /^(09|\+639)\d{9}$/;
    return re.test(phone);
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
   * Require role (redirect if not authorized)
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

window.Utils = Utils;
console.log("✅ Utils loaded with escapeHtml support");
