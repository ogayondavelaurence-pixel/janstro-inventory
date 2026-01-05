/**
 * ============================================================================
 * JANSTRO IMS - ENVIRONMENT CONFIGURATION v1.0
 * ============================================================================
 * Path: frontend/assets/js/config.js
 *
 * Automatically detects localhost vs production and sets correct API URLs
 * ============================================================================
 */

(function (window) {
  "use strict";

  const hostname = window.location.hostname;
  const protocol = window.location.protocol;
  const port = window.location.port;

  // Detect environment
  const isLocalhost =
    hostname === "localhost" ||
    hostname === "127.0.0.1" ||
    hostname.startsWith("192.168.");

  // Configuration object
  const AppConfig = {
    // API Base URL
    API_BASE: isLocalhost
      ? `http://localhost:8080/janstro-inventory/public`
      : `${protocol}//${hostname}/api`,

    // Frontend Base URL
    FRONTEND_BASE: isLocalhost
      ? `http://localhost:8080/janstro-inventory/frontend`
      : `${protocol}//${hostname}`,

    // Environment flag
    IS_PRODUCTION: !isLocalhost,
    IS_LOCALHOST: isLocalhost,

    // Helper methods
    getApiUrl(endpoint) {
      return `${this.API_BASE}/${endpoint.replace(/^\//, "")}`;
    },

    getFrontendUrl(path) {
      return `${this.FRONTEND_BASE}/${path.replace(/^\//, "")}`;
    },
  };

  // Export to global scope
  window.AppConfig = AppConfig;

  // Log configuration (development only)
  if (!AppConfig.IS_PRODUCTION) {
    console.log("✅ AppConfig Loaded:");
    console.log(
      "   Environment:",
      AppConfig.IS_PRODUCTION ? "Production" : "Development"
    );
    console.log("   API Base:", AppConfig.API_BASE);
    console.log("   Frontend Base:", AppConfig.FRONTEND_BASE);
  }
})(window);
