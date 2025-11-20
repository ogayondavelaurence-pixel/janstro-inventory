/**
 * JANSTRO INVENTORY SYSTEM - API CLIENT v5.0
 * FIXED: Token storage as plain strings (not JSON), 401 handling, CORS
 * Date: 2025-11-21
 * Author: IMS Technical Team
 */

(function (window) {
  "use strict";

  const API_BASE_URL = "http://localhost:8080/janstro-inventory/public";
  const TOKEN_KEY = "janstro_token";
  const USER_KEY = "janstro_user";
  const REFRESH_KEY = "janstro_refresh";

  // ============================================
  // STORAGE MANAGER (FIXED: Raw string storage)
  // ============================================
  const Storage = {
    set: function (key, value) {
      try {
        // Store tokens as plain strings, not JSON
        if (key === TOKEN_KEY || key === REFRESH_KEY) {
          localStorage.setItem(key, value);
          sessionStorage.setItem(key, value);
        } else {
          // Store user data as JSON
          const jsonValue = JSON.stringify(value);
          localStorage.setItem(key, jsonValue);
          sessionStorage.setItem(key, jsonValue);
        }
        console.log(`✅ ${key} stored successfully`);
        return true;
      } catch (error) {
        console.error(`❌ Storage.set error:`, error);
        return false;
      }
    },

    get: function (key) {
      try {
        // Try localStorage first
        let value = localStorage.getItem(key) || sessionStorage.getItem(key);
        if (!value) return null;

        // Tokens are stored as plain strings
        if (key === TOKEN_KEY || key === REFRESH_KEY) {
          return value;
        }

        // User data is stored as JSON
        return JSON.parse(value);
      } catch (error) {
        console.error(`❌ Storage.get error for ${key}:`, error);
        // Clear corrupted data
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
      [TOKEN_KEY, USER_KEY, REFRESH_KEY].forEach((key) => {
        localStorage.removeItem(key);
        sessionStorage.removeItem(key);
      });
      console.log("🗑️ Session cleared");
    },
  };

  // ============================================
  // API MODULE
  // ============================================
  const API = {
    // Authentication Methods
    login: async function (username, password) {
      console.log(`🔐 Login attempt: ${username}`);

      try {
        const response = await this.post("/auth/login", {
          username: username,
          password: password,
        });

        console.log("📥 Login response:", response);

        if (response && response.success && response.data) {
          const { token, refresh_token, user } = response.data;

          // Store tokens as plain strings
          Storage.set(TOKEN_KEY, token);
          if (refresh_token) {
            Storage.set(REFRESH_KEY, refresh_token);
          }

          // Store user data as JSON
          Storage.set(USER_KEY, {
            user_id: user.user_id,
            username: user.username,
            role_id: user.role_id,
            role_name: user.role_name,
            permissions: user.permissions || [],
          });

          // Verify storage
          const storedToken = Storage.get(TOKEN_KEY);
          const storedUser = Storage.get(USER_KEY);

          console.log("✅ Login successful, data persisted:", {
            tokenStored: !!storedToken,
            userStored: !!storedUser,
            tokenLength: storedToken?.length,
            username: storedUser?.username,
          });

          return response;
        }

        console.error("❌ Login failed:", response);
        return null;
      } catch (error) {
        console.error("❌ Login error:", error);
        return null;
      }
    },

    logout: async function () {
      try {
        await this.post("/auth/logout", {});
      } catch (error) {
        console.error("Logout error:", error);
      } finally {
        Storage.clear();
        window.location.href = "/janstro-inventory/frontend/index.html";
      }
    },

    isAuthenticated: function () {
      const token = this.getToken();
      const user = Storage.get(USER_KEY);

      const isAuth = !!(token && user);
      console.log("🔒 isAuthenticated:", isAuth, {
        hasToken: !!token,
        hasUser: !!user,
        tokenPreview: token ? token.substring(0, 20) + "..." : null,
      });

      return isAuth;
    },

    getToken: function () {
      return Storage.get(TOKEN_KEY);
    },

    getUser: function () {
      return Storage.get(USER_KEY);
    },

    // HTTP Request Methods
    request: async function (endpoint, options = {}) {
      const url = `${API_BASE_URL}${endpoint}`;
      const token = this.getToken();

      const config = {
        method: options.method || "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...options.headers,
        },
        credentials: "include",
        mode: "cors",
      };

      if (options.body) {
        config.body = JSON.stringify(options.body);
      }

      try {
        const response = await fetch(url, config);

        // Handle 401 Unauthorized
        if (response.status === 401) {
          console.warn("⚠️ 401 Unauthorized - clearing session");
          Storage.clear();
          if (!endpoint.includes("/auth/login")) {
            window.location.href = "/janstro-inventory/frontend/index.html";
          }
          return null;
        }

        // Handle non-OK responses
        if (!response.ok) {
          console.error(`❌ HTTP ${response.status}:`, response.statusText);
          return null;
        }

        const data = await response.json();
        return data;
      } catch (error) {
        console.error(`❌ Request failed [${endpoint}]:`, error);
        return null;
      }
    },

    get: function (endpoint, params = {}) {
      const queryString = new URLSearchParams(params).toString();
      const fullEndpoint = queryString
        ? `${endpoint}?${queryString}`
        : endpoint;
      return this.request(fullEndpoint, { method: "GET" });
    },

    post: function (endpoint, body) {
      return this.request(endpoint, {
        method: "POST",
        body: body,
      });
    },

    put: function (endpoint, body) {
      return this.request(endpoint, {
        method: "PUT",
        body: body,
      });
    },

    delete: function (endpoint) {
      return this.request(endpoint, {
        method: "DELETE",
      });
    },

    // Health check
    checkHealth: async function () {
      try {
        const response = await fetch(`${API_BASE_URL}/health`);
        const data = await response.json();
        console.log("🏥 API Health:", data);
        return data.success;
      } catch (error) {
        console.error("❌ API Health check failed:", error);
        return false;
      }
    },
  };

  // ============================================
  // EXPOSE API GLOBALLY
  // ============================================
  window.API = API;
  console.log("✅ API Module v5.0 Loaded");
})(window);
