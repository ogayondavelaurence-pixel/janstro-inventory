/**
 * ============================================================================
 * JANSTRO IMS - ERROR HANDLER v1.1 COMPLETE (PRODUCTION-GRADE)
 * ============================================================================
 * Path: frontend/assets/js/error-handler.js
 *
 * WHAT THIS FILE DOES:
 * - Stops infinite loading spinners automatically
 * - Shows user-friendly error messages
 * - Handles network failures gracefully
 * - Provides retry mechanisms for failed requests
 * - Logs errors for debugging
 * - Monitors loading states with failsafe timeouts
 *
 * KEY PROBLEMS SOLVED:
 * ✅ Infinite loading spinners stuck on screen
 * ✅ Silent failures with no user feedback
 * ✅ Network errors crashing the UI
 * ✅ No recovery mechanism for failed API calls
 * ✅ Poor error visibility for developers
 *
 * CHANGELOG v1.1:
 * ✅ Added automatic loading spinner timeout (12 seconds)
 * ✅ Enhanced error message categorization (network, auth, data, etc.)
 * ✅ Improved empty state rendering
 * ✅ Added retry mechanism with exponential backoff
 * ✅ Network status monitoring (online/offline)
 * ✅ Better error logging with context
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
 * ============================================================================
 */

(function (window) {
  "use strict";

  const ErrorHandler = {
    // ========================================================================
    // CONFIGURATION
    // ========================================================================
    maxRetries: 2,
    retryDelay: 1000,
    loadingTimeout: 12000, // 12 seconds max for any loading state
    errorLogLimit: 100, // Keep last 100 errors in memory
    errorLog: [],

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    init() {
      this.setupGlobalErrorCatcher();
      this.setupLoadingFailsafe();
      this.setupNetworkMonitor();
      this.setupUnhandledPromiseRejection();
      console.log("✅ Error Handler v1.1 Complete Loaded");
    },

    // ========================================================================
    // GLOBAL ERROR CATCHING
    // ========================================================================

    /**
     * Catch all unhandled JavaScript errors
     */
    setupGlobalErrorCatcher() {
      window.addEventListener("error", (event) => {
        console.error("❌ Global Error:", event.error);
        this.logError({
          type: "global_error",
          message: event.message,
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
          error: event.error,
        });

        this.hideAllLoaders();

        // Show user-friendly error
        if (!navigator.onLine) {
          this.showError("No internet connection. Please check your network.");
        } else {
          this.showError(
            "Something went wrong. Please refresh the page if the problem persists."
          );
        }
      });
    },

    /**
     * Catch unhandled promise rejections
     */
    setupUnhandledPromiseRejection() {
      window.addEventListener("unhandledrejection", (event) => {
        console.error("❌ Unhandled Promise:", event.reason);
        this.logError({
          type: "promise_rejection",
          reason: event.reason,
          promise: event.promise,
        });

        this.hideAllLoaders();

        // Categorize error
        const errorMsg = this.categorizeError(event.reason);
        this.showError(errorMsg);
      });
    },

    /**
     * Categorize errors for better user messages
     */
    categorizeError(error) {
      const errorStr = String(error?.message || error || "").toLowerCase();

      if (errorStr.includes("network") || errorStr.includes("fetch")) {
        return "Network error. Please check your internet connection.";
      }

      if (errorStr.includes("unauthorized") || errorStr.includes("401")) {
        return "Session expired. Please log in again.";
      }

      if (errorStr.includes("forbidden") || errorStr.includes("403")) {
        return "Access denied. You don't have permission for this action.";
      }

      if (errorStr.includes("not found") || errorStr.includes("404")) {
        return "Requested resource not found.";
      }

      if (errorStr.includes("timeout") || errorStr.includes("timed out")) {
        return "Request timed out. Please try again.";
      }

      if (errorStr.includes("server") || errorStr.includes("500")) {
        return "Server error. Please try again later.";
      }

      return "An error occurred. Please try again.";
    },

    // ========================================================================
    // LOADING SPINNER FAILSAFE
    // ========================================================================

    /**
     * Auto-hide stuck loading spinners
     */
    setupLoadingFailsafe() {
      setInterval(() => {
        this.checkStuckLoaders();
      }, 3000); // Check every 3 seconds
    },

    /**
     * Check for and remove stuck loaders
     */
    checkStuckLoaders() {
      const spinners = document.querySelectorAll(
        ".spinner-border, .loading-overlay, [data-loading='true']"
      );

      spinners.forEach((spinner) => {
        const parent = spinner.closest("[data-loading-start]");

        if (parent) {
          const startTime = parseInt(parent.getAttribute("data-loading-start"));
          const elapsed = Date.now() - startTime;

          if (elapsed > this.loadingTimeout) {
            console.warn("⏱️ Failsafe: Removing stuck loader");
            this.hideLoader(spinner);
            this.showError("Request timed out", parent);
          }
        } else {
          // Orphaned loader - check if visible for too long
          const visibleTime = this.getElementVisibleTime(spinner);
          if (visibleTime > this.loadingTimeout) {
            console.warn("⏱️ Failsafe: Removing orphaned loader");
            this.hideLoader(spinner);
          }
        }
      });
    },

    /**
     * Mark element as loading (for timeout tracking)
     */
    markLoading(element) {
      if (element) {
        element.setAttribute("data-loading-start", Date.now());
        element.setAttribute("data-loading", "true");
      }
    },

    /**
     * Hide specific loader
     */
    hideLoader(spinner) {
      if (spinner.classList.contains("loading-overlay")) {
        spinner.style.display = "none";
      } else {
        const container = spinner.closest(
          ".text-center, .py-5, .py-4, .card-body, [data-loading='true']"
        );
        if (container) {
          container.removeAttribute("data-loading");
          container.removeAttribute("data-loading-start");
          spinner.remove();
        } else {
          spinner.remove();
        }
      }
    },

    /**
     * Hide all loading indicators
     */
    hideAllLoaders() {
      // Hide overlay
      const overlay = document.getElementById("loadingOverlay");
      if (overlay) overlay.style.display = "none";

      // Hide all spinners
      document.querySelectorAll(".spinner-border").forEach((spinner) => {
        this.hideLoader(spinner);
      });

      // Remove loading attributes
      document.querySelectorAll("[data-loading-start]").forEach((el) => {
        el.removeAttribute("data-loading-start");
        el.removeAttribute("data-loading");
      });
    },

    /**
     * Get how long element has been visible (approximation)
     */
    getElementVisibleTime(element) {
      // Check if element has animation
      const style = window.getComputedStyle(element);
      const animationDuration = parseFloat(style.animationDuration) || 0;

      // If no animation, assume it's been visible long
      return animationDuration > 0 ? 0 : this.loadingTimeout + 1000;
    },

    // ========================================================================
    // NETWORK MONITORING
    // ========================================================================

    setupNetworkMonitor() {
      window.addEventListener("online", () => {
        console.log("🌐 Network: ONLINE");
        const offlineMsg = document.querySelector(".offline-message");
        if (offlineMsg) offlineMsg.remove();

        this.showError(
          "Connection restored. You're back online.",
          document.body,
          "online-message success-message"
        );

        setTimeout(() => {
          document.querySelector(".online-message")?.remove();
        }, 3000);
      });

      window.addEventListener("offline", () => {
        console.log("🌐 Network: OFFLINE");
        this.hideAllLoaders();
        this.showError(
          "No internet connection. Please check your network.",
          document.body,
          "offline-message"
        );
      });

      // Initial check
      if (!navigator.onLine) {
        this.showError(
          "No internet connection. Please check your network.",
          document.body,
          "offline-message"
        );
      }
    },

    // ========================================================================
    // ERROR DISPLAY
    // ========================================================================

    /**
     * Show error message to user
     */
    showError(message, container = null, className = "error-message") {
      // Remove existing errors with same class
      document.querySelectorAll(`.${className.split(" ")[0]}`).forEach((el) => {
        el.remove();
      });

      const errorDiv = document.createElement("div");
      errorDiv.className = `alert alert-danger ${className} m-3`;
      errorDiv.style.cssText =
        "animation: fadeIn 0.3s; position: relative; z-index: 1000;";
      errorDiv.innerHTML = `
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div>${message}</div>
          <button type="button" class="btn-close ms-auto" aria-label="Close"></button>
        </div>
      `;

      // Add close functionality
      errorDiv.querySelector(".btn-close").addEventListener("click", () => {
        errorDiv.remove();
      });

      if (container) {
        container.insertBefore(errorDiv, container.firstChild);
      } else {
        const main = document.querySelector(".main-content") || document.body;
        main.insertBefore(errorDiv, main.firstChild);
      }

      // Auto-remove after 10 seconds
      setTimeout(() => errorDiv.remove(), 10000);
    },

    /**
     * Show empty state (no data)
     */
    showEmptyState(container, message = "No data available", icon = "inbox") {
      if (!container) return;

      container.innerHTML = `
        <div class="text-center py-5 text-muted">
          <i class="bi bi-${icon}" style="font-size: 3rem; opacity: 0.5;"></i>
          <p class="mt-3 mb-0">${message}</p>
        </div>
      `;
    },

    // ========================================================================
    // RETRY MECHANISM
    // ========================================================================

    /**
     * Retry failed request with exponential backoff
     */
    async retry(requestFn, retries = this.maxRetries) {
      for (let i = 0; i <= retries; i++) {
        try {
          return await requestFn();
        } catch (error) {
          if (i === retries) throw error;

          const delay = this.retryDelay * Math.pow(2, i);
          console.warn(`⏳ Retry ${i + 1}/${retries} after ${delay}ms`);
          await new Promise((resolve) => setTimeout(resolve, delay));
        }
      }
    },

    /**
     * Safe API call wrapper with retry
     */
    async safeApiCall(apiFunction, fallbackData = null) {
      try {
        const result = await this.retry(apiFunction);
        return result;
      } catch (error) {
        console.error("❌ API Call Failed:", error);
        this.hideAllLoaders();

        const errorMsg = this.categorizeError(error);
        this.showError(errorMsg);

        return fallbackData;
      }
    },

    // ========================================================================
    // ERROR LOGGING
    // ========================================================================

    /**
     * Log error to memory
     */
    logError(errorData) {
      const logEntry = {
        timestamp: new Date().toISOString(),
        ...errorData,
      };

      this.errorLog.push(logEntry);

      // Keep only last N errors
      if (this.errorLog.length > this.errorLogLimit) {
        this.errorLog.shift();
      }

      // Also log to console in development
      if (window.location.hostname === "localhost") {
        console.error("📝 Error logged:", logEntry);
      }
    },

    /**
     * Get error log
     */
    getErrorLog() {
      return [...this.errorLog];
    },

    /**
     * Clear error log
     */
    clearErrorLog() {
      this.errorLog = [];
      console.log("🗑️ Error log cleared");
    },

    /**
     * Export error log as JSON
     */
    exportErrorLog() {
      const dataStr = JSON.stringify(this.errorLog, null, 2);
      const blob = new Blob([dataStr], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `error-log-${Date.now()}.json`;
      a.click();
      URL.revokeObjectURL(url);
    },

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Show loading overlay
     */
    showLoading(message = "Loading...") {
      let overlay = document.getElementById("loadingOverlay");

      if (!overlay) {
        overlay = document.createElement("div");
        overlay.id = "loadingOverlay";
        overlay.className = "loading-overlay";
        document.body.appendChild(overlay);
      }

      overlay.innerHTML = `
        <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <p style="font-size: 1.1rem; color: white;">${message}</p>
      `;
      overlay.style.display = "flex";

      this.markLoading(overlay);
    },

    /**
     * Hide loading overlay
     */
    hideLoading() {
      const overlay = document.getElementById("loadingOverlay");
      if (overlay) {
        overlay.style.display = "none";
        overlay.removeAttribute("data-loading");
        overlay.removeAttribute("data-loading-start");
      }
    },

    /**
     * Check if online
     */
    isOnline() {
      return navigator.onLine;
    },
  };

  // ========================================================================
  // AUTO-INITIALIZATION
  // ========================================================================
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => ErrorHandler.init());
  } else {
    ErrorHandler.init();
  }

  // ========================================================================
  // GLOBAL EXPORT
  // ========================================================================
  window.ErrorHandler = ErrorHandler;

  // ========================================================================
  // CSS ANIMATIONS
  // ========================================================================
  const style = document.createElement("style");
  style.textContent = `
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      flex-direction: column;
      color: white;
      backdrop-filter: blur(4px);
    }

    .error-message {
      animation: fadeIn 0.3s ease-out;
    }

    .success-message {
      background: #d4edda !important;
      color: #155724 !important;
      border-color: #c3e6cb !important;
    }

    .offline-message {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10000;
      min-width: 300px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
  `;
  document.head.appendChild(style);

  console.log("✅ Error Handler v1.1 Complete Loaded");
  console.log("🛡️ Global error catching active");
  console.log("⏱️ Loading timeout: 12 seconds");
  console.log("🔄 Retry mechanism: enabled");
})(window);
