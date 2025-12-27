/**
 * ============================================================================
 * JANSTRO IMS - APP INITIALIZATION v3.1 COMPLETE (TOKEN-SAFE)
 * ============================================================================
 * Path: frontend/assets/js/app-init.js
 *
 * WHAT THIS FILE DOES:
 * - Coordinates the startup sequence of all app modules
 * - Ensures proper script loading order
 * - Waits for token validation before API calls
 * - Initializes page-specific features
 * - Handles initialization errors gracefully
 *
 * INITIALIZATION ORDER:
 * 1. Wait for DOM ready
 * 2. Wait for core scripts (API, RBAC, AppCore, ErrorHandler)
 * 3. Verify token on protected pages
 * 4. Initialize AppCore (auth, sidebar, theme)
 * 5. Initialize page-specific features (charts, etc.)
 * 6. Dispatch ready event
 *
 * CHANGELOG v3.1:
 * ✅ Added token verification before protected page access
 * ✅ Improved script loading detection
 * ✅ Enhanced error handling and recovery
 * ✅ Added initialization timeout safeguards
 * ✅ Better logging for debugging
 * ✅ Support for page-specific initialization
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
 * ============================================================================
 */

(async function initializeJanstroIMS() {
  "use strict";

  console.log("========================================");
  console.log("🚀 JANSTRO IMS INITIALIZATION v3.1");
  console.log("========================================");

  const INIT_TIMEOUT = 10000; // 10 seconds max
  const SCRIPT_CHECK_INTERVAL = 100; // Check every 100ms

  // ========================================================================
  // STEP 1: WAIT FOR DOM READY
  // ========================================================================
  if (document.readyState === "loading") {
    console.log("⏳ Waiting for DOM...");
    await new Promise((resolve) => {
      document.addEventListener("DOMContentLoaded", resolve);
    });
  }
  console.log("✅ DOM Ready");

  // ========================================================================
  // STEP 2: WAIT FOR CORE SCRIPTS TO LOAD
  // ========================================================================
  console.log("⏳ Waiting for core scripts...");

  const requiredGlobals = ["ErrorHandler", "API", "RBAC", "AppCore"];
  const startTime = Date.now();

  while (true) {
    const allLoaded = requiredGlobals.every(
      (name) => window[name] !== undefined
    );

    if (allLoaded) {
      console.log("✅ All core scripts loaded:");
      requiredGlobals.forEach((name) => {
        console.log(`   - ${name}: ${typeof window[name]}`);
      });
      break;
    }

    // Check for timeout
    if (Date.now() - startTime > INIT_TIMEOUT) {
      console.error("❌ Script loading timeout!");
      console.error(
        "Missing scripts:",
        requiredGlobals.filter((name) => !window[name])
      );

      showInitError(
        "Initialization Failed",
        "Required scripts failed to load. Please refresh the page."
      );
      return;
    }

    await new Promise((resolve) => setTimeout(resolve, SCRIPT_CHECK_INTERVAL));
  }

  // ========================================================================
  // STEP 3: INITIALIZE API CLIENT
  // ========================================================================
  console.log("⏳ Initializing API client...");

  if (window.API && typeof window.API.init === "function") {
    try {
      window.API.init();
      console.log("✅ API client initialized");
    } catch (error) {
      console.error("❌ API init failed:", error);
    }
  }

  // ========================================================================
  // STEP 4: VERIFY TOKEN ON PROTECTED PAGES
  // ========================================================================
  const currentPage = window.location.pathname.split("/").pop();
  const publicPages = [
    "index.html",
    "forgot-password.html",
    "reset-password.html",
    "",
  ];

  console.log(`📄 Current page: ${currentPage || "index.html"}`);

  if (!publicPages.includes(currentPage)) {
    console.log("🔐 Protected page detected - verifying token...");

    const token = window.API?.getToken();

    if (!token) {
      console.warn("⚠️ No token found - redirecting to login");
      window.location.href = "index.html";
      return;
    }

    console.log(`✅ Token verified (length: ${token.length})`);

    // Additional token validation
    try {
      const userData = window.API?.getCurrentUserData();
      if (!userData) {
        console.warn("⚠️ No user data - token may be invalid");
        window.location.href = "index.html";
        return;
      }
      console.log(
        `✅ User data loaded: ${userData.username} (${userData.role})`
      );
    } catch (error) {
      console.error("❌ User data validation failed:", error);
      window.location.href = "index.html";
      return;
    }
  } else {
    console.log("🌐 Public page - no authentication required");
  }

  // ========================================================================
  // STEP 5: INITIALIZE APP CORE
  // ========================================================================
  console.log("⏳ Initializing AppCore...");

  try {
    if (window.AppCore && typeof window.AppCore.init === "function") {
      await window.AppCore.init();
      console.log("✅ AppCore initialized");
    } else {
      console.warn("⚠️ AppCore not available");
    }
  } catch (error) {
    console.error("❌ AppCore init failed:", error);
    showInitError(
      "Application Error",
      "Failed to initialize application. Please refresh the page."
    );
    return;
  }

  // ========================================================================
  // STEP 6: INITIALIZE PAGE-SPECIFIC FEATURES
  // ========================================================================
  console.log("⏳ Initializing page-specific features...");

  try {
    await initPageFeatures(currentPage);
  } catch (error) {
    console.error("❌ Page features init failed:", error);
    // Don't block initialization - page features are optional
  }

  // ========================================================================
  // STEP 7: INITIALIZE ACCESSIBILITY FEATURES
  // ========================================================================
  if (window.Accessibility && typeof window.Accessibility.init === "function") {
    try {
      window.Accessibility.init();
      console.log("✅ Accessibility features initialized");
    } catch (error) {
      console.warn("⚠️ Accessibility init failed:", error);
    }
  }

  // ========================================================================
  // STEP 8: MARK INITIALIZATION COMPLETE
  // ========================================================================
  window.janstroInitialized = true;

  console.log("========================================");
  console.log("✅ JANSTRO IMS READY");
  console.log("========================================");
  console.log(`⏱️ Initialization time: ${Date.now() - startTime}ms`);

  // Dispatch ready event for external listeners
  window.dispatchEvent(
    new CustomEvent("janstro:ready", {
      detail: {
        timestamp: Date.now(),
        user: window.API?.getCurrentUserData(),
        page: currentPage,
        initTime: Date.now() - startTime,
      },
    })
  );

  // Announce to screen readers
  if (window.Accessibility) {
    window.Accessibility.announce("Application loaded successfully", "polite");
  }

  // ========================================================================
  // HELPER FUNCTIONS
  // ========================================================================

  /**
   * Initialize page-specific features
   */
  async function initPageFeatures(page) {
    // Dashboard charts
    if (page.includes("dashboard") && window.ChartSystem) {
      console.log("📊 Initializing dashboard charts...");
      try {
        await window.ChartSystem.init();
        console.log("✅ Charts initialized");
      } catch (error) {
        console.error("❌ Chart init failed:", error);
      }
    }

    // Reports page
    if (page.includes("reports") && window.ReportSystem) {
      console.log("📈 Initializing reports...");
      try {
        await window.ReportSystem.init();
        console.log("✅ Reports initialized");
      } catch (error) {
        console.error("❌ Reports init failed:", error);
      }
    }

    // Data tables (if using DataTables library)
    if (window.jQuery && window.jQuery.fn.DataTable) {
      console.log("📋 Initializing data tables...");
      try {
        initDataTables();
        console.log("✅ Data tables initialized");
      } catch (error) {
        console.error("❌ Data tables init failed:", error);
      }
    }

    // Form validation
    initFormValidation();

    // Tooltips and popovers (Bootstrap)
    if (window.bootstrap) {
      initBootstrapComponents();
    }
  }

  /**
   * Initialize DataTables (if present)
   */
  function initDataTables() {
    document.querySelectorAll(".data-table").forEach((table) => {
      if (window.jQuery && window.jQuery.fn.DataTable) {
        try {
          window.jQuery(table).DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, "desc"]],
          });
        } catch (error) {
          console.warn("DataTable init warning:", error);
        }
      }
    });
  }

  /**
   * Initialize form validation
   */
  function initFormValidation() {
    document.querySelectorAll("form[novalidate]").forEach((form) => {
      form.addEventListener(
        "submit",
        (event) => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add("was-validated");
        },
        false
      );
    });
    console.log("✅ Form validation initialized");
  }

  /**
   * Initialize Bootstrap components
   */
  function initBootstrapComponents() {
    try {
      // Initialize tooltips
      const tooltipTriggerList = document.querySelectorAll(
        '[data-bs-toggle="tooltip"]'
      );
      [...tooltipTriggerList].forEach((el) => new bootstrap.Tooltip(el));

      // Initialize popovers
      const popoverTriggerList = document.querySelectorAll(
        '[data-bs-toggle="popover"]'
      );
      [...popoverTriggerList].forEach((el) => new bootstrap.Popover(el));

      console.log("✅ Bootstrap components initialized");
    } catch (error) {
      console.warn("⚠️ Bootstrap components init failed:", error);
    }
  }

  /**
   * Show initialization error
   */
  function showInitError(title, message) {
    document.body.innerHTML = `
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-family:system-ui;text-align:center;padding:20px;">
        <div style="font-size:5rem;margin-bottom:20px;">⚠️</div>
        <h1 style="margin:0 0 10px;font-size:2rem;">${title}</h1>
        <p style="opacity:0.9;font-size:1.1rem;max-width:500px;margin:20px auto;">
          ${message}
        </p>
        <button onclick="location.reload()" style="margin-top:30px;padding:12px 30px;background:white;color:#667eea;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;">
          🔄 Refresh Page
        </button>
      </div>
    `;
  }
})().catch((error) => {
  // Catch any unhandled errors in the initialization chain
  console.error("========================================");
  console.error("🚨 FATAL INITIALIZATION ERROR");
  console.error("========================================");
  console.error("Error:", error);
  console.error("Stack trace:", error.stack);
  console.error("========================================");

  // Show user-friendly error
  document.body.innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;font-family:system-ui;text-align:center;padding:20px;">
      <div style="font-size:5rem;margin-bottom:20px;">💥</div>
      <h1 style="margin:0 0 10px;font-size:2rem;">Fatal Error</h1>
      <p style="opacity:0.9;font-size:1.1rem;max-width:500px;margin:20px auto;">
        The application failed to initialize. Please refresh the page or contact support if the problem persists.
      </p>
      <button onclick="location.reload()" style="margin-top:30px;padding:12px 30px;background:white;color:#ef4444;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;">
        🔄 Refresh Page
      </button>
      <details style="margin-top:30px;max-width:600px;text-align:left;background:rgba(0,0,0,0.2);padding:15px;border-radius:8px;">
        <summary style="cursor:pointer;font-weight:600;">Technical Details</summary>
        <pre style="margin-top:10px;font-size:0.8rem;white-space:pre-wrap;word-wrap:break-word;">${error.message}\n\n${error.stack}</pre>
      </details>
    </div>
  `;
});
