/**
 * ============================================================================
 * DELETION APPROVALS MODULE v5.1 - COMPLETE FIX
 * ============================================================================
 * FIXES:
 * ✅ Token sent in Authorization header
 * ✅ Functions exposed to global scope for HTML onclick
 * ✅ Proper error handling
 * ============================================================================
 */

(async function () {
  "use strict";

  let currentUser = null;
  let deletionRequests = [];

  // ========================================================================
  // INITIALIZATION
  // ========================================================================
  document.addEventListener("DOMContentLoaded", async () => {
    console.log("🔧 Deletion Approvals v5.1: Initializing...");

    await verifyAccess();
    await loadRequests();
    setupEventListeners();
  });

  // ========================================================================
  // VERIFY SUPERADMIN ACCESS
  // ========================================================================
  async function verifyAccess() {
    try {
      console.log("========================================");
      console.log("🔐 AUTHENTICATION CHECK");
      console.log("========================================");

      const token = API.getToken();
      console.log("Token exists:", !!token);
      if (token) {
        console.log("Token length:", token.length);
        console.log("Token preview:", token.substring(0, 20) + "...");
      }
      console.log("========================================");

      if (!token) {
        console.error("❌ No token found");
        Utils.showToast("Session expired. Please login.", "error");
        setTimeout(() => (window.location.href = "index.html"), 2000);
        return;
      }

      console.log("🔐 Verifying superadmin access...");
      const response = await API.getCurrentUser();

      console.log("👤 User role:", response.data?.role || "unknown");

      if (response.success && response.data) {
        currentUser = response.data;

        const userRole = (
          currentUser.role ||
          currentUser.role_name ||
          ""
        ).toLowerCase();

        if (userRole !== "superadmin") {
          console.error("❌ Access denied: Not superadmin");
          Utils.showToast("Access denied. Superadmin only.", "error");
          setTimeout(() => (window.location.href = "dashboard.html"), 2000);
          return;
        }

        console.log("✅ Superadmin access verified");
      }
    } catch (error) {
      console.error("❌ Access verification error:", error);
      Utils.showToast("Authentication failed", "error");
      setTimeout(() => (window.location.href = "index.html"), 2000);
    }
  }

  // ========================================================================
  // LOAD DELETION REQUESTS
  // ========================================================================
  async function loadRequests() {
    try {
      console.log("========================================");
      console.log("📥 Loading deletion requests v5.1...");
      console.log("========================================");

      const tbody = document.getElementById("requestsTable");
      if (!tbody) {
        console.error("❌ Table body not found");
        return;
      }

      tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;

      const token = API.getToken();
      if (!token) {
        throw new Error("No authentication token");
      }
      console.log("✅ Token verified, making request...");

      const url = `${API.baseURL}/admin/deletion-requests`;
      console.log("🔗 Request URL:", url);
      console.log("🔑 Using Authorization: Bearer [token]");

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      console.log("📊 HTTP Status:", response.status);
      console.log("📊 Status Text:", response.statusText);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ HTTP Error Response:", errorText);
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      let result;
      try {
        result = await response.json();
      } catch (e) {
        const text = await response.text();
        console.error("❌ JSON Parse Error:", e);
        console.error("❌ Response Text:", text);
        throw new Error("Invalid JSON response");
      }

      console.log("📦 API Response:", result);

      if (!result.success && !Array.isArray(result)) {
        throw new Error(result.message || "Failed to load requests");
      }

      deletionRequests = Array.isArray(result) ? result : result.data || [];
      console.log("✅ Loaded", deletionRequests.length, "deletion requests");
      console.log("========================================");

      renderRequests();
      updateStats();
    } catch (error) {
      console.error("========================================");
      console.error("❌ Load requests error:", error);
      console.error("========================================");

      const tbody = document.getElementById("requestsTable");
      if (tbody) {
        tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Failed to load deletion requests: ${error.message}
                        </td>
                    </tr>
                `;
      }

      Utils.showToast("Failed to load deletion requests", "error");
    } finally {
      console.log("✅ Loading state reset");
    }
  }

  // ========================================================================
  // RENDER REQUESTS TABLE
  // ========================================================================
  function renderRequests() {
    const tbody = document.getElementById("requestsTable");
    if (!tbody) return;

    console.log("🎨 Rendering", deletionRequests.length, "requests...");

    const pending = deletionRequests.filter((r) => r.status === "pending");

    if (pending.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle"></i>
                        No pending deletion requests
                    </td>
                </tr>
            `;
      return;
    }

    tbody.innerHTML = pending
      .map((req) => {
        const statusBadge =
          {
            pending: '<span class="badge bg-warning">Pending</span>',
            approved: '<span class="badge bg-success">Approved</span>',
            rejected: '<span class="badge bg-danger">Rejected</span>',
          }[req.status] || '<span class="badge bg-secondary">Unknown</span>';

        return `
                <tr>
                    <td>
                        <strong>${req.username || "Unknown"}</strong>
                        <br>
                        <small class="text-muted">${req.name || "N/A"}</small>
                    </td>
                    <td>${req.email || "N/A"}</td>
                    <td>
                        <small>${req.reason || "No reason provided"}</small>
                    </td>
                    <td>
                        <small>${Utils.formatDateTime(req.requested_at)}</small>
                    </td>
                    <td>${statusBadge}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-success me-1" 
                                onclick="window.approveRequest(${
                                  req.request_id
                                })"
                                title="Approve deletion">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="window.rejectRequest(${
                                  req.request_id
                                })"
                                title="Reject deletion">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </td>
                </tr>
            `;
      })
      .join("");

    console.log("✅ Requests table rendered");
  }

  // ========================================================================
  // UPDATE STATISTICS
  // ========================================================================
  function updateStats() {
    const stats = deletionRequests.reduce(
      (acc, req) => {
        acc.total++;
        if (req.status === "pending") acc.pending++;
        if (req.status === "approved") acc.approved++;
        if (req.status === "rejected") acc.rejected++;
        return acc;
      },
      { total: 0, pending: 0, approved: 0, rejected: 0 }
    );

    console.log("📊 Statistics:", stats);

    const statPending = document.getElementById("statPending");
    const statApproved = document.getElementById("statApproved");
    const statRejected = document.getElementById("statRejected");

    if (statPending) statPending.textContent = stats.pending;
    if (statApproved) statApproved.textContent = stats.approved;
    if (statRejected) statRejected.textContent = stats.rejected;
  }

  // ========================================================================
  // APPROVE REQUEST - ✅ FIXED v5.1
  // ========================================================================
  async function approveRequest(requestId) {
    if (
      !confirm(
        "⚠️ APPROVE DELETION?\n\nThis will permanently delete the user account.\n\nThis action cannot be undone."
      )
    ) {
      return;
    }

    try {
      console.log("========================================");
      console.log("✅ Approving deletion request:", requestId);
      console.log("========================================");

      const token = API.getToken();
      if (!token) {
        console.error("❌ No authentication token found");
        Utils.showToast("Session expired. Please login again.", "error");
        setTimeout(() => (window.location.href = "index.html"), 2000);
        return;
      }
      console.log("✅ Token verified");

      // ✅ FIX: Send token in Authorization header
      const response = await fetch(
        `${API.baseURL}/admin/deletion-requests/${requestId}/approve`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        }
      );

      console.log("📊 Approve HTTP Status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ HTTP Error:", errorText);
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }

      const result = await response.json();
      console.log("📦 Approve response:", result);
      console.log("========================================");

      if (result.success) {
        Utils.showToast("Account deleted successfully", "success");
        await loadRequests();
      } else {
        Utils.showToast(result.message || "Approval failed", "error");
      }
    } catch (error) {
      console.error("========================================");
      console.error("❌ Approve request error:", error);
      console.error("========================================");
      Utils.showToast("Failed to approve deletion: " + error.message, "error");
    }
  }

  // ========================================================================
  // REJECT REQUEST - ✅ FIXED v5.1
  // ========================================================================
  async function rejectRequest(requestId) {
    const reason = prompt("Enter rejection reason (optional):");

    try {
      console.log("========================================");
      console.log("❌ Rejecting deletion request:", requestId);
      console.log("========================================");

      const token = API.getToken();
      if (!token) {
        console.error("❌ No authentication token found");
        Utils.showToast("Session expired. Please login again.", "error");
        setTimeout(() => (window.location.href = "index.html"), 2000);
        return;
      }
      console.log("✅ Token verified");

      // ✅ FIX: Send token in Authorization header
      const response = await fetch(
        `${API.baseURL}/admin/deletion-requests/${requestId}/reject`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ reason: reason || "No reason provided" }),
        }
      );

      console.log("📊 Reject HTTP Status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ HTTP Error:", errorText);
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }

      const result = await response.json();
      console.log("📦 Reject response:", result);
      console.log("========================================");

      if (result.success) {
        Utils.showToast("Deletion request rejected", "success");
        await loadRequests();
      } else {
        Utils.showToast(result.message || "Rejection failed", "error");
      }
    } catch (error) {
      console.error("========================================");
      console.error("❌ Reject request error:", error);
      console.error("========================================");
      Utils.showToast("Failed to reject deletion: " + error.message, "error");
    }
  }

  // ========================================================================
  // SETUP EVENT LISTENERS
  // ========================================================================
  function setupEventListeners() {
    const btnRefresh = document.getElementById("btnRefresh");
    if (btnRefresh) {
      btnRefresh.addEventListener("click", loadRequests);
    }
  }

  // ========================================================================
  // ✅ EXPOSE FUNCTIONS TO GLOBAL SCOPE (for HTML onclick)
  // ========================================================================
  window.approveRequest = approveRequest;
  window.rejectRequest = rejectRequest;

  console.log(
    "✅ Deletion Approvals Module v5.1 Loaded (Token Fix + Global Scope)"
  );
})();
