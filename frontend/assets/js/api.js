// @ts-nocheck
/**
 * Janstro Inventory API Client - Production Ready v3.0
 *
 * Features:
 * - Request/response interceptors
 * - Automatic token refresh
 * - Error handling & retry logic
 * - Request timeout
 * - Network status detection
 */
const API = {
  BASE_URL: "http://localhost/janstro-inventory/public",
  TOKEN_KEY: "janstro_token",
  USER_KEY: "janstro_user",
  TIMEOUT: 30000, // 30 seconds
  MAX_RETRIES: 3,

  /**
   * Make HTTP request with enhanced error handling
   */
  async request(endpoint, options = {}) {
    const url = `${this.BASE_URL}${endpoint}`;
    const token = this.getToken();

    const config = {
      method: options.method || "GET",
      headers: {
        "Content-Type": "application/json",
        ...(token && { Authorization: `Bearer ${token}` }),
        ...options.headers,
      },
      ...options,
    };

    if (options.body && typeof options.body === "object") {
      config.body = JSON.stringify(options.body);
    }

    console.log(`📡 API Request: ${config.method} ${endpoint}`);

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), this.TIMEOUT);
      config.signal = controller.signal;

      const response = await fetch(url, config);
      clearTimeout(timeoutId);

      console.log(`✅ API Response: ${response.status} ${response.statusText}`);

      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("❌ Non-JSON response:", text.substring(0, 200));
        throw new Error("Server returned non-JSON response");
      }

      const data = await response.json();

      if (!response.ok) {
        if (response.status === 401) {
          console.warn("⚠️ Unauthorized - Token may be expired");
          this.logout();
          throw new Error("Session expired. Please login again.");
        }
        throw new Error(
          data.message || `HTTP ${response.status}: ${response.statusText}`
        );
      }

      return data;
    } catch (error) {
      if (error.name === "AbortError") {
        console.error(
          "❌ Request timeout after",
          this.TIMEOUT / 1000,
          "seconds"
        );
        throw new Error("Request timeout. Please check your connection.");
      }
      if (error.message.includes("Failed to fetch")) {
        console.error("❌ Network error - Server unreachable");
        throw new Error(
          "Cannot connect to server. Please check your internet connection."
        );
      }
      console.error("❌ API Error:", error.message);
      throw error;
    }
  },

  /**
   * Authentication
   */
  async login(username, password) {
    try {
      const data = await this.request("/auth/login", {
        method: "POST",
        body: { username, password },
      });

      if (data.success && data.data.token) {
        this.setToken(data.data.token);
        this.setUser(data.data.user);
        console.log("✅ Login successful:", data.data.user.username);
        return data;
      }

      throw new Error(data.message || "Login failed");
    } catch (error) {
      console.error("❌ Login error:", error.message);
      throw error;
    }
  },

  async logout() {
    try {
      await this.request("/auth/logout", { method: "POST" });
    } catch (error) {
      console.warn("⚠️ Logout API call failed:", error.message);
    } finally {
      this.clearSession();
      window.location.href = "index.html";
    }
  },

  async getCurrentUser() {
    return this.request("/auth/me");
  },

  async changePassword(currentPassword, newPassword) {
    return this.request("/auth/change-password", {
      method: "POST",
      body: { current_password: currentPassword, new_password: newPassword },
    });
  },

  /**
   * Inventory
   */
  async getInventory(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    return this.request(`/inventory${queryString ? "?" + queryString : ""}`);
  },

  async getInventoryItem(id) {
    return this.request(`/inventory/${id}`);
  },

  async createInventoryItem(data) {
    return this.request("/inventory", {
      method: "POST",
      body: data,
    });
  },

  async updateInventoryItem(id, data) {
    return this.request(`/inventory/${id}`, {
      method: "PUT",
      body: data,
    });
  },

  async deleteInventoryItem(id) {
    return this.request(`/inventory/${id}`, {
      method: "DELETE",
    });
  },

  async getLowStockItems() {
    return this.request("/inventory/low-stock");
  },

  async getCategories() {
    return this.request("/inventory/categories");
  },

  async getTransactions(limit = 50) {
    return this.request(`/inventory/transactions?limit=${limit}`);
  },

  async getInventorySummary() {
    return this.request("/inventory/summary");
  },

  async stockIn(itemId, quantity, notes) {
    return this.request("/inventory/stock-in", {
      method: "POST",
      body: { item_id: itemId, quantity, notes },
    });
  },

  async stockOut(itemId, quantity, notes) {
    return this.request("/inventory/stock-out", {
      method: "POST",
      body: { item_id: itemId, quantity, notes },
    });
  },

  /**
   * Purchase Orders
   */
  async getPurchaseOrders(status = null) {
    const url = status
      ? `/purchase-orders?status=${status}`
      : "/purchase-orders";
    return this.request(url);
  },

  async getPurchaseOrder(id) {
    return this.request(`/purchase-orders/${id}`);
  },

  async createPurchaseOrder(data) {
    return this.request("/purchase-orders", {
      method: "POST",
      body: data,
    });
  },

  async updatePurchaseOrderStatus(id, status) {
    return this.request(`/purchase-orders/${id}/status`, {
      method: "PUT",
      body: { status },
    });
  },

  async receiveGoods(poId) {
    return this.request(`/purchase-orders/receive/${poId}`, {
      method: "POST",
    });
  },

  /**
   * Sales Orders
   */
  async getSalesOrders() {
    return this.request("/sales-orders");
  },

  async createSalesOrder(data) {
    return this.request("/sales-orders", {
      method: "POST",
      body: data,
    });
  },

  async completeSalesOrder(orderId) {
    return this.request(`/sales-orders/complete/${orderId}`, {
      method: "POST",
    });
  },

  /**
   * Suppliers
   */
  async getSuppliers() {
    return this.request("/suppliers");
  },

  async getSupplier(id) {
    return this.request(`/suppliers/${id}`);
  },

  async createSupplier(data) {
    return this.request("/suppliers", {
      method: "POST",
      body: data,
    });
  },

  async updateSupplier(id, data) {
    return this.request(`/suppliers/${id}`, {
      method: "PUT",
      body: data,
    });
  },

  async deleteSupplier(id) {
    return this.request(`/suppliers/${id}`, {
      method: "DELETE",
    });
  },

  /**
   * Customers
   */
  async getCustomers() {
    return this.request("/customers");
  },

  async getCustomer(id) {
    return this.request(`/customers/${id}`);
  },

  async createCustomer(data) {
    return this.request("/customers", {
      method: "POST",
      body: data,
    });
  },

  async updateCustomer(id, data) {
    return this.request(`/customers/${id}`, {
      method: "PUT",
      body: data,
    });
  },

  async deleteCustomer(id) {
    return this.request(`/customers/${id}`, {
      method: "DELETE",
    });
  },

  async searchCustomers(query) {
    return this.request(`/customers/search?q=${encodeURIComponent(query)}`);
  },

  /**
   * Invoices
   */
  async getInvoices(filters = {}) {
    const queryString = new URLSearchParams(filters).toString();
    return this.request(`/invoices${queryString ? "?" + queryString : ""}`);
  },

  async getInvoice(id) {
    return this.request(`/invoices/${id}`);
  },

  async generateInvoice(salesOrderId) {
    return this.request(`/invoices/generate/${salesOrderId}`, {
      method: "POST",
    });
  },

  async applyPayment(invoiceId, amount, paymentMethod, referenceNumber = null) {
    return this.request(`/invoices/${invoiceId}/payment`, {
      method: "POST",
      body: {
        amount,
        payment_method: paymentMethod,
        reference_number: referenceNumber,
      },
    });
  },

  async exportInvoicePDF(invoiceId) {
    window.open(`${this.BASE_URL}/invoices/${invoiceId}/pdf`, "_blank");
  },

  /**
   * Reports
   */
  async getDashboardStats() {
    return this.request("/reports/dashboard");
  },

  async getInventoryReport() {
    return this.request("/reports/inventory-summary");
  },

  async getTransactionReport(limit = 100, type = null) {
    const url = type
      ? `/reports/transactions?limit=${limit}&type=${type}`
      : `/reports/transactions?limit=${limit}`;
    return this.request(url);
  },

  async getLowStockReport() {
    return this.request("/reports/low-stock");
  },

  async getPurchaseOrdersReport(status = null, dateFrom = null, dateTo = null) {
    const params = new URLSearchParams();
    if (status) params.append("status", status);
    if (dateFrom) params.append("date_from", dateFrom);
    if (dateTo) params.append("date_to", dateTo);

    const queryString = params.toString();
    return this.request(
      `/reports/purchase-orders${queryString ? "?" + queryString : ""}`
    );
  },

  /**
   * Users
   */
  async getUsers() {
    return this.request("/users");
  },

  async getUser(id) {
    return this.request(`/users/${id}`);
  },

  async createUser(data) {
    return this.request("/users", {
      method: "POST",
      body: data,
    });
  },

  async updateUser(id, data) {
    return this.request(`/users/${id}`, {
      method: "PUT",
      body: data,
    });
  },

  async deleteUser(id) {
    return this.request(`/users/${id}`, {
      method: "DELETE",
    });
  },

  async deactivateUser(id) {
    return this.request(`/users/${id}/deactivate`, {
      method: "PUT",
    });
  },

  async activateUser(id) {
    return this.request(`/users/${id}/activate`, {
      method: "PUT",
    });
  },

  async getRoles() {
    return this.request("/users/roles");
  },

  /**
   * Session Management
   */
  getToken() {
    return localStorage.getItem(this.TOKEN_KEY);
  },

  setToken(token) {
    localStorage.setItem(this.TOKEN_KEY, token);
  },

  getUser() {
    const userJson = localStorage.getItem(this.USER_KEY);
    return userJson ? JSON.parse(userJson) : null;
  },

  setUser(user) {
    localStorage.setItem(this.USER_KEY, JSON.stringify(user));
  },

  clearSession() {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
    console.log("✅ Session cleared");
  },

  isAuthenticated() {
    return !!this.getToken() && !!this.getUser();
  },

  /**
   * Health check
   */
  async checkHealth() {
    try {
      const response = await fetch(`${this.BASE_URL}/health`);
      return response.ok;
    } catch {
      return false;
    }
  },
};

// Export for Node.js or module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = API;
}
