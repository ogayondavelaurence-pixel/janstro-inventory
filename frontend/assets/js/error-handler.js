/**
 * ============================================================================
 * JANSTRO IMS - ERROR HANDLER v1.2 PRODUCTION
 * ============================================================================
 * Path: frontend/assets/js/error-handler.js
 *
 * PRODUCTION CHANGES:
 * ✅ Removed development console.logs
 * ✅ Added production error reporting
 * ✅ Maintained critical error logging only
 * ✅ Enhanced user-friendly error messages
 * ============================================================================
 */

(function (window) {
  "use strict";

  const ErrorHandler = {
    // ========================================================================
    // CONFIGURATION
    // ========================================================================
    config: {
      enableErrorReporting: true,
      logToServer: false, // Set to true if you have server-side error logging
      maxErrorsPerSession: 50,
      errorReportEndpoint: "/api/errors/report",
    },

    errorCount: 0,
    errorCache: new Set(),

    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    init() {
      this.attachGlobalHandlers();
      this.preventDuplicateErrors();
    },

    // ========================================================================
    // GLOBAL ERROR HANDLERS
    // ========================================================================
    attachGlobalHandlers() {
      // Uncaught errors
      window.addEventListener("error", (event) => {
        this.handleError({
          message: event.message || "Unknown error",
          source: event.filename || "unknown",
          lineno: event.lineno || 0,
          colno: event.colno || 0,
          error: event.error,
          type: "runtime",
        });
      });

      // Unhandled promise rejections
      window.addEventListener("unhandledrejection", (event) => {
        this.handleError({
          message:
            event.reason?.message ||
            String(event.reason) ||
            "Promise rejection",
          error: event.reason,
          type: "promise",
        });
      });

      // Network errors
      window.addEventListener("offline", () => {
        this.showError(
          "No internet connection. Please check your network.",
          "warning",
          5000
        );
      });

      window.addEventListener("online", () => {
        this.showSuccess("Connection restored", 3000);
      });
    },

    // ========================================================================
    // ERROR HANDLING
    // ========================================================================
    handleError(errorData) {
      if (!this.shouldLogError(errorData)) {
        return;
      }

      this.errorCount++;

      // Rate limit error reporting
      if (this.errorCount > this.config.maxErrorsPerSession) {
        return;
      }

      const errorInfo = this.formatError(errorData);

      // Log to console in production (minimal)
      if (errorInfo.severity === "critical") {
        console.error("[CRITICAL]", errorInfo.message);
      }

      // Send to server if enabled
      if (this.config.logToServer && this.config.enableErrorReporting) {
        this.reportToServer(errorInfo);
      }

      // Show user-friendly message
      this.showUserError(errorInfo);
    },

    // ========================================================================
    // ERROR FORMATTING
    // ========================================================================
    formatError(errorData) {
      const now = new Date();

      return {
        timestamp: now.toISOString(),
        message: this.sanitizeErrorMessage(errorData.message),
        type: errorData.type || "unknown",
        source: errorData.source || "unknown",
        line: errorData.lineno || null,
        column: errorData.colno || null,
        stack: errorData.error?.stack
          ? this.sanitizeStack(errorData.error.stack)
          : null,
        userAgent: navigator.userAgent,
        url: window.location.href,
        severity: this.determineSeverity(errorData),
      };
    },

    sanitizeErrorMessage(message) {
      // Remove sensitive data from error messages
      return String(message)
        .replace(/password=\w+/gi, "password=***")
        .replace(/token=\w+/gi, "token=***")
        .replace(/key=\w+/gi, "key=***");
    },

    sanitizeStack(stack) {
      // Only keep first 3 lines of stack trace
      return stack.split("\n").slice(0, 3).join("\n");
    },

    determineSeverity(errorData) {
      const message = String(errorData.message).toLowerCase();

      if (
        message.includes("network") ||
        message.includes("fetch") ||
        message.includes("timeout")
      ) {
        return "warning";
      }

      if (
        message.includes("unauthorized") ||
        message.includes("forbidden") ||
        message.includes("token")
      ) {
        return "security";
      }

      if (
        message.includes("database") ||
        message.includes("sql") ||
        message.includes("critical")
      ) {
        return "critical";
      }

      return "error";
    },

    // ========================================================================
    // ERROR DEDUPLICATION
    // ========================================================================
    preventDuplicateErrors() {
      // Clear cache every 5 minutes
      setInterval(() => {
        this.errorCache.clear();
      }, 300000);
    },

    shouldLogError(errorData) {
      const errorKey = `${errorData.message}:${errorData.source}:${errorData.lineno}`;

      if (this.errorCache.has(errorKey)) {
        return false;
      }

      this.errorCache.add(errorKey);
      return true;
    },

    // ========================================================================
    // SERVER REPORTING
    // ========================================================================
    async reportToServer(errorInfo) {
      try {
        await fetch(this.config.errorReportEndpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(errorInfo),
        });
      } catch (e) {
        // Silently fail - don't show errors about error reporting
      }
    },

    // ========================================================================
    // USER NOTIFICATIONS
    // ========================================================================
    showUserError(errorInfo) {
      const userMessage = this.getUserFriendlyMessage(errorInfo);
      const duration = errorInfo.severity === "critical" ? 10000 : 5000;

      this.showError(userMessage, errorInfo.severity, duration);
    },

    getUserFriendlyMessage(errorInfo) {
      const message = errorInfo.message.toLowerCase();

      // Network errors
      if (message.includes("network") || message.includes("fetch")) {
        return "Connection issue. Please check your internet.";
      }

      // Authentication errors
      if (message.includes("unauthorized") || message.includes("token")) {
        return "Session expired. Please log in again.";
      }

      // Permission errors
      if (message.includes("forbidden") || message.includes("access denied")) {
        return "You don't have permission for this action.";
      }

      // Validation errors
      if (message.includes("validation") || message.includes("invalid")) {
        return errorInfo.message; // Show actual validation message
      }

      // Generic error
      return "An error occurred. Please try again.";
    },

    // ========================================================================
    // TOAST NOTIFICATIONS
    // ========================================================================
    showError(message, type = "error", duration = 5000) {
      if (window.Utils && window.Utils.showToast) {
        window.Utils.showToast(message, type, duration);
      } else {
        alert(message);
      }
    },

    showSuccess(message, duration = 3000) {
      if (window.Utils && window.Utils.showToast) {
        window.Utils.showToast(message, "success", duration);
      }
    },

    // ========================================================================
    // LOADING STATE MANAGEMENT
    // ========================================================================
    showLoader(message = "Loading...") {
      let loader = document.getElementById("globalLoader");

      if (!loader) {
        loader = document.createElement("div");
        loader.id = "globalLoader";
        loader.className = "global-loader";
        loader.innerHTML = `
          <div class="loader-content">
            <div class="spinner"></div>
            <p class="loader-message">${message}</p>
          </div>
        `;
        document.body.appendChild(loader);

        // Add CSS if not exists
        if (!document.getElementById("loaderStyles")) {
          const style = document.createElement("style");
          style.id = "loaderStyles";
          style.textContent = `
            .global-loader {
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0, 0, 0, 0.7);
              display: flex;
              align-items: center;
              justify-content: center;
              z-index: 9999;
              backdrop-filter: blur(4px);
            }
            .loader-content {
              text-align: center;
              color: white;
            }
            .spinner {
              width: 50px;
              height: 50px;
              margin: 0 auto 20px;
              border: 4px solid rgba(255, 255, 255, 0.3);
              border-top-color: white;
              border-radius: 50%;
              animation: spin 1s linear infinite;
            }
            @keyframes spin {
              to { transform: rotate(360deg); }
            }
            .loader-message {
              font-size: 1.1rem;
              margin: 0;
            }
          `;
          document.head.appendChild(style);
        }
      }

      loader.style.display = "flex";
      loader.querySelector(".loader-message").textContent = message;
    },

    hideLoader() {
      const loader = document.getElementById("globalLoader");
      if (loader) {
        loader.style.display = "none";
      }
    },

    hideAllLoaders() {
      // Hide global loader
      this.hideLoader();

      // Hide any Utils loaders
      if (window.Utils && window.Utils.loadingState) {
        window.Utils.loadingState(false);
      }

      // Remove any other loaders
      document
        .querySelectorAll(".loading-overlay, .spinner-border")
        .forEach((el) => {
          el.remove();
        });
    },

    // ========================================================================
    // API ERROR HANDLING
    // ========================================================================
    async handleAPIError(error, context = "") {
      let errorMessage = "An error occurred";

      if (error.response) {
        // HTTP error response
        try {
          const data = await error.response.json();
          errorMessage = data.message || errorMessage;
        } catch (e) {
          errorMessage = `Server error (${error.response.status})`;
        }
      } else if (error.message) {
        errorMessage = error.message;
      }

      this.showError(errorMessage, "error");
      this.hideAllLoaders();

      if (context) {
        console.error(`[${context}]`, errorMessage);
      }
    },

    // ========================================================================
    // VALIDATION ERROR DISPLAY
    // ========================================================================
    showValidationErrors(errors, formId = null) {
      if (!errors || typeof errors !== "object") {
        return;
      }

      // Clear existing validation errors
      document.querySelectorAll(".is-invalid").forEach((el) => {
        el.classList.remove("is-invalid");
      });

      document.querySelectorAll(".invalid-feedback").forEach((el) => {
        el.textContent = "";
      });

      // Display new errors
      Object.entries(errors).forEach(([field, message]) => {
        const input = formId
          ? document.querySelector(`#${formId} [name="${field}"]`)
          : document.querySelector(`[name="${field}"]`);

        if (input) {
          input.classList.add("is-invalid");

          let feedback = input.nextElementSibling;
          if (!feedback || !feedback.classList.contains("invalid-feedback")) {
            feedback = document.createElement("div");
            feedback.className = "invalid-feedback";
            input.parentNode.appendChild(feedback);
          }

          feedback.textContent = Array.isArray(message) ? message[0] : message;
        }
      });

      // Show summary toast
      const errorCount = Object.keys(errors).length;
      this.showError(`Please fix ${errorCount} validation error(s)`, "warning");
    },

    // ========================================================================
    // NETWORK STATUS
    // ========================================================================
    checkNetworkStatus() {
      if (!navigator.onLine) {
        this.showError("No internet connection", "warning", 0);
        return false;
      }
      return true;
    },

    // ========================================================================
    // CLEANUP
    // ========================================================================
    reset() {
      this.errorCount = 0;
      this.errorCache.clear();
      this.hideAllLoaders();
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
})(window);
