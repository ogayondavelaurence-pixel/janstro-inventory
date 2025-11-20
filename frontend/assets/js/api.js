/**
 * JANSTRO INVENTORY SYSTEM - API CLIENT v4.0
 * FIXED: Token persistence, CORS, error handling
 * Date: 2025-11-20
 */

(function (window) {
  "use strict";

  const API_BASE_URL = "http://localhost:8080/janstro-inventory/public";
  const TOKEN_KEY = "janstro_token";
  const USER_KEY = "janstro_user";

  // ============================================
  // STORAGE MANAGER (Session + Local Storage)
  // ============================================
  const Storage = {
    set(key, value) {
      try {
        const data = JSON.stringify(value);
        localStorage.setItem(key, data);
        sessionStorage.setItem(key, data); // Backup in session
        return true;
      } catch (error) {
        console.error("Storage.set error:", error);
        return false;
      }
    },

    get(key) {
      try {
        // Try localStorage first
        let data = localStorage.getItem(key);
        if (!data) {
          // Fallback to sessionStorage
          data = sessionStorage.getItem(key);
        }
        return data ? JSON.parse(data) : null;
      } catch (error) {
        console.error("Storage.get error:", error);
        return null;
      }
    },

    remove(key) {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    },

    clear() {
      localStorage.clear();
      sessionStorage.clear();
    },
  };

  // ============================================
  // API CLIENT
  // ============================================
  const API = {
    // Authentication
    async login(username, password) {
      try {
        console.log("🔐 Login attempt:", username);

        const response = await this.post("/auth/login", {
          username,
          password,
        });

        console.log("📥 Login response:", response);

        if (response && response.success && response.data) {
          const { token, user } = response.data;

          if (!token || !user) {
            console.error("❌ Missing token or user in response");
            return null;
          }

          // CRITICAL: Store token and user IMMEDIATELY
          const tokenStored = Storage.set(TOKEN_KEY, token);
          const userStored = Storage.set(USER_KEY, user);

          console.log("💾 Token stored:", tokenStored);
          console.log("💾 User stored:", userStored);

          // VERIFY storage worked
          const verifyToken = Storage.get(TOKEN_KEY);
          const verifyUser = Storage.get(USER_KEY);

          if (!verifyToken || !verifyUser) {
            console.error("❌ Storage verification failed");
            return null;
          }

          console.log("✅ Login successful, data persisted");
          return response;
        }

        console.error("❌ Login failed:", response);
        return null;
      } catch (error) {
        console.error("❌ Login error:", error);
        throw error;
      }
    },

    logout() {
      Storage.clear();
      window.location.href = "index.html";
    },

    isAuthenticated() {
      const token = Storage.get(TOKEN_KEY);
      const user = Storage.get(USER_KEY);
      const isAuth = !!(token && user);
      console.log("🔒 isAuthenticated:", isAuth, {
        token: !!token,
        user: !!user,
      });
      return isAuth;
    },

    getToken() {
      return Storage.get(TOKEN_KEY);
    },

    getUser() {
      return Storage.get(USER_KEY);
    },

    // HTTP Methods
    async request(endpoint, options = {}) {
      const url = `${API_BASE_URL}${endpoint}`;
      const token = this.getToken();

      const config = {
        method: options.method || "GET",
        headers: {
          "Content-Type": "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...options.headers,
        },
        ...options,
      };

      if (options.body && typeof options.body === "object") {
        config.body = JSON.stringify(options.body);
      }

      try {
        const response = await fetch(url, config);
        const data = await response.json();

        // Handle 401 Unauthorized
        if (response.status === 401) {
          console.warn("⚠️ 401 Unauthorized - clearing session");
          Storage.clear();
          if (
            window.location.pathname !==
            "/janstro-inventory/frontend/index.html"
          ) {
            window.location.href = "index.html";
          }
          return null;
        }

        return data;
      } catch (error) {
        console.error("API request error:", error);
        throw error;
      }
    },

    get(endpoint) {
      return this.request(endpoint, { method: "GET" });
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

    // Inventory Endpoints
    getInventory() {
      return this.get("/inventory");
    },

    getItem(id) {
      return this.get(`/inventory/${id}`);
    },

    getInventoryStatus() {
      return this.get("/inventory/status");
    },

    checkStock(itemId) {
      return this.get(`/inventory/check-stock?item_id=${itemId}`);
    },

    getLowStockItems() {
      return this.get("/inventory/low-stock");
    },

    getStockMovements() {
      return this.get("/inventory/movements");
    },

    // Purchase Orders
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

    // Sales Orders
    getSalesOrders() {
      return this.get("/sales-orders");
    },

    createSalesOrder(data) {
      return this.post("/sales-orders", data);
    },

    processInvoice(salesOrderId, data) {
      return this.post(`/sales-orders/invoice/${salesOrderId}`, data);
    },

    // Suppliers
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

    // Users
    getUsers() {
      return this.get("/users");
    },

    createUser(data) {
      return this.post("/users", data);
    },

    updateUser(id, data) {
      return this.put(`/users/${id}`, data);
    },

    deactivateUser(id) {
      return this.put(`/users/${id}`, { status: "inactive" });
    },
  };

  // Make API globally available
  window.API = API;
  console.log("✅ API Module v4.0 Loaded");
})(window);
