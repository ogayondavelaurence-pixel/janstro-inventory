/**
 * JANSTRO INVENTORY SYSTEM - API CLIENT v6.0
 * FIXED: Added all missing API methods
 * Date: 2025-11-21
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
          const jsonValue = JSON.stringify(value);
          localStorage.setItem(key, jsonValue);
          sessionStorage.setItem(key, jsonValue);
        }
        return true;
      } catch (e) {
        console.error(`Storage.set error:`, e);
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
    // AUTH
    login: async function (username, password) {
      console.log(`🔐 Login attempt: ${username}`);
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
      window.location.href = "/janstro-inventory/frontend/index.html";
    },
    isAuthenticated: function () {
      return !!(this.getToken() && Storage.get(USER_KEY));
    },
    getToken: function () {
      return Storage.get(TOKEN_KEY);
    },
    getUser: function () {
      return Storage.get(USER_KEY);
    },

    // HTTP METHODS
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
        if (response.status === 401) {
          console.warn("401 Unauthorized");
          Storage.clear();
          if (!endpoint.includes("/auth/login")) {
            window.location.href = "/janstro-inventory/frontend/index.html";
          }
          return null;
        }
        if (!response.ok) return null;
        return await response.json();
      } catch (e) {
        console.error(`Request failed [${endpoint}]:`, e);
        return null;
      }
    },
    get: function (endpoint, params = {}) {
      const qs = new URLSearchParams(params).toString();
      return this.request(qs ? `${endpoint}?${qs}` : endpoint, {
        method: "GET",
      });
    },
    post: function (endpoint, body) {
      return this.request(endpoint, { method: "POST", body });
    },
    put: function (endpoint, body) {
      return this.request(endpoint, { method: "PUT", body });
    },
    delete: function (endpoint) {
      return this.request(endpoint, { method: "DELETE" });
    },

    // ============================================
    // INVENTORY METHODS (MISSING IN ORIGINAL)
    // ============================================
    getInventory: function () {
      return this.get("/inventory");
    },
    getItem: function (id) {
      return this.get(`/inventory/${id}`);
    },
    getInventoryStatus: function () {
      return this.get("/inventory/status");
    },
    getLowStockItems: function () {
      return this.get("/inventory/low-stock");
    },
    getStockMovements: function () {
      return this.get("/inventory/movements");
    },
    checkStock: function (itemId) {
      return this.get("/inventory/check-stock", { item_id: itemId });
    },

    // ============================================
    // PURCHASE ORDERS
    // ============================================
    getPurchaseOrders: function () {
      return this.get("/purchase-orders");
    },
    getPurchaseOrder: function (id) {
      return this.get(`/purchase-orders/${id}`);
    },
    createPurchaseOrder: function (data) {
      return this.post("/purchase-orders", data);
    },
    receiveGoods: function (poId, data) {
      return this.post(`/purchase-orders/receive/${poId}`, data);
    },

    // ============================================
    // SALES ORDERS
    // ============================================
    getSalesOrders: function () {
      return this.get("/sales-orders");
    },
    createSalesOrder: function (data) {
      return this.post("/sales-orders", data);
    },
    processInvoice: function (soId, data) {
      return this.post(`/sales-orders/invoice/${soId}`, data);
    },

    // ============================================
    // SUPPLIERS
    // ============================================
    getSuppliers: function () {
      return this.get("/suppliers");
    },
    getSupplier: function (id) {
      return this.get(`/suppliers/${id}`);
    },
    createSupplier: function (data) {
      return this.post("/suppliers", data);
    },
    updateSupplier: function (id, data) {
      return this.put(`/suppliers/${id}`, data);
    },

    // ============================================
    // USERS
    // ============================================
    getUsers: function () {
      return this.get("/users");
    },
    getUser: function (id) {
      return this.get(`/users/${id}`);
    },
    createUser: function (data) {
      return this.post("/users", data);
    },
    updateUser: function (id, data) {
      return this.put(`/users/${id}`, data);
    },
    deactivateUser: function (id) {
      return this.put(`/users/${id}`, { status: "inactive" });
    },

    // HEALTH CHECK
    checkHealth: async function () {
      try {
        const res = await fetch(`${API_BASE_URL}/health`);
        return (await res.json()).success;
      } catch (e) {
        return false;
      }
    },
  };

  window.API = API;
  console.log("✅ API Module v6.0 Loaded");
})(window);
