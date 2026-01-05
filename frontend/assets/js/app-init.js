/**
 * ============================================================================
 * JANSTRO IMS - APP INITIALIZATION v3.2 (TOKEN REFRESH FIX)
 * ============================================================================
 * FIXES APPLIED:
 * ✅ Enhanced token validation before protected page access
 * ✅ Automatic token refresh on 401 errors
 * ✅ Better error recovery for expired tokens
 * ✅ Improved script loading with retry mechanism
 * ============================================================================
 */

(async function initializeJanstroIMS() {
  "use strict";

  console.log("========================================");
  console.log("🚀 JANSTRO IMS INITIALIZATION v3.2");
  console.log("========================================");

  const INIT_TIMEOUT = 15000; // 15 seconds
  const SCRIPT_CHECK_INTERVAL = 100;
  const MAX_TOKEN_REFRESH_ATTEMPTS = 2;

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
  // STEP 4: ✅ ENHANCED TOKEN VALIDATION ON PROTECTED PAGES
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

    let token = window.API?.getToken();

    if (!token) {
      console.warn("⚠️ No token found - redirecting to login");
      window.location.href = "index.html";
      return;
    }

    console.log(`✅ Token found (length: ${token.length})`);

    // ✅ ENHANCEMENT: Validate token by making a test API call
    let tokenValid = false;
    let attempts = 0;

    while (attempts < MAX_TOKEN_REFRESH_ATTEMPTS && !tokenValid) {
      attempts++;
      console.log(
        `🔍 Token validation attempt ${attempts}/${MAX_TOKEN_REFRESH_ATTEMPTS}`
      );

      try {
        const response = await fetch(`${window.API.baseURL}/auth/me`, {
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        });

        if (response.ok) {
          const data = await response.json();
          if (data.success && data.data) {
            console.log(`✅ Token valid for user: ${data.data.username}`);
            tokenValid = true;
          }
        } else if (response.status === 401) {
          console.warn(`⚠️ Token expired (attempt ${attempts})`);

          // ✅ ENHANCEMENT: Attempt automatic token refresh
          if (attempts < MAX_TOKEN_REFRESH_ATTEMPTS) {
            console.log("🔄 Attempting token refresh...");

            try {
              const refreshResponse = await fetch(
                `${window.API.baseURL}/auth/refresh`,
                {
                  method: "POST",
                  headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "application/json",
                  },
                }
              );

              if (refreshResponse.ok) {
                const refreshData = await refreshResponse.json();
                if (refreshData.success && refreshData.data?.token) {
                  const newToken = refreshData.data.token;
                  window.API.saveToken(newToken);
                  token = newToken;
                  console.log("✅ Token refreshed successfully");
                  continue; // Retry validation with new token
                }
              }
            } catch (refreshError) {
              console.error("❌ Token refresh failed:", refreshError);
            }
          }

          // If refresh failed or max attempts reached
          console.error("❌ Token refresh failed - redirecting to login");
          window.API?.clearToken();
          window.location.href = "index.html";
          return;
        }
      } catch (error) {
        console.error(
          `❌ Token validation error (attempt ${attempts}):`,
          error
        );

        if (attempts >= MAX_TOKEN_REFRESH_ATTEMPTS) {
          console.error("❌ Max validation attempts reached - redirecting");
          window.API?.clearToken();
          window.location.href = "index.html";
          return;
        }

        // Wait before retry
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }

    if (!tokenValid) {
      console.error("❌ Token validation failed after all attempts");
      window.API?.clearToken();
      window.location.href = "index.html";
      return;
    }

    // ✅ ENHANCEMENT: Verify user data is accessible
    try {
      const userData = window.API?.getCurrentUserData();
      if (!userData) {
        console.warn("⚠️ No user data - token may be invalid");
        window.API?.clearToken();
        window.location.href = "index.html";
        return;
      }
      console.log(
        `✅ User data loaded: ${userData.username} (${userData.role})`
      );
    } catch (error) {
      console.error("❌ User data validation failed:", error);
      window.API?.clearToken();
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
  // STEP 8: ✅ SETUP GLOBAL ERROR HANDLERS FOR TOKEN EXPIRY
  // ========================================================================
  setupGlobalErrorHandlers();

  // ========================================================================
  // STEP 9: MARK INITIALIZATION COMPLETE
  // ========================================================================
  window.janstroInitialized = true;

  console.log("========================================");
  console.log("✅ JANSTRO IMS READY");
  console.log("========================================");
  console.log(`⏱️ Initialization time: ${Date.now() - startTime}ms`);

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

  if (window.Accessibility) {
    window.Accessibility.announce("Application loaded successfully", "polite");
  }

  // ========================================================================
  // HELPER FUNCTIONS
  // ========================================================================

  async function initPageFeatures(page) {
    if (page.includes("dashboard") && window.ChartSystem) {
      console.log("📊 Initializing dashboard charts...");
      try {
        await window.ChartSystem.init();
        console.log("✅ Charts initialized");
      } catch (error) {
        console.error("❌ Chart init failed:", error);
      }
    }

    if (page.includes("reports") && window.ReportSystem) {
      console.log("📈 Initializing reports...");
      try {
        await window.ReportSystem.init();
        console.log("✅ Reports initialized");
      } catch (error) {
        console.error("❌ Reports init failed:", error);
      }
    }

    if (window.jQuery && window.jQuery.fn.DataTable) {
      console.log("📋 Initializing data tables...");
      try {
        initDataTables();
        console.log("✅ Data tables initialized");
      } catch (error) {
        console.error("❌ Data tables init failed:", error);
      }
    }

    initFormValidation();

    if (window.bootstrap) {
      initBootstrapComponents();
    }
  }

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

  function initBootstrapComponents() {
    try {
      const tooltipTriggerList = document.querySelectorAll(
        '[data-bs-toggle="tooltip"]'
      );
      [...tooltipTriggerList].forEach((el) => new bootstrap.Tooltip(el));

      const popoverTriggerList = document.querySelectorAll(
        '[data-bs-toggle="popover"]'
      );
      [...popoverTriggerList].forEach((el) => new bootstrap.Popover(el));

      console.log("✅ Bootstrap components initialized");
    } catch (error) {
      console.warn("⚠️ Bootstrap components init failed:", error);
    }
  }

  // ✅ NEW: Global error handler for 401 responses
  function setupGlobalErrorHandlers() {
    // Intercept fetch requests globally
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
      try {
        const response = await originalFetch(...args);

        // Check for 401 Unauthorized
        if (response.status === 401) {
          console.warn(
            "🔒 Unauthorized request detected - token may be expired"
          );

          // Don't redirect if already on login page or this is a login request
          const url = args[0]?.toString() || "";
          if (
            !url.includes("/auth/login") &&
            !window.location.pathname.includes("index.html")
          ) {
            console.log("🔄 Redirecting to login...");
            window.API?.clearToken();
            setTimeout(() => {
              window.location.href = "index.html";
            }, 100);
          }
        }

        return response;
      } catch (error) {
        console.error("Fetch error:", error);
        throw error;
      }
    };

    console.log("✅ Global error handlers configured");
  }

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
  console.error("========================================");
  console.error("🚨 FATAL INITIALIZATION ERROR");
  console.error("========================================");
  console.error("Error:", error);
  console.error("Stack trace:", error.stack);
  console.error("========================================");

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
