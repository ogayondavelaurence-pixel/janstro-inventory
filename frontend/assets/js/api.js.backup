/**
 * Janstro Inventory System - API Wrapper
 * Handles all API calls with JWT authentication
 * Version: 2.0.0
 */

const API = {
  // Auto-detect base URL
  baseURL: window.location.origin + "/janstro-inventory/public",
  tokenKey: "janstro_token",
  userKey: "janstro_user",

  /**
   * Get stored JWT token
   */
  getToken() {
    return localStorage.getItem(this.tokenKey);
  },

  /**
   * Store JWT token
   */
  setToken(token) {
    localStorage.setItem(this.tokenKey, token);
  },

  /**
   * Remove JWT token (logout)
   */
  removeToken() {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  },

  /**
   * Get stored user data
   */
  getUser() {
    const userJson = localStorage.getItem(this.userKey);
    return userJson ? JSON.parse(userJson) : null;
  },

  /**
   * Store user data
   */
  setUser(user) {
    localStorage.setItem(this.userKey, JSON.stringify(user));
  },

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return !!this.getToken();
  },

  /**
   * Make HTTP request with JWT token
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;

    const config = {
      headers: {
        "Content-Type": "application/json",
        ...options.headers,
      },
      ...options,
    };

    // Add JWT token if available
    const token = this.getToken();
    if (token) {
      config.headers["Authorization"] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      // Handle token expiration
      if (response.status === 401) {
        this.removeToken();
        window.location.href = "/janstro-inventory/frontend/index.html";
        throw new Error("Session expired. Please login again.");
      }

      return data;
    } catch (error) {
      console.error("API Error:", error);
      throw error;
    }
  },

  /**
   * GET request
   */
  async get(endpoint) {
    return this.request(endpoint, {
      method: "GET",
    });
  },

  /**
   * POST request
   */
  async post(endpoint, data) {
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  /**
   * PUT request
   */
  async put(endpoint, data) {
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },

  /**
   * DELETE request
   */
  async delete(endpoint) {
    return this.request(endpoint, {
      method: "DELETE",
    });
  },

  // ============================================
  // AUTHENTICATION ENDPOINTS
  // ============================================

  /**
   * Login user
   */
  async login(username, password) {
    const response = await this.post("/auth/login", { username, password });

    if (response.success) {
      this.setToken(response.data.token);
      this.setUser(response.data.user);
    }

    return response;
  },

  /**
   * Logout user
   */
  logout() {
    this.removeToken();
    window.location.href = "/janstro-inventory/frontend/index.html";
  },

  /**
   * Get current user profile
   */
  async getCurrentUser() {
    return this.get("/auth/me");
  },

  // ============================================
  // INVENTORY ENDPOINTS (SAP: MMBE)
  // ============================================

  /**
   * Get all inventory items
   */
  async getInventory() {
    return this.get("/inventory");
  },

  /**
   * Get single item details
   */
  async getItem(itemId) {
    return this.get(`/inventory/${itemId}`);
  },

  /**
   * Get inventory status/dashboard stats
   */
  async getInventoryStatus() {
    return this.get("/inventory/status");
  },

  /**
   * Check stock availability (SAP: MMBE)
   */
  async checkStock(itemId) {
    return this.get(`/inventory/check-stock?item_id=${itemId}`);
  },

  /**
   * Get low stock items
   */
  async getLowStockItems() {
    return this.get("/inventory/low-stock");
  },

  /**
   * Get stock movements (SAP: MB51)
   */
  async getStockMovements(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/inventory/movements?${params}`);
  },

  // ============================================
  // PURCHASE ORDER ENDPOINTS (SAP: ME21N, MIGO)
  // ============================================

  /**
   * Get all purchase orders
   */
  async getPurchaseOrders() {
    return this.get("/purchase-orders");
  },

  /**
   * Get single purchase order
   */
  async getPurchaseOrder(poId) {
    return this.get(`/purchase-orders/${poId}`);
  },

  /**
   * Create purchase order (SAP: ME21N)
   */
  async createPurchaseOrder(data) {
    return this.post("/purchase-orders", data);
  },

  /**
   * Receive goods from PO (SAP: MIGO - Stock IN)
   */
  async receiveGoods(poId, data) {
    return this.post(`/purchase-orders/receive/${poId}`, data);
  },

  /**
   * Get pending purchase orders
   */
  async getPendingPurchaseOrders() {
    return this.get("/purchase-orders?status=pending");
  },

  // ============================================
  // SALES ORDER ENDPOINTS (SAP: VA01, VF01)
  // ============================================

  /**
   * Get all sales orders
   */
  async getSalesOrders() {
    return this.get("/sales-orders");
  },

  /**
   * Create sales order (SAP: VA01) - Simple single-item order
   */
  async createSalesOrder(data) {
    return this.post("/sales-orders", data);
  },

  /**
   * Process invoice (SAP: VF01 - Stock OUT)
   */
  async processInvoice(salesOrderId, data) {
    return this.post(`/sales-orders/invoice/${salesOrderId}`, data);
  },

  // ============================================
  // SUPPLIER ENDPOINTS
  // ============================================

  /**
   * Get all suppliers
   */
  async getSuppliers() {
    return this.get("/suppliers");
  },

  /**
   * Get single supplier
   */
  async getSupplier(supplierId) {
    return this.get(`/suppliers/${supplierId}`);
  },

  /**
   * Create supplier
   */
  async createSupplier(data) {
    return this.post("/suppliers", data);
  },

  /**
   * Update supplier
   */
  async updateSupplier(supplierId, data) {
    return this.put(`/suppliers/${supplierId}`, data);
  },

  // ============================================
  // USER MANAGEMENT ENDPOINTS
  // ============================================

  /**
   * Get all users (Admin only)
   */
  async getUsers() {
    return this.get("/users");
  },

  /**
   * Create user (Admin only)
   */
  async createUser(data) {
    return this.post("/users", data);
  },

  /**
   * Update user (Admin only)
   */
  async updateUser(userId, data) {
    return this.put(`/users/${userId}`, data);
  },

  /**
   * Deactivate user (Admin only)
   */
  async deactivateUser(userId) {
    return this.delete(`/users/${userId}`);
  },

  // ============================================
  // REPORTS ENDPOINTS
  // ============================================

  /**
   * Get dashboard report
   */
  async getDashboardReport() {
    return this.get("/reports/dashboard");
  },

  /**
   * Get inventory report
   */
  async getInventoryReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/inventory?${params}`);
  },

  /**
   * Get stock movement report (SAP: MB51)
   */
  async getStockMovementReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/stock-movements?${params}`);
  },

  /**
   * Get sales report
   */
  async getSalesReport(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.get(`/reports/sales?${params}`);
  },

  // ============================================
  // HEALTH CHECK
  // ============================================

  /**
   * Check API health
   */
  async healthCheck() {
    return this.get("/health");
  },
};

// Make API available globally
window.API = API;
