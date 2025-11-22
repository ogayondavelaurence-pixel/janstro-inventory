/**
 * JANSTRO INVENTORY SYSTEM - API CLIENT v6.1
 * FIXED: Added missing user endpoints, validation & stable error handling
 * Date: 2025-11-22
 */

(function (window) {
  "use strict";

  const API_BASE_URL = "http://localhost:8080/janstro-inventory/public";
  const TOKEN_KEY = "janstro_token";
  const USER_KEY = "janstro_user";
  const REFRESH_KEY = "janstro_refresh";

  // ============================================
  // STORAGE MANAGER
  // ============================================
  const Storage = {
    set: function (key, value) {
      try {
        if (key === TOKEN_KEY || key === REFRESH_KEY) {
          localStorage.setItem(key, value);
          sessionStorage.setItem(key, value);
        } else {
          const json = JSON.stringify(value);
          localStorage.setItem(key, json);
          sessionStorage.setItem(key, json);
        }
        return true;
      } catch (e) {
        console.error("Storage.set error:", e);
        return false;
      }
    },

    get: function (key) {
      try {
        let value = localStorage.getItem(key) || sessionStorage.getItem(key);
        if (!value) return null;

        if (key === TOKEN_KEY || key === REFRESH_KEY) return value;
        return JSON.parse(value);
      } catch (e) {
        localStorage.removeItem(key);
        sessionStorage.removeItem(key);
        return null;
      }
    },

    remove: function (key) {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    },

    clear: function () {
      [TOKEN_KEY, USER_KEY, REFRESH_KEY].forEach((k) => {
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
    login: async function (username, password) {
      try {
        const res = await this.post("/auth/login", { username, password });

        if (res && res.success && res.data) {
          const { token, refresh_token, user } = res.data;

          Storage.set(TOKEN_KEY, token);
          if (refresh_token) Storage.set(REFRESH_KEY, refresh_token);

          Storage.set(USER_KEY, {
            user_id: user.user_id,
            username: user.username,
            name: user.name,
            role_id: user.role_id,
            role: user.role_name,
            role_name: user.role_name,
            permissions: user.permissions || [],
          });

          return res;
        }

        return null;
      } catch (e) {
        console.error("Login error:", e);
        return null;
      }
    },

    logout: async function () {
      try {
        await this.post("/auth/logout", {});
      } catch (e) {}

      Storage.clear();
      window.location.href = "/janstro-inventory/frontend/login.html";
    },

    isAuthenticated: function () {
      return !!(this.getToken() && Storage.get(USER_KEY));
    },

    getToken: function () {
      return Storage.get(TOKEN_KEY);
    },

    getUserFromStorage: function () {
      return Storage.get(USER_KEY);
    },

    // ============================
    // BASE REQUEST HANDLER
    // ============================
    request: async function (endpoint, options = {}) {
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
          console.warn("⚠ Session expired or unauthorized");
          Storage.clear();
          window.location.href = "/janstro-inventory/frontend/login.html";
          return null;
        }

        if (!response.ok) {
          const error = new Error("Request failed");
          error.status = response.status;
          throw error;
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
    // INVENTORY
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
    getStockMovements() {
      return this.get("/inventory/movements");
    },
    checkStock(itemId) {
      return this.get("/inventory/check-stock", { item_id: itemId });
    },

    // ============================================
    // PURCHASE ORDERS
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
    receiveGoods(poId, data) {
      return this.post(`/purchase-orders/receive/${poId}`, data);
    },

    // ============================================
    // SALES ORDERS
    // ============================================
    getSalesOrders() {
      return this.get("/sales-orders");
    },
    createSalesOrder(data) {
      return this.post("/sales-orders", data);
    },
    processInvoice(soId, data) {
      return this.post(`/sales-orders/invoice/${soId}`, data);
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

    // ============================================
    // USERS (FULLY FIXED)
    // ============================================

    // NEW: Fetch the current logged-in user (via session)
    async getCurrentUser() {
      try {
        const res = await this.get("/users/current");
        if (!res) throw new Error("Failed to fetch current user");
        return res;
      } catch (error) {
        console.error("❌ getCurrentUser failed:", error);

        if (error.status === 401 || error.status === 419) {
          Storage.clear();
          window.location.href = "/janstro-inventory/frontend/login.html";
        }
        throw error;
      }
    },

    async getUser(id) {
      if (!id || isNaN(Number(id))) {
        throw new Error(`Invalid user ID: ${id}`);
      }
      return this.get(`/users/${id}`);
    },

    getUsers() {
      return this.get("/users");
    },

    createUser(data) {
      return this.post("/users", data);
    },

    updateUser(id, data) {
      if (!id || isNaN(Number(id))) throw new Error("Invalid user ID");
      return this.put(`/users/${id}`, data);
    },

    deleteUser(id) {
      if (!id || isNaN(Number(id))) throw new Error("Invalid user ID");
      return this.delete(`/users/${id}`);
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
  console.log("✅ API Module v6.1 Loaded");
})(window);
