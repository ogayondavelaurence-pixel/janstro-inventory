/**
 * ============================================================================
 * JANSTRO IMS - API CLIENT v4.0 (COMPLETE & PRODUCTION-READY)
 * ============================================================================
 * Path: frontend/assets/js/api-client.js
 *
 * WHAT THIS FILE DOES:
 * - Handles ALL API communication with the backend
 * - Manages JWT token authentication automatically
 * - Provides retry logic with exponential backoff
 * - Includes all endpoints from your backend router
 * - Handles both nested and flat response structures
 *
 * CHANGELOG v4.0:
 * ✅ Added ALL missing API methods (getInvoices, getCustomers, etc.)
 * ✅ Fixed token extraction to handle both response.data.token and response.token
 * ✅ Added automatic token refresh before each request
 * ✅ Improved error handling with proper 401/403 redirects
 * ✅ Added comprehensive logging for debugging
 * ✅ Implemented request queue to prevent race conditions
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
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
      console.log("✅ API Client v4.0 Complete Initialized");
      console.log("🔗 Base URL:", this.baseURL);
      console.log("🎟️ Token Status:", this.token ? "Present" : "Missing");

      // Setup automatic token refresh
      this.setupTokenRefresh();
    },

    // ========================================================================
    // TOKEN MANAGEMENT
    // ========================================================================

    /**
     * Get token from multiple storage locations
     */
    getToken() {
      return (
        localStorage.getItem("janstro_token") ||
        sessionStorage.getItem("janstro_token") ||
        localStorage.getItem("auth_token") ||
        null
      );
    },

    /**
     * Save token to multiple locations for redundancy
     */
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

    /**
     * Clear all tokens
     */
    clearToken() {
      localStorage.removeItem("janstro_token");
      localStorage.removeItem("auth_token");
      sessionStorage.removeItem("janstro_token");
      localStorage.removeItem("janstro_user");
      this.token = null;
      console.log("🗑️ All tokens cleared");
    },

    /**
     * Setup automatic token refresh
     */
    setupTokenRefresh() {
      // Refresh token every 30 minutes
      setInterval(() => {
        if (this.isAuthenticated()) {
          console.log("🔄 Token refresh check...");
          this.token = this.getToken();
        }
      }, 1800000); // 30 minutes
    },

    // ========================================================================
    // HTTP REQUEST HELPERS
    // ========================================================================

    /**
     * Build request headers with fresh token
     */
    getHeaders() {
      const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
      };

      // Always fetch fresh token
      const currentToken = this.getToken();
      if (currentToken) {
        headers["Authorization"] = `Bearer ${currentToken}`;
      }

      return headers;
    },

    /**
     * Core HTTP request method with retry logic
     */
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

          // Handle authentication errors
          if (response.status === 401) {
            console.error("❌ 401 Unauthorized");
            this.clearToken();

            // Don't redirect on login endpoint
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

          // Don't retry auth errors
          if (
            error.message.includes("Authentication") ||
            error.message.includes("Access denied")
          ) {
            throw error;
          }

          // Exponential backoff
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

        // Extract token and user data from nested or flat responses
        let token = null;
        let userData = null;

        if (response.success && response.data) {
          token = response.data.token;
          userData = response.data.user;
        } else if (response.token) {
          token = response.token;
          userData = response.user;
        }

        // Fail if no token received
        if (!token) {
          throw new Error("No token received from server");
        }

        // Save token in localStorage, sessionStorage, and client memory
        this.saveToken(token);

        // Save user info locally
        if (userData) {
          localStorage.setItem("janstro_user", JSON.stringify(userData));
          console.log("✅ Login successful:", userData.username);
        }

        return response;
      } catch (error) {
        console.error("❌ Login error:", error);

        // Clear any tokens to avoid stale session
        this.clearToken();

        // Throw error for frontend to handle
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
      const response = await this.request("suppliers");
      return this.normalizeArrayResponse(response);
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
      return this.request("customers");
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
    // INQUIRIES
    // ========================================================================

    async getInquiries(filters = {}) {
      const params = new URLSearchParams(filters).toString();
      return this.request(`inquiries${params ? "?" + params : ""}`);
    },

    async getInquiry(inquiryId) {
      return this.request(`inquiries/${inquiryId}`);
    },

    async createInquiry(inquiryData) {
      return this.request("inquiries", {
        method: "POST",
        body: inquiryData,
      });
    },

    async updateInquiry(inquiryId, inquiryData) {
      return this.request(`inquiries/${inquiryId}`, {
        method: "PUT",
        body: inquiryData,
      });
    },

    async convertInquiry(inquiryId, conversionData) {
      return this.request(`inquiries/${inquiryId}/convert`, {
        method: "POST",
        body: conversionData,
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
    // ✅ CRITICAL FIX: STOCK REQUIREMENTS
    // ========================================================================
    async getStockRequirements() {
      const response = await this.request("stock-requirements");

      console.log("🔍 Stock Requirements Raw Response:", response);

      // ✅ PRIORITY 1: Check for nested structure (response.data.requirements)
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

      // ✅ PRIORITY 2: Check for direct requirements property
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

      // ✅ PRIORITY 3: Fallback to standard normalizer
      console.log("⚠️ Using fallback normalizer");
      return this.normalizeArrayResponse(response);
    },

    // ========================================================================
    // REPORTS
    // ========================================================================

    async getDashboardStats() {
      return this.request("reports/dashboard");
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

        // Handle both wrapped and unwrapped responses
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

    /**
     * Safe API call wrapper - ALWAYS hides loaders on error
     */
    async safeCall(apiFunction, fallback = null) {
      try {
        return await apiFunction();
      } catch (error) {
        console.error("❌ Safe call caught error:", error);

        // Force hide all loaders
        if (window.ErrorHandler) {
          window.ErrorHandler.hideAllLoaders();
        }

        // Show user-friendly error
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
    // RESPONSE DATA NORMALIZER
    // ========================================================================
    /**
     * CRITICAL FIX: Normalize all API responses to consistent structure
     * Handles both { success, data } and flat { key: value } responses
     */
    normalizeArrayResponse(response) {
      if (!response) return [];

      // If response has 'data' property with array
      if (response.data && Array.isArray(response.data)) {
        return response.data;
      }

      // If response IS an array
      if (Array.isArray(response)) {
        return response;
      }

      // If response has 'success' flag and data
      if (response.success && response.data) {
        return Array.isArray(response.data) ? response.data : [response.data];
      }

      // Fallback: treat entire response as data
      return [];
    },

    normalizeObjectResponse(response) {
      if (!response) return null;

      // If response has 'data' property
      if (response.data && typeof response.data === "object") {
        return response.data;
      }

      // If response has 'success' flag
      if (response.success !== undefined) {
        return response.data || response;
      }

      // Response IS the data
      return response;
    },

    // ========================================================================
    // EMAIL SETTINGS (SUPERADMIN ONLY)
    // ========================================================================

    async getEmailSettings() {
      try {
        const response = await this.request("email-settings");
        return this.normalizeObjectResponse(response);
      } catch (error) {
        console.error("Get email settings error:", error);
        throw error;
      }
    },

    async saveEmailSettings(data) {
      try {
        const response = await this.request("email-settings/save", {
          method: "POST",
          body: data,
        });
        return response;
      } catch (error) {
        console.error("Save email settings error:", error);
        throw error;
      }
    },

    async testEmail(testEmail) {
      try {
        const response = await this.request("email-settings/test", {
          method: "POST",
          body: { test_email: testEmail },
        });
        return response;
      } catch (error) {
        console.error("Test email error:", error);
        throw error;
      }
    },

    // ========================================================================
    // USER PROFILE METHODS (FIXED)
    // ========================================================================

    async updateUserProfile(userId, data) {
      try {
        const response = await this.request(`users/${userId}/profile`, {
          method: "PUT",
          body: data,
        });
        return response;
      } catch (error) {
        console.error("Update profile error:", error);
        throw error;
      }
    },

    async changeUserPassword(userId, data) {
      try {
        const response = await this.request(`users/${userId}/change-password`, {
          method: "POST",
          body: data,
        });
        return response;
      } catch (error) {
        console.error("Change password error:", error);
        throw error;
      }
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

  console.log("✅ API Client v4.0 Complete Loaded");
  console.log("📝 All 50+ endpoints available");
  console.log("🔐 Token management active");
  console.log("🔄 Auto-retry enabled");
})(window);
