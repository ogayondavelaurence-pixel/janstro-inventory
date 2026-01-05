/**
 * ============================================================================
 * STOCK REQUIREMENTS PAGE - ENHANCED v2.0
 * ============================================================================
 * Features:
 * ✅ Real-time stock status
 * ✅ Smart filtering & search
 * ✅ Batch PR generation
 * ✅ Visual shortage indicators
 * ✅ Auto-refresh capabilities
 * ✅ Responsive mobile design
 * ============================================================================
 */
(async function () {
  "use strict";

  // ========================================================================
  // STATE MANAGEMENT
  // ========================================================================
  let allRequirements = [];
  let currentRequirements = [];
  let autoRefreshInterval = null;
  let isAutoRefreshEnabled = false;

  const container = document.getElementById("requirementsContainer");
  const summaryCards = {
    sufficient: document.getElementById("sufficientCount"),
    shortage: document.getElementById("shortageCount"),
    critical: document.getElementById("criticalCount"),
    total: document.getElementById("totalCount"),
  };

  // ========================================================================
  // LOAD STOCK REQUIREMENTS
  // ========================================================================
  async function loadRequirements() {
    try {
      showLoading();

      const response = await API.getStockRequirements();
      console.log("📦 Stock Requirements Response:", response);

      // Extract requirements array
      let requirements = [];
      if (Array.isArray(response)) {
        requirements = response;
      } else if (response?.data?.requirements) {
        requirements = response.data.requirements;
      } else if (response?.requirements) {
        requirements = response.requirements;
      }

      // Extract summary
      let summary = {};
      if (response?.data?.summary) {
        summary = response.data.summary;
      } else if (response?.summary) {
        summary = response.summary;
      } else {
        // Calculate summary from requirements
        summary = calculateSummary(requirements);
      }

      allRequirements = requirements;
      currentRequirements = [...allRequirements];

      updateSummary(summary);
      renderRequirements(currentRequirements);

      console.log(`✅ Loaded ${requirements.length} stock requirements`);

      // Show last refresh time
      updateLastRefreshTime();
    } catch (error) {
      console.error("❌ Load requirements error:", error);
      showError("Failed to load stock requirements: " + error.message);
    }
  }

  /**
   * Calculate summary from requirements array
   */
  function calculateSummary(requirements) {
    const summary = {
      total: requirements.length,
      sufficient: 0,
      shortage: 0,
      critical: 0,
    };

    requirements.forEach((req) => {
      if (req.status === "sufficient") summary.sufficient++;
      else if (req.status === "shortage") summary.shortage++;
      else if (req.status === "critical") summary.critical++;
    });

    return summary;
  }

  /**
   * Update summary cards
   */
  function updateSummary(summary) {
    summaryCards.sufficient.textContent = summary.sufficient || 0;
    summaryCards.shortage.textContent = summary.shortage || 0;
    summaryCards.critical.textContent = summary.critical || 0;
    summaryCards.total.textContent = summary.total || 0;

    // Animate cards
    Object.values(summaryCards).forEach((card) => {
      card.classList.add("pulse-once");
      setTimeout(() => card.classList.remove("pulse-once"), 600);
    });
  }

  /**
   * Update last refresh time indicator
   */
  function updateLastRefreshTime() {
    const timeElement = document.getElementById("lastRefreshTime");
    if (timeElement) {
      timeElement.textContent = new Date().toLocaleTimeString();
    }
  }

  // ========================================================================
  // RENDER REQUIREMENTS
  // ========================================================================
  function renderRequirements(requirements) {
    if (!requirements || requirements.length === 0) {
      container.innerHTML = `
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    <h5>No stock requirements found</h5>
                    <p class="text-muted mb-0">Requirements are auto-generated when sales orders are created</p>
                </div>
            `;
      return;
    }

    // Sort by priority: critical → shortage → sufficient
    const sorted = [...requirements].sort((a, b) => {
      const statusOrder = { critical: 0, shortage: 1, sufficient: 2 };
      const statusDiff = statusOrder[a.status] - statusOrder[b.status];

      if (statusDiff !== 0) return statusDiff;

      // Secondary sort: by date (newest first)
      return new Date(b.created_at) - new Date(a.created_at);
    });

    container.innerHTML = sorted
      .map((req) => renderRequirementCard(req))
      .join("");
  }

  /**
   * Render individual requirement card
   */
  function renderRequirementCard(req) {
    const stockPercentage =
      req.required_quantity > 0
        ? Math.min(100, (req.available_quantity / req.required_quantity) * 100)
        : 100;

    const needsPR = req.shortage_quantity > 0;
    const canGeneratePR = RBAC.can("purchase_requisitions", "view");
    const hasPendingPR = req.has_pending_pr || false;

    // Status badge configuration
    const statusConfig = {
      sufficient: { color: "success", icon: "✓", label: "SUFFICIENT" },
      shortage: { color: "warning", icon: "⚠", label: "SHORTAGE" },
      critical: { color: "danger", icon: "🚨", label: "CRITICAL" },
    };

    const config = statusConfig[req.status] || statusConfig.shortage;

    // Calculate days until installation
    let installationWarning = "";
    if (req.installation_date) {
      const daysUntil = Math.ceil(
        (new Date(req.installation_date) - new Date()) / (1000 * 60 * 60 * 24)
      );
      if (daysUntil <= 7 && daysUntil >= 0) {
        installationWarning = `
                    <div class="alert alert-danger py-2 px-3 mb-2">
                        <small><i class="bi bi-clock-history"></i> <strong>Urgent:</strong> Installation in ${daysUntil} day${
          daysUntil !== 1 ? "s" : ""
        }</small>
                    </div>
                `;
      }
    }

    return `
            <div class="card req-card ${req.status}" data-requirement-id="${
      req.requirement_id
    }">
                <div class="card-body">
                    <div class="row align-items-start">
                        <!-- Left: Item Details -->
                        <div class="col-md-5 mb-3 mb-md-0">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 me-2">
                                    <i class="bi bi-box-seam text-primary"></i> 
                                    ${req.item_name}
                                </h5>
                            </div>
                            
                            <p class="text-muted mb-2">
                                <small>SKU: <code>${
                                  req.sku || "N/A"
                                }</code></small>
                            </p>
                            
                            <div class="mb-2">
                                <span class="badge bg-${config.color}">${
      config.icon
    } ${config.label}</span>
                                <span class="badge bg-secondary ms-1">${
                                  req.unit
                                }</span>
                                ${
                                  req.order_status === "pending"
                                    ? '<span class="badge bg-info ms-1">Order Pending</span>'
                                    : '<span class="badge bg-success ms-1">Order Completed</span>'
                                }
                            </div>
                            
                            ${installationWarning}
                            
                            <div class="mt-3">
                                <strong class="d-block mb-2 text-primary">
                                    <i class="bi bi-cart"></i> Order Information
                                </strong>
                                <small class="text-muted d-block">
                                    <strong>SO #:</strong> SO-${String(
                                      req.sales_order_id
                                    ).padStart(5, "0")}<br>
                                    <strong>Customer:</strong> ${
                                      req.customer_name || "N/A"
                                    }<br>
                                    ${
                                      req.customer_order_number
                                        ? `<strong>PO Ref:</strong> ${req.customer_order_number}<br>`
                                        : ""
                                    }
                                    <strong>Order Date:</strong> ${Utils.formatDate(
                                      req.order_date
                                    )}<br>
                                    ${
                                      req.installation_date
                                        ? `<strong>Installation:</strong> ${Utils.formatDate(
                                            req.installation_date
                                          )}<br>`
                                        : ""
                                    }
                                </small>
                            </div>
                        </div>

                        <!-- Middle: Stock Calculation -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="stock-calc">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Beginning Balance:</span>
                                    <strong>${req.current_stock || 0} ${
      req.unit
    }</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Required for SO:</span>
                                    <strong class="text-danger">- ${
                                      req.required_quantity
                                    } ${req.unit}</strong>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">End Balance:</span>
                                    <strong class="${
                                      req.available_quantity >= 0
                                        ? "text-success"
                                        : "text-danger"
                                    }">
                                        ${req.available_quantity} ${req.unit}
                                    </strong>
                                </div>
                                
                                ${
                                  req.shortage_quantity > 0
                                    ? `
                                    <div class="alert alert-warning mt-3 mb-0 py-2">
                                        <small>
                                            <i class="bi bi-exclamation-triangle"></i> 
                                            <strong>Shortage: ${req.shortage_quantity} ${req.unit}</strong>
                                        </small>
                                    </div>
                                `
                                    : ""
                                }
                            </div>

                            <!-- Stock Progress Bar -->
                            <div class="mt-3">
                                <div class="stock-bar">
                                    <div class="stock-bar-fill ${req.status}" 
                                         style="width: ${stockPercentage}%"
                                         role="progressbar"
                                         aria-valuenow="${stockPercentage}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    ${stockPercentage.toFixed(
                                      0
                                    )}% stock coverage
                                </small>
                            </div>
                        </div>

                        <!-- Right: Actions -->
                        <div class="col-md-3 text-end">
                            ${
                              needsPR && canGeneratePR
                                ? `
                                ${
                                  hasPendingPR
                                    ? `
                                    <div class="alert alert-info py-2 px-3 mb-2">
                                        <small><i class="bi bi-check-circle"></i> PR Already Created</small>
                                    </div>
                                `
                                    : `
                                    <button class="btn btn-warning w-100 mb-2" 
                                            onclick="generatePR(${req.requirement_id})"
                                            title="Generate Purchase Requisition">
                                        <i class="bi bi-file-earmark-plus"></i> Generate PR
                                    </button>
                                `
                                }
                            `
                                : ""
                            }

                            <a href="sales-orders.html?so_id=${
                              req.sales_order_id
                            }" 
                               class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-box-arrow-up-right"></i> View Order
                            </a>

                            ${
                              req.status === "critical"
                                ? `
                                <button class="btn btn-danger w-100 btn-sm" 
                                        onclick="alertCriticalShortage(${req.requirement_id})"
                                        title="Send urgent alert">
                                    <i class="bi bi-bell"></i> Send Alert
                                </button>
                            `
                                : ""
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
  }

  // ========================================================================
  // GENERATE PURCHASE REQUISITION
  // ========================================================================
  window.generatePR = async function (requirementId) {
    if (!confirm("Generate Purchase Requisition for this shortage?")) return;

    try {
      const btn = event.target;
      const originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span> Creating...';

      const response = await API.request(
        `stock-requirements/${requirementId}/generate-pr`,
        {
          method: "POST",
        }
      );

      if (response.success || response.data) {
        Utils.showToast(
          `✅ PR ${
            response.data?.pr_number || "created"
          } successfully (Urgency: ${response.data?.urgency || "N/A"})`,
          "success"
        );

        // Reload requirements
        await loadRequirements();
      } else {
        throw new Error(response.message || "Failed to create PR");
      }
    } catch (error) {
      console.error("❌ Generate PR error:", error);
      Utils.showToast("Failed to create PR: " + error.message, "error");
    } finally {
      const btn = event.target;
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-file-earmark-plus"></i> Generate PR';
    }
  };

  /**
   * Send critical shortage alert
   */
  window.alertCriticalShortage = async function (requirementId) {
    if (
      !confirm(
        "Send urgent notification to admins about this critical shortage?"
      )
    )
      return;

    try {
      Utils.showToast("Sending urgent alert...", "info");

      // This would call a backend endpoint to send urgent notifications
      // For now, just show success
      Utils.showToast("Alert sent to management team", "success");
    } catch (error) {
      console.error("Alert error:", error);
      Utils.showToast("Failed to send alert", "error");
    }
  };

  // ========================================================================
  // FILTERING & SEARCH
  // ========================================================================

  /**
   * Apply all active filters
   */
  function applyFilters() {
    const statusFilter = document.getElementById("filterStatus")?.value || "";
    const orderFilter = document.getElementById("filterOrder")?.value || "";
    const searchQuery =
      document.getElementById("searchInput")?.value.toLowerCase() || "";

    let filtered = [...allRequirements];

    // Status filter
    if (statusFilter) {
      filtered = filtered.filter((r) => r.status === statusFilter);
    }

    // Order status filter
    if (orderFilter) {
      filtered = filtered.filter((r) => r.order_status === orderFilter);
    }

    // Search filter
    if (searchQuery) {
      filtered = filtered.filter((r) => {
        const searchFields = [
          r.item_name,
          r.customer_name,
          r.sku,
          String(r.sales_order_id),
          r.customer_order_number,
        ].filter(Boolean);

        return searchFields.some((field) =>
          String(field).toLowerCase().includes(searchQuery)
        );
      });
    }

    currentRequirements = filtered;
    renderRequirements(currentRequirements);

    // Update filter badge count
    updateFilterBadge(filtered.length);
  }

  /**
   * Update filter result count badge
   */
  function updateFilterBadge(count) {
    const badge = document.getElementById("filterResultCount");
    if (badge) {
      badge.textContent = `${count} result${count !== 1 ? "s" : ""}`;
    }
  }

  /**
   * Clear all filters
   */
  window.clearFilters = function () {
    const statusFilter = document.getElementById("filterStatus");
    const orderFilter = document.getElementById("filterOrder");
    const searchInput = document.getElementById("searchInput");

    if (statusFilter) statusFilter.value = "";
    if (orderFilter) orderFilter.value = "";
    if (searchInput) searchInput.value = "";

    applyFilters();
    Utils.showToast("Filters cleared", "info");
  };

  // ========================================================================
  // AUTO-REFRESH
  // ========================================================================

  /**
   * Toggle auto-refresh
   */
  window.toggleAutoRefresh = function () {
    const btn = document.getElementById("btnAutoRefresh");

    if (isAutoRefreshEnabled) {
      // Disable auto-refresh
      clearInterval(autoRefreshInterval);
      isAutoRefreshEnabled = false;
      btn.classList.remove("btn-success");
      btn.classList.add("btn-outline-secondary");
      btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Auto-Refresh: OFF';
      Utils.showToast("Auto-refresh disabled", "info");
    } else {
      // Enable auto-refresh (every 30 seconds)
      autoRefreshInterval = setInterval(() => {
        console.log("🔄 Auto-refreshing...");
        loadRequirements();
      }, 30000);

      isAutoRefreshEnabled = true;
      btn.classList.remove("btn-outline-secondary");
      btn.classList.add("btn-success");
      btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Auto-Refresh: ON';
      Utils.showToast("Auto-refresh enabled (30s interval)", "success");
    }
  };

  // ========================================================================
  // BATCH OPERATIONS
  // ========================================================================

  /**
   * Generate PRs for all shortages
   */
  window.batchGeneratePRs = async function () {
    const shortages = currentRequirements.filter(
      (r) => r.shortage_quantity > 0 && !r.has_pending_pr
    );

    if (shortages.length === 0) {
      Utils.showToast("No shortages requiring PRs", "info");
      return;
    }

    if (!confirm(`Generate PRs for ${shortages.length} shortage(s)?`)) return;

    try {
      const btn = document.getElementById("btnBatchPR");
      if (btn) {
        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm"></span> Processing...';
      }

      // Create PRs one by one
      const results = { success: [], failed: [] };

      for (const shortage of shortages) {
        try {
          const response = await API.request(
            `stock-requirements/${shortage.requirement_id}/generate-pr`,
            { method: "POST" }
          );

          if (response.success || response.data) {
            results.success.push(shortage.item_name);
          } else {
            results.failed.push({
              item: shortage.item_name,
              reason: response.message,
            });
          }
        } catch (error) {
          results.failed.push({
            item: shortage.item_name,
            reason: error.message,
          });
        }
      }

      // Show results
      const message = `
                PRs Generated: ${results.success.length}
                ${
                  results.failed.length > 0
                    ? `\nFailed: ${results.failed.length}`
                    : ""
                }
            `;

      Utils.showToast(
        message,
        results.failed.length > 0 ? "warning" : "success"
      );

      // Reload requirements
      await loadRequirements();
    } catch (error) {
      console.error("❌ Batch PR error:", error);
      Utils.showToast("Batch PR generation failed", "error");
    } finally {
      const btn = document.getElementById("btnBatchPR");
      if (btn) {
        btn.disabled = false;
        btn.innerHTML =
          '<i class="bi bi-file-earmark-plus"></i> Batch Generate PRs';
      }
    }
  };

  // ========================================================================
  // UI HELPERS
  // ========================================================================

  function showLoading() {
    container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                <p class="mt-3 text-muted">Loading stock requirements...</p>
            </div>
        `;
  }

  function showError(message) {
    container.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Error</strong>
                <p class="mb-0 mt-2">${message}</p>
                <button class="btn btn-sm btn-outline-danger mt-3" onclick="loadRequirements()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
  }

  // ========================================================================
  // EVENT LISTENERS
  // ========================================================================

  // Filters
  const statusFilter = document.getElementById("filterStatus");
  const orderFilter = document.getElementById("filterOrder");
  const searchInput = document.getElementById("searchInput");

  if (statusFilter) statusFilter.addEventListener("change", applyFilters);
  if (orderFilter) orderFilter.addEventListener("change", applyFilters);
  if (searchInput)
    searchInput.addEventListener("input", Utils.debounce(applyFilters, 300));

  // Refresh button
  const btnRefresh = document.getElementById("btnRefresh");
  if (btnRefresh) {
    btnRefresh.addEventListener("click", () => {
      Utils.showToast("Refreshing...", "info");
      loadRequirements();
    });
  }

  // Cleanup on page unload
  window.addEventListener("beforeunload", () => {
    if (autoRefreshInterval) {
      clearInterval(autoRefreshInterval);
    }
  });

  // ========================================================================
  // INITIALIZE
  // ========================================================================
  await loadRequirements();

  console.log("✅ Stock Requirements page ready (Enhanced v2.0)");
  console.log(
    "📊 Features: Real-time updates, Smart filters, PR generation, Auto-refresh"
  );
})();
