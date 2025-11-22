/**
 * JANSTRO IMS - Complete API Client v7.0
 * Connects frontend to src/ backend with all endpoints
 * Date: 2025-11-22
 */

(function (window) {
  "use strict";

  const API_BASE_URL = "http://localhost:8080/janstro-inventory/public";
  const TOKEN_KEY = "janstro_token";
  const USER_KEY = "janstro_user";

  // ============================================
  // STORAGE MANAGER
  // ============================================
  const Storage = {
    set(key, value) {
      try {
        const json = typeof value === "string" ? value : JSON.stringify(value);
        localStorage.setItem(key, json);
        sessionStorage.setItem(key, json);
        return true;
      } catch (e) {
        console.error("Storage.set error:", e);
        return false;
      }
    },

    get(key) {
      try {
        let value = localStorage.getItem(key) || sessionStorage.getItem(key);
        if (!value) return null;

        if (key === TOKEN_KEY) return value;

        try {
          return JSON.parse(value);
        } catch {
          return value;
        }
      } catch (e) {
        console.error("Storage.get error:", e);
        return null;
      }
    },

    remove(key) {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    },

    clear() {
      [TOKEN_KEY, USER_KEY].forEach((k) => {
        localStorage.removeItem(k);
        sessionStorage.removeItem(k);
      });
    },
  };

  // ============================================
  // API MODULE
  // ============================================
  const API = {
    // ============================
    // AUTH
    // ============================
    async login(username, password) {
      try {
        const res = await this.post("/auth/login", { username, password });

        if (res?.success && res?.data) {
          const { token, user } = res.data;

          Storage.set(TOKEN_KEY, token);
          Storage.set(USER_KEY, {
            user_id: user.user_id,
            username: user.username,
            name: user.name,
            role_id: user.role_id,
            role: user.role_name,
            role_name: user.role_name,
          });

          return res;
        }
        return null;
      } catch (e) {
        console.error("Login error:", e);
        return null;
      }
    },

    async logout() {
      try {
        await this.post("/auth/logout", {});
      } catch (e) {}

      Storage.clear();
      window.location.href = "/janstro-inventory/frontend/index.html";
    },

    isAuthenticated() {
      return !!(this.getToken() && Storage.get(USER_KEY));
    },

    getToken() {
      return Storage.get(TOKEN_KEY);
    },

    getUserFromStorage() {
      return Storage.get(USER_KEY);
    },

    // ✅ GET CURRENT USER FROM BACKEND SESSION
    async getCurrentUser() {
      return this.get("/users/current");
    },

    // ============================
    // BASE REQUEST HANDLER
    // ============================
    async request(endpoint, options = {}) {
      const url = `${API_BASE_URL}${endpoint}`;
      const token = this.getToken();

      const config = {
        method: options.method || "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: "include",
        mode: "cors",
      };

      if (options.body) config.body = JSON.stringify(options.body);

      try {
        const response = await fetch(url, config);

        if (response.status === 401 || response.status === 419) {
          console.warn("⚠ Session expired");
          Storage.clear();
          window.location.href = "/janstro-inventory/frontend/index.html";
          return null;
        }

        if (!response.ok) {
          throw new Error(`Request failed: ${response.status}`);
        }

        return await response.json();
      } catch (e) {
        console.error(`❌ Request failed (${endpoint}):`, e);
        throw e;
      }
    },

    get(endpoint, params = {}) {
      const qs = new URLSearchParams(params).toString();
      return this.request(qs ? `${endpoint}?${qs}` : endpoint, {
        method: "GET",
      });
    },

    post(endpoint, body) {
      return this.request(endpoint, { method: "POST", body });
    },

    put(endpoint, body) {
      return this.request(endpoint, { method: "PUT", body });
    },

    delete(endpoint) {
      return this.request(endpoint, { method: "DELETE" });
    },

    // ============================================
    // INVENTORY (MMBE / Material Master)
    // ============================================
    getInventory() {
      return this.get("/inventory");
    },

    getItem(id) {
      return this.get(`/inventory/${id}`);
    },

    getInventoryStatus() {
      return this.get("/inventory/status");
    },

    getLowStockItems() {
      return this.get("/inventory/low-stock");
    },

    checkStock(itemId) {
      return this.get("/inventory/check-stock", { item_id: itemId });
    },

    createItem(data) {
      return this.post("/inventory", data);
    },

    updateItem(id, data) {
      return this.put(`/inventory/${id}`, data);
    },

    deleteItem(id) {
      return this.delete(`/inventory/${id}`);
    },

    // ============================================
    // STOCK MOVEMENTS (MB51 / Material Documents)
    // ============================================
    getStockMovements(filters = {}) {
      return this.get("/inventory/movements", filters);
    },

    // ============================================
    // PURCHASE ORDERS (ME21N)
    // ============================================
    getPurchaseOrders() {
      return this.get("/purchase-orders");
    },

    getPurchaseOrder(id) {
      return this.get(`/purchase-orders/${id}`);
    },

    createPurchaseOrder(data) {
      return this.post("/purchase-orders", data);
    },

    // GOODS RECEIPT (MIGO)
    receiveGoods(poId, data) {
      return this.post(`/purchase-orders/receive/${poId}`, data);
    },

    // ============================================
    // SALES ORDERS (VA01)
    // ============================================
    getSalesOrders() {
      return this.get("/sales-orders");
    },

    createSalesOrder(data) {
      return this.post("/sales-orders", data);
    },

    // INVOICE GENERATION (VF01)
    processInvoice(soId, userId) {
      return this.post(`/sales-orders/invoice/${soId}`, { user_id: userId });
    },

    // ============================================
    // SUPPLIERS
    // ============================================
    getSuppliers() {
      return this.get("/suppliers");
    },

    getSupplier(id) {
      return this.get(`/suppliers/${id}`);
    },

    createSupplier(data) {
      return this.post("/suppliers", data);
    },

    updateSupplier(id, data) {
      return this.put(`/suppliers/${id}`, data);
    },

    deleteSupplier(id) {
      return this.delete(`/suppliers/${id}`);
    },

    // ============================================
    // CUSTOMERS (NEW)
    // ============================================
    getCustomers() {
      return this.get("/customers");
    },

    getCustomer(id) {
      return this.get(`/customers/${id}`);
    },

    createCustomer(data) {
      return this.post("/customers", data);
    },

    // ============================================
    // USERS
    // ============================================
    getUsers() {
      return this.get("/users");
    },

    getUser(id) {
      return this.get(`/users/${id}`);
    },

    createUser(data) {
      return this.post("/users", data);
    },

    updateUser(id, data) {
      return this.put(`/users/${id}`, data);
    },

    deleteUser(id) {
      return this.delete(`/users/${id}`);
    },

    getRoles() {
      return this.get("/users/roles");
    },

    // ============================================
    // REPORTS & ANALYTICS
    // ============================================
    getDashboardStats() {
      return this.get("/reports/dashboard");
    },

    getInventorySummary() {
      return this.get("/reports/inventory-summary");
    },

    getTransactionHistory(limit = 50) {
      return this.get("/reports/transactions", { limit });
    },

    // ============================================
    // HEALTH CHECK
    // ============================================
    async checkHealth() {
      try {
        const res = await fetch(`${API_BASE_URL}/health`);
        return (await res.json()).success;
      } catch (e) {
        return false;
      }
    },
  };

  window.API = API;
  console.log("✅ API Module v7.0 Loaded - Connected to src/ backend");
})(window);
