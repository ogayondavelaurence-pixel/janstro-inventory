/**
 * ============================================================================
 * JANSTRO IMS - API CLIENT v4.1 (COMPLETE WITH PR METHODS)
 * ============================================================================
 * Path: frontend/assets/js/api-client.js
 * REPLACE YOUR ENTIRE api-client.js FILE WITH THIS CODE
 * ============================================================================
 */

(function (window) {
  "use strict";

  const API = {
    // ========================================================================
    // CONFIGURATION
    // ========================================================================
    baseURL: "http://localhost:8080/janstro-inventory/public",
    token: null,
    maxRetries: 3,
    retryDelay: 1000,
    requestQueue: [],
    isProcessingQueue: false,

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    init() {
      this.token = this.getToken();
      console.log("✅ API Client v4.1 Complete Initialized");
      console.log("🔗 Base URL:", this.baseURL);
      console.log("🎟️ Token Status:", this.token ? "Present" : "Missing");

      this.setupTokenRefresh();
    },

    // ========================================================================
    // TOKEN MANAGEMENT
    // ========================================================================

    getToken() {
      return (
        localStorage.getItem("janstro_token") ||
        sessionStorage.getItem("janstro_token") ||
        localStorage.getItem("auth_token") ||
        null
      );
    },

    saveToken(token) {
      if (!token) {
        console.error("❌ Cannot save empty token");
        return false;
      }

      localStorage.setItem("janstro_token", token);
      localStorage.setItem("auth_token", token);
      sessionStorage.setItem("janstro_token", token);
      this.token = token;

      console.log("✅ Token saved successfully");
      return true;
    },

    clearToken() {
      localStorage.removeItem("janstro_token");
      localStorage.removeItem("auth_token");
      sessionStorage.removeItem("janstro_token");
      localStorage.removeItem("janstro_user");
      this.token = null;
      console.log("🗑️ All tokens cleared");
    },

    setupTokenRefresh() {
      setInterval(() => {
        if (this.isAuthenticated()) {
          console.log("🔄 Token refresh check...");
          this.token = this.getToken();
        }
      }, 1800000);
    },

    // ========================================================================
    // HTTP REQUEST HELPERS
    // ========================================================================

    getHeaders() {
      const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
      };

      const currentToken = this.getToken();
      if (currentToken) {
        headers["Authorization"] = `Bearer ${currentToken}`;
      }

      return headers;
    },

    async request(endpoint, options = {}) {
      const url = `${this.baseURL}/${endpoint}`;
      let lastError;

      for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
        try {
          const config = {
            method: options.method || "GET",
            headers: this.getHeaders(),
            ...options,
          };

          if (options.body && typeof options.body === "object") {
            config.body = JSON.stringify(options.body);
          }

          console.log(
            `📡 ${config.method} ${endpoint} (attempt ${attempt + 1}/${
              this.maxRetries + 1
            })`
          );

          const response = await fetch(url, config);

          if (response.status === 401) {
            console.error("❌ 401 Unauthorized");
            this.clearToken();

            if (!endpoint.includes("auth/login")) {
              window.location.href = "index.html";
            }
            throw new Error("Authentication required");
          }

          if (response.status === 403) {
            console.error("❌ 403 Forbidden");
            throw new Error("Access denied");
          }

          const data = await response.json();

          if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`);
          }

          console.log(`✅ Success: ${endpoint}`);
          return data;
        } catch (error) {
          lastError = error;
          console.warn(`⚠️ Attempt ${attempt + 1} failed: ${error.message}`);

          if (
            error.message.includes("Authentication") ||
            error.message.includes("Access denied")
          ) {
            throw error;
          }

          if (attempt < this.maxRetries) {
            const delay = this.retryDelay * Math.pow(2, attempt);
            console.log(`⏳ Retrying in ${delay}ms...`);
            await new Promise((resolve) => setTimeout(resolve, delay));
          }
        }
      }

      console.error(`❌ Failed after ${this.maxRetries + 1} attempts`);
      throw lastError;
    },

    // ========================================================================
    // AUTHENTICATION ENDPOINTS
    // ========================================================================

    async login(username, password) {
      try {
        const response = await this.request("auth/login", {
          method: "POST",
          body: { username, password },
        });

        console.log("🔍 Login response:", response);

        let token = null;
        let userData = null;

        if (response.success && response.data) {
          token = response.data.token;
          userData = response.data.user;
        } else if (response.token) {
          token = response.token;
          userData = response.user;
        }

        if (!token) {
          throw new Error("No token received from server");
        }

        this.saveToken(token);

        if (userData) {
          localStorage.setItem("janstro_user", JSON.stringify(userData));
          console.log("✅ Login successful:", userData.username);
        }

        return response;
      } catch (error) {
        console.error("❌ Login error:", error);
        this.clearToken();
        throw error;
      }
    },

    async logout() {
      try {
        await this.request("auth/logout", { method: "POST" });
      } catch (error) {
        console.warn("⚠️ Logout request failed:", error);
      } finally {
        this.clearToken();
        window.location.href = "index.html";
      }
    },

    async getCurrentUser() {
      return this.request("auth/me");
    },

    // ========================================================================
    // INVENTORY ENDPOINTS
    // ========================================================================

    async getInventory() {
      const response = await this.request("inventory");
      return this.normalizeArrayResponse(response);
    },

    async getItem(itemId) {
      return this.request(`items/${itemId}`);
    },

    async createItem(itemData) {
      return this.request("items", {
        method: "POST",
        body: itemData,
      });
    },

    async updateItem(itemId, itemData) {
      return this.request(`items/${itemId}`, {
        method: "PUT",
        body: itemData,
      });
    },

    async deleteItem(itemId) {
      return this.request(`items/${itemId}`, {
        method: "DELETE",
      });
    },

    async getInventoryStatus() {
      return this.request("inventory/status");
    },

    async getLowStock() {
      return this.request("inventory/low-stock");
    },

    // ========================================================================
    // CATEGORIES
    // ========================================================================

    async getCategories() {
      const response = await this.request("categories");
      return this.normalizeArrayResponse(response);
    },

    async getCategory(categoryId) {
      return this.request(`categories/${categoryId}`);
    },

    async createCategory(categoryData) {
      return this.request("categories", {
        method: "POST",
        body: categoryData,
      });
    },

    // ========================================================================
    // SUPPLIERS
    // ========================================================================

    async getSuppliers() {
      try {
        const response = await this.request("suppliers");
        // ✅ FIX: Normalize response to always return array
        return this.normalizeArrayResponse(response);
      } catch (error) {
        console.error("❌ getSuppliers error:", error);
        throw error;
      }
    },

    async getSupplier(supplierId) {
      return this.request(`suppliers/${supplierId}`);
    },

    async createSupplier(supplierData) {
      return this.request("suppliers", {
        method: "POST",
        body: supplierData,
      });
    },

    async updateSupplier(supplierId, supplierData) {
      return this.request(`suppliers/${supplierId}`, {
        method: "PUT",
        body: supplierData,
      });
    },

    async deleteSupplier(supplierId) {
      return this.request(`suppliers/${supplierId}`, {
        method: "DELETE",
      });
    },

    // ========================================================================
    // PURCHASE ORDERS
    // ========================================================================

    async getPurchaseOrders() {
      const response = await this.request("purchase-orders");
      return this.normalizeArrayResponse(response);
    },

    async getPurchaseOrder(poId) {
      return this.request(`purchase-orders/${poId}`);
    },

    async createPurchaseOrder(poData) {
      return this.request("purchase-orders", {
        method: "POST",
        body: poData,
      });
    },

    async approvePurchaseOrder(poId) {
      return this.request(`purchase-orders/${poId}/approve`, {
        method: "POST",
      });
    },

    async receiveGoods(poId, data) {
      return this.request(`purchase-orders/${poId}/receive`, {
        method: "POST",
        body: data,
      });
    },
    // ========================================================================
    // SALES ORDERS
    // ========================================================================

    async getSalesOrders() {
      const response = await this.request("sales-orders");
      return this.normalizeArrayResponse(response);
    },

    async getSalesOrder(soId) {
      return this.request(`sales-orders/${soId}`);
    },

    async createSalesOrder(soData) {
      return this.request("sales-orders", {
        method: "POST",
        body: soData,
      });
    },

    async completeSalesOrder(soId) {
      return this.request(`sales-orders/${soId}/complete`, {
        method: "POST",
      });
    },

    // ========================================================================
    // INVOICES
    // ========================================================================

    async getInvoices() {
      return this.request("invoices");
    },

    async getInvoice(invoiceId) {
      return this.request(`invoices/${invoiceId}`);
    },

    async generateInvoice(salesOrderId) {
      return this.request(`invoices/generate/${salesOrderId}`, {
        method: "POST",
      });
    },

    // ========================================================================
    // CUSTOMERS
    // ========================================================================

    async getCustomers() {
      try {
        const response = await this.request("customers");
        // ✅ FIX: Normalize response to always return array
        return this.normalizeArrayResponse(response);
      } catch (error) {
        console.error("❌ getCustomers error:", error);
        throw error;
      }
    },

    async getCustomer(customerId) {
      return this.request(`customers/${customerId}`);
    },

    async createCustomer(customerData) {
      return this.request("customers", {
        method: "POST",
        body: customerData,
      });
    },

    async updateCustomer(customerId, customerData) {
      return this.request(`customers/${customerId}`, {
        method: "PUT",
        body: customerData,
      });
    },

    // ========================================================================
    // TRANSACTIONS / STOCK MOVEMENTS
    // ========================================================================

    async getStockMovements(filters = {}) {
      const params = new URLSearchParams(filters).toString();
      return this.request(`transactions${params ? "?" + params : ""}`);
    },

    async getTransactionHistory(filters = {}) {
      return this.getStockMovements(filters);
    },

    // ========================================================================
    // STOCK REQUIREMENTS
    // ========================================================================
    async getStockRequirements() {
      const response = await this.request("stock-requirements");

      console.log("🔍 Stock Requirements Raw Response:", response);

      if (
        response &&
        response.data &&
        response.data.requirements &&
        Array.isArray(response.data.requirements)
      ) {
        console.log(
          "✅ Extracted from response.data.requirements:",
          response.data.requirements.length
        );
        return response.data.requirements;
      }

      if (
        response &&
        response.requirements &&
        Array.isArray(response.requirements)
      ) {
        console.log(
          "✅ Extracted from response.requirements:",
          response.requirements.length
        );
        return response.requirements;
      }

      console.log("⚠️ Using fallback normalizer");
      return this.normalizeArrayResponse(response);
    },

    // ========================================================================
    // ✅ PURCHASE REQUISITIONS (PHASE 5) - ADDED
    // ========================================================================

    /**
     * Get all purchase requisitions
     * @returns {Promise<Object>} Object with requisitions array and summary
     */
    async getPRs() {
      try {
        const response = await this.request("purchase-requisitions");

        return {
          requisitions: response.requisitions || [],
          summary: response.summary || {
            total: 0,
            pending: 0,
            approved: 0,
            converted: 0,
          },
        };
      } catch (error) {
        console.error("❌ API.getPRs error:", error);
        throw error;
      }
    },

    /**
     * Approve a purchase requisition (ADMIN ONLY)
     * @param {number} prId - Purchase requisition ID
     * @returns {Promise<Object>} Success response
     */
    async approvePR(prId) {
      if (!prId || !Number.isInteger(prId)) {
        throw new Error("Valid PR ID required");
      }

      try {
        const response = await this.request(
          `purchase-requisitions/${prId}/approve`,
          {
            method: "POST",
          }
        );

        console.log(`✅ PR #${prId} approved`);
        return response;
      } catch (error) {
        console.error(`❌ API.approvePR(${prId}) error:`, error);
        throw error;
      }
    },

    /**
     * Reject a purchase requisition (ADMIN ONLY)
     * @param {number} prId - Purchase requisition ID
     * @param {string} reason - Rejection reason (required)
     * @returns {Promise<Object>} Success response
     */
    async rejectPR(prId, reason) {
      if (!prId || !Number.isInteger(prId)) {
        throw new Error("Valid PR ID required");
      }

      if (!reason || typeof reason !== "string" || reason.trim().length === 0) {
        throw new Error("Rejection reason is required");
      }

      try {
        const response = await this.request(
          `purchase-requisitions/${prId}/reject`,
          {
            method: "POST",
            body: { reason: reason.trim() },
          }
        );

        console.log(`✅ PR #${prId} rejected`);
        return response;
      } catch (error) {
        console.error(`❌ API.rejectPR(${prId}) error:`, error);
        throw error;
      }
    },

    /**
     * Convert PR to Purchase Order (ADMIN ONLY)
     * @param {number} prId - Purchase requisition ID
     * @param {Object} data - Conversion data
     * @param {number} data.supplier_id - Supplier ID (required)
     * @param {number} [data.unit_price] - Override unit price (optional)
     * @param {string} [data.expected_delivery_date] - Expected delivery (optional)
     * @returns {Promise<Object>} Response with new PO ID
     */
    async convertPRtoPO(prId, data) {
      if (!prId || !Number.isInteger(prId)) {
        throw new Error("Valid PR ID required");
      }

      if (!data || typeof data !== "object") {
        throw new Error("Conversion data required");
      }

      if (!data.supplier_id || !Number.isInteger(data.supplier_id)) {
        throw new Error("Valid supplier_id required in conversion data");
      }

      try {
        const response = await this.request(
          `purchase-requisitions/${prId}/convert-to-po`,
          {
            method: "POST",
            body: {
              supplier_id: data.supplier_id,
              unit_price: data.unit_price || null,
              expected_delivery_date: data.expected_delivery_date || null,
            },
          }
        );

        console.log(`✅ PR #${prId} converted to PO #${response.data?.po_id}`);
        return response;
      } catch (error) {
        console.error(`❌ API.convertPRtoPO(${prId}) error:`, error);
        throw error;
      }
    },

    // ========================================================================
    // REPORTS
    // ========================================================================

    // Dashboard can calculate stats from other APIs:
    async getDashboardStats() {
      const [inventory, pos, transactions] = await Promise.all([
        this.getInventory(),
        this.getPurchaseOrders(),
        this.getStockMovements(),
      ]);

      return {
        total_items: inventory.length,
        low_stock_items: inventory.filter((i) => i.quantity <= i.reorder_level)
          .length,
        pending_pos: pos.filter((p) => p.status === "pending").length,
        total_inventory_value: inventory.reduce(
          (sum, i) => sum + i.quantity * i.unit_price,
          0
        ),
      };
    },

    async getInventorySummary() {
      return this.request("reports/inventory-summary");
    },

    async getTransactionReport(filters = {}) {
      const params = new URLSearchParams(filters).toString();
      return this.request(`reports/transactions${params ? "?" + params : ""}`);
    },

    async getLowStockReport() {
      return this.request("reports/low-stock");
    },

    // ========================================================================
    // USERS (Superadmin only)
    // ========================================================================

    async getUsers() {
      return this.request("users");
    },

    async getUser(userId) {
      return this.request(`users/${userId}`);
    },

    async createUser(userData) {
      return this.request("users", {
        method: "POST",
        body: userData,
      });
    },

    async updateUser(userId, userData) {
      return this.request(`users/${userId}`, {
        method: "PUT",
        body: userData,
      });
    },

    async deleteUser(userId) {
      return this.request(`users/${userId}`, {
        method: "DELETE",
      });
    },

    // ========================================================================
    // EMAIL SETTINGS (SUPERADMIN ONLY)
    // ========================================================================

    async getEmailSettings() {
      try {
        console.log("📥 API: Getting email settings...");
        const response = await this.request("email-settings");
        console.log("📦 API: Email settings response:", response);
        return this.normalizeObjectResponse(response);
      } catch (error) {
        console.error("❌ API: Get email settings error:", error);
        throw error;
      }
    },

    async saveEmailSettings(data) {
      try {
        console.log("💾 API: Saving email settings...", data);
        const response = await this.request("email-settings/save", {
          method: "POST",
          body: data,
        });
        console.log("📦 API: Save response:", response);
        return response;
      } catch (error) {
        console.error("❌ API: Save email settings error:", error);
        throw error;
      }
    },

    async testEmail(testEmail) {
      try {
        console.log("📧 API: Testing email to:", testEmail);
        const response = await this.request("email-settings/test", {
          method: "POST",
          body: { test_email: testEmail },
        });
        console.log("📦 API: Test email response:", response);
        return response;
      } catch (error) {
        console.error("❌ API: Test email error:", error);
        throw error;
      }
    },

    // ========================================================================
    // USER PROFILE METHODS (ALL AUTHENTICATED USERS)
    // ========================================================================

    async updateUserProfile(userId, data) {
      try {
        console.log(`💾 API: Updating user profile ${userId}...`, data);
        const response = await this.request(`users/${userId}/profile`, {
          method: "PUT",
          body: data,
        });
        console.log("📦 API: Update profile response:", response);
        return response;
      } catch (error) {
        console.error("❌ API: Update profile error:", error);
        throw error;
      }
    },

    async changeUserPassword(userId, data) {
      try {
        console.log(`🔑 API: Changing password for user ${userId}...`);
        const response = await this.request(`users/${userId}/change-password`, {
          method: "POST",
          body: data,
        });
        console.log("📦 API: Change password response:", response);
        return response;
      } catch (error) {
        console.error("❌ API: Change password error:", error);
        throw error;
      }
    },

    // ========================================================================
    // AUDIT LOGS (Superadmin only)
    // ========================================================================

    async getAuditLogs(filters = {}) {
      const params = new URLSearchParams(filters).toString();
      return this.request(`audit-logs${params ? "?" + params : ""}`);
    },

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    isAuthenticated() {
      return !!this.getToken();
    },

    getCurrentUserData() {
      try {
        const userData = localStorage.getItem("janstro_user");
        return userData ? JSON.parse(userData) : null;
      } catch (error) {
        console.error("❌ Failed to parse user data:", error);
        return null;
      }
    },

    hasRole(allowedRoles) {
      const user = this.getCurrentUserData();
      if (!user || !user.role) return false;

      const userRole = user.role.toLowerCase();
      const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles];

      return roles.map((r) => r.toLowerCase()).includes(userRole);
    },

    async safeCall(apiFunction, fallback = null) {
      try {
        return await apiFunction();
      } catch (error) {
        console.error("❌ Safe call caught error:", error);

        if (window.ErrorHandler) {
          window.ErrorHandler.hideAllLoaders();
        }

        const errorMsg = this.categorizeError(error);
        if (window.Utils) {
          window.Utils.showToast(errorMsg, "error");
        }

        return fallback;
      }
    },

    categorizeError(error) {
      const msg = String(error?.message || error || "").toLowerCase();

      if (msg.includes("network") || msg.includes("fetch")) {
        return "Network error. Check your connection.";
      }
      if (msg.includes("401") || msg.includes("unauthorized")) {
        return "Session expired. Please log in again.";
      }
      if (msg.includes("403") || msg.includes("forbidden")) {
        return "Access denied.";
      }
      if (msg.includes("timeout")) {
        return "Request timed out. Please try again.";
      }

      return "An error occurred. Please try again.";
    },

    // ========================================================================
    // ANALYTICS (PHASE 9)
    // ========================================================================

    async getAnalyticsDashboard() {
      return this.request("analytics/dashboard");
    },

    async getInventoryAnalysis() {
      return this.request("analytics/inventory");
    },

    async getSupplierPerformance() {
      return this.request("analytics/suppliers");
    },

    async getSalesForecast() {
      return this.request("analytics/sales-forecast");
    },

    async getABCAnalysis() {
      return this.request("analytics/abc-analysis");
    },

    async getStockVelocity(days = 30) {
      return this.request(`analytics/stock-velocity?days=${days}`);
    },

    // ========================================================================
    // RESPONSE DATA NORMALIZER
    // ========================================================================

    normalizeArrayResponse(response) {
      if (!response) return [];

      if (response.data && Array.isArray(response.data)) {
        return response.data;
      }

      if (Array.isArray(response)) {
        return response;
      }

      if (response.success && response.data) {
        return Array.isArray(response.data) ? response.data : [response.data];
      }

      return [];
    },

    normalizeObjectResponse(response) {
      if (!response) return null;

      if (response.data && typeof response.data === "object") {
        return response.data;
      }

      if (response.success !== undefined) {
        return response.data || response;
      }

      return response;
    },
  };

  // ========================================================================
  // AUTO-INITIALIZATION
  // ========================================================================
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => API.init());
  } else {
    API.init();
  }
  // ========================================================================
  // GLOBAL EXPORT
  // ========================================================================
  window.API = API;

  console.log("✅ API Client v4.1 Complete Loaded (WITH PR METHODS)");
  console.log("📝 All endpoints including Purchase Requisitions available");
})(window);
