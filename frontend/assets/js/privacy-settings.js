/**
 * ============================================================================
 * PRIVACY SETTINGS - COMPLETE FIXED v4.0 (PERSISTENT STATE)
 * ============================================================================
 * FIXES:
 * ✅ Properly checks for existing deletion request on load
 * ✅ State persists after page reload
 * ✅ Prevents duplicate submissions
 * ✅ Better error handling
 * ============================================================================
 */

(function () {
  "use strict";

  let currentUser = null;
  let activeSessions = [];
  let hasPendingDeletion = false;

  // ========================================================================
  // INITIALIZATION
  // ========================================================================
  document.addEventListener("DOMContentLoaded", async () => {
    console.log("🔧 Privacy Settings v4.0: Initializing...");

    await loadCurrentUser();
    await loadActiveSessions();
    await checkExistingDeletionRequest(); // ✅ CRITICAL: Check on load
    setupEventListeners();
  });

  // ========================================================================
  // CHECK FOR EXISTING DELETION REQUEST - ✅ FIXED v4.0
  // ========================================================================
  async function checkExistingDeletionRequest() {
    try {
      console.log("🔍 Checking for existing deletion request...");

      const response = await fetch(`${API.baseURL}/privacy/deletion-status`, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${API.getToken()}`,
          "Content-Type": "application/json",
        },
      });

      console.log("📊 Deletion status response:", response.status);

      if (!response.ok) {
        console.log("⚠️ Failed to check deletion status:", response.statusText);
        return;
      }

      const result = await response.json();
      console.log("📦 Deletion status result:", result);

      if (result.success && result.data && result.data.has_pending) {
        console.log("⚠️ User has existing pending deletion request");
        hasPendingDeletion = true;
        showDeletionPendingStatus(result.data.request);
      } else {
        console.log("✅ No pending deletion request found");
        hasPendingDeletion = false;
      }
    } catch (error) {
      console.error("❌ Error checking deletion status:", error);
      // Don't block the UI if check fails
    }
  }

  // ========================================================================
  // LOAD CURRENT USER
  // ========================================================================
  async function loadCurrentUser() {
    try {
      const response = await API.getCurrentUser();

      if (response.success && response.data) {
        currentUser = response.data;
        console.log("✅ Current user loaded:", currentUser.username);
        updateUserInfo();
      } else {
        throw new Error("Failed to load user data");
      }
    } catch (error) {
      console.error("❌ Load user error:", error);
      Utils.showToast("Session expired. Please login again.", "error");
      setTimeout(() => {
        window.location.href = "index.html";
      }, 2000);
    }
  }

  // ========================================================================
  // UPDATE USER INFO DISPLAY
  // ========================================================================
  function updateUserInfo() {
    const elements = {
      username: document.getElementById("currentUsername"),
      email: document.getElementById("currentEmail"),
      memberSince: document.getElementById("memberSince"),
    };

    if (elements.username) elements.username.textContent = currentUser.username;
    if (elements.email)
      elements.email.textContent = currentUser.email || "Not set";
    if (elements.memberSince && currentUser.created_at) {
      elements.memberSince.textContent = formatDate(currentUser.created_at);
    }
  }

  // ========================================================================
  // LOAD ACTIVE SESSIONS
  // ========================================================================
  async function loadActiveSessions() {
    try {
      console.log("📥 Loading active sessions...");

      const response = await fetch(`${API.baseURL}/privacy/sessions`, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${API.getToken()}`,
          "Content-Type": "application/json",
        },
      });

      const result = await response.json();

      if (result.success && result.data) {
        activeSessions = result.data.sessions || [];
        renderSessions();
      } else {
        throw new Error(result.message || "Failed to load sessions");
      }
    } catch (error) {
      console.error("❌ Load sessions error:", error);
      Utils.showToast("Failed to load sessions", "error");

      const tbody = document.getElementById("sessionsTable");
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="text-center text-muted py-4">
              Failed to load sessions
            </td>
          </tr>
        `;
      }
    }
  }

  // ========================================================================
  // RENDER SESSIONS TABLE
  // ========================================================================
  function renderSessions() {
    const tbody = document.getElementById("sessionsTable");
    if (!tbody) return;

    if (activeSessions.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center text-muted py-4">
            <i class="bi bi-info-circle"></i>
            No active sessions found
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = activeSessions
      .map((session) => {
        const isCurrentSession = session.is_current === 1;
        const deviceInfo = parseUserAgent(session.user_agent);

        return `
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <i class="bi ${getDeviceIcon(deviceInfo.device)} fs-5"></i>
                <div>
                  <strong>${deviceInfo.browser}</strong>
                  <br>
                  <small class="text-muted">${deviceInfo.os}</small>
                </div>
              </div>
            </td>
            <td>
              <code>${session.ip_address}</code>
            </td>
            <td>
              <small>${formatDateTime(session.last_activity)}</small>
              ${
                isCurrentSession
                  ? '<br><span class="badge bg-success">Current</span>'
                  : ""
              }
            </td>
            <td class="text-end">
              ${
                !isCurrentSession
                  ? `
                <button class="btn btn-sm btn-outline-danger" 
                        onclick="revokeSession(${session.session_id})"
                        title="Revoke this session">
                  <i class="bi bi-x-circle"></i> Revoke
                </button>
              `
                  : '<span class="text-muted">This device</span>'
              }
            </td>
          </tr>
        `;
      })
      .join("");
  }

  // ========================================================================
  // PARSE USER AGENT
  // ========================================================================
  function parseUserAgent(ua) {
    if (!ua) return { browser: "Unknown", os: "Unknown", device: "desktop" };

    let browser = "Unknown Browser";
    if (ua.includes("Chrome")) browser = "Chrome";
    else if (ua.includes("Firefox")) browser = "Firefox";
    else if (ua.includes("Safari") && !ua.includes("Chrome"))
      browser = "Safari";
    else if (ua.includes("Edge")) browser = "Edge";
    else if (ua.includes("Opera")) browser = "Opera";

    let os = "Unknown OS";
    if (ua.includes("Windows")) os = "Windows";
    else if (ua.includes("Mac OS")) os = "macOS";
    else if (ua.includes("Linux")) os = "Linux";
    else if (ua.includes("Android")) os = "Android";
    else if (ua.includes("iOS") || ua.includes("iPhone") || ua.includes("iPad"))
      os = "iOS";

    let device = "desktop";
    if (ua.includes("Mobile") || ua.includes("Android")) device = "mobile";
    else if (ua.includes("Tablet") || ua.includes("iPad")) device = "tablet";

    return { browser, os, device };
  }

  // ========================================================================
  // GET DEVICE ICON
  // ========================================================================
  function getDeviceIcon(device) {
    switch (device) {
      case "mobile":
        return "bi-phone";
      case "tablet":
        return "bi-tablet";
      default:
        return "bi-laptop";
    }
  }

  // ========================================================================
  // REVOKE SPECIFIC SESSION
  // ========================================================================
  window.revokeSession = async function (sessionId) {
    if (!confirm("Revoke this session? The device will be logged out.")) {
      return;
    }

    try {
      console.log(`🗑️ Revoking session: ${sessionId}`);

      const response = await fetch(
        `${API.baseURL}/privacy/revoke-session/${sessionId}`,
        {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${API.getToken()}`,
            "Content-Type": "application/json",
          },
        }
      );

      const result = await response.json();

      if (result.success) {
        Utils.showToast("Session revoked successfully", "success");
        await loadActiveSessions();
      } else {
        Utils.showToast(result.message || "Failed to revoke session", "error");
      }
    } catch (error) {
      console.error("❌ Revoke session error:", error);
      Utils.showToast("Failed to revoke session", "error");
    }
  };

  // ========================================================================
  // LOGOUT FROM ALL DEVICES
  // ========================================================================
  async function logoutAllDevices() {
    if (
      !confirm(
        "Logout from all devices? You will need to log in again on this device."
      )
    ) {
      return;
    }

    try {
      console.log("🚪 Logging out from all devices...");

      const response = await fetch(`${API.baseURL}/privacy/logout-all`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${API.getToken()}`,
          "Content-Type": "application/json",
        },
      });

      const result = await response.json();

      if (result.success) {
        Utils.showToast(
          "Logged out from all devices. Redirecting...",
          "success"
        );
        API.clearToken();

        setTimeout(() => {
          window.location.href = "index.html";
        }, 2000);
      } else {
        Utils.showToast(result.message || "Failed to logout", "error");
      }
    } catch (error) {
      console.error("❌ Logout all error:", error);
      Utils.showToast("Failed to logout from all devices", "error");
    }
  }

  // ========================================================================
  // EXPORT USER DATA (GDPR)
  // ========================================================================
  async function exportUserData() {
    try {
      console.log("📦 Exporting user data...");

      Utils.showToast("Preparing your data export...", "info");

      const response = await fetch(`${API.baseURL}/privacy/export-data`, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${API.getToken()}`,
          "Content-Type": "application/json",
        },
      });

      const result = await response.json();

      if (result.success && result.data) {
        const dataStr = JSON.stringify(result.data, null, 2);
        const dataBlob = new Blob([dataStr], { type: "application/json" });
        const url = URL.createObjectURL(dataBlob);

        const link = document.createElement("a");
        link.href = url;
        link.download = `janstro_data_export_${
          currentUser.username
        }_${Date.now()}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        Utils.showToast("Data exported successfully!", "success");
      } else {
        throw new Error(result.message || "Export failed");
      }
    } catch (error) {
      console.error("❌ Export data error:", error);
      Utils.showToast("Failed to export data", "error");
    }
  }

  // ========================================================================
  // REQUEST ACCOUNT DELETION - ✅ FIXED v4.0
  // ========================================================================
  async function requestAccountDeletion() {
    // ✅ FIX: Check if already has pending request
    if (hasPendingDeletion) {
      Utils.showToast("You already have a pending deletion request", "warning");
      return;
    }

    const reasonInput = document.getElementById("deletionReason");
    const reason = reasonInput?.value.trim() || "";

    // Validate reason length
    if (reason.length < 10) {
      Utils.showToast(
        "Please provide at least 10 characters explaining why",
        "warning"
      );
      reasonInput?.focus();
      return;
    }

    const confirmed = confirm(
      "⚠️ WARNING: This will request permanent account deletion.\n\n" +
        "Your account will be reviewed by a superadmin before deletion.\n" +
        "This action cannot be undone once approved.\n\n" +
        "Are you sure you want to continue?"
    );

    if (!confirmed) return;

    try {
      console.log("========================================");
      console.log("🗑️ Submitting deletion request...");
      console.log("========================================");

      const response = await fetch(`${API.baseURL}/privacy/request-deletion`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${API.getToken()}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ reason: reason }),
      });

      console.log("📊 HTTP Status:", response.status);

      const result = await response.json();
      console.log("📦 Response:", result);

      if (result.success || response.ok) {
        // ✅ FIX: Handle both new request and existing request
        if (result.data && result.data.already_exists) {
          Utils.showToast(
            "You already have a pending deletion request",
            "info"
          );
        } else {
          Utils.showToast("Deletion request submitted successfully", "success");
        }

        // Clear form and show pending status
        if (reasonInput) reasonInput.value = "";
        hasPendingDeletion = true;
        showDeletionPendingStatus(result.data);
      } else {
        Utils.showToast(result.message || "Failed to submit request", "error");
      }

      console.log("========================================");
    } catch (error) {
      console.error("========================================");
      console.error("❌ Request deletion error:", error);
      console.error("========================================");

      Utils.showToast("Network error. Please try again.", "error");
    }
  }

  // ========================================================================
  // SHOW DELETION PENDING STATUS - ✅ IMPROVED v4.0
  // ========================================================================
  function showDeletionPendingStatus(requestData) {
    const container = document.getElementById("deletionStatus");
    if (!container) return;

    const requestedDate = requestData?.requested_at
      ? formatDateTime(requestData.requested_at)
      : "recently";

    container.innerHTML = `
      <div class="alert alert-warning">
        <i class="bi bi-clock-history"></i>
        <strong>Deletion Request Pending</strong>
        <p class="mb-0">
          Your account deletion request (submitted ${requestedDate}) is awaiting superadmin approval.
        </p>
      </div>
    `;
    container.style.display = "block";

    // Disable the deletion form
    const reasonInput = document.getElementById("deletionReason");
    const submitBtn = document.getElementById("btnRequestDeletion");

    if (reasonInput) {
      reasonInput.disabled = true;
      reasonInput.value = "";
      reasonInput.placeholder = "Deletion request already pending...";
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="bi bi-clock-history"></i> Request Pending';
      submitBtn.classList.add("btn-secondary");
      submitBtn.classList.remove("btn-danger");
    }
  }

  // ========================================================================
  // SETUP EVENT LISTENERS
  // ========================================================================
  function setupEventListeners() {
    const btnLogoutAll = document.getElementById("btnLogoutAll");
    if (btnLogoutAll) {
      btnLogoutAll.addEventListener("click", logoutAllDevices);
    }

    const btnExportData = document.getElementById("btnExportData");
    if (btnExportData) {
      btnExportData.addEventListener("click", exportUserData);
    }

    const btnRequestDeletion = document.getElementById("btnRequestDeletion");
    if (btnRequestDeletion) {
      btnRequestDeletion.addEventListener("click", requestAccountDeletion);
    }

    const btnRefreshSessions = document.getElementById("btnRefreshSessions");
    if (btnRefreshSessions) {
      btnRefreshSessions.addEventListener("click", loadActiveSessions);
    }
  }

  // ========================================================================
  // UTILITY FUNCTIONS
  // ========================================================================
  function formatDate(dateString) {
    if (!dateString) return "N/A";
    try {
      return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    } catch (e) {
      return dateString;
    }
  }

  function formatDateTime(dateString) {
    if (!dateString) return "N/A";
    try {
      return new Date(dateString).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    } catch (e) {
      return dateString;
    }
  }

  console.log("✅ Privacy Settings Module v4.0 Loaded");
})();
