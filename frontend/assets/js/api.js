/**
 * Janstro Inventory System - API Wrapper
 * Handles all API calls with JWT authentication
 * Version: 2.1.0 (Frontend Fixes + Missing Endpoints Completed)
 */

const API = {
  baseURL: window.location.origin + ":8080/janstro-inventory/public",
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
  // SALES ORDERS — FIXED & COMPLETED
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

window.API = API;
