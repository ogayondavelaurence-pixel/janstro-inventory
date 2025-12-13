/**
 * Stock Requirements Page Handler
 */
(async function () {
  "use strict";

  const tableBody = document.getElementById("requirementsBody");
  const summaryCards = {
    total: document.getElementById("totalRequirements"),
    sufficient: document.getElementById("sufficientCount"),
    shortage: document.getElementById("shortageCount"),
    critical: document.getElementById("criticalCount"),
  };

  async function loadStockRequirements() {
    try {
      ErrorHandler.showLoading("Loading stock requirements...");

      const response = await API.getStockRequirements();

      // Response structure: { requirements: [], summary: {} }
      const data = response.requirements || response.data?.requirements || [];
      const summary = response.summary || response.data?.summary || {};

      if (!Array.isArray(data)) {
        throw new Error("Invalid response structure");
      }

      renderTable(data);
      updateSummary(summary, data.length);

      ErrorHandler.hideLoading();
    } catch (error) {
      console.error("Load requirements error:", error);
      ErrorHandler.hideLoading();
      ErrorHandler.showError("Failed to load stock requirements");
    }
  }

  function renderTable(requirements) {
    if (!tableBody) return;

    if (requirements.length === 0) {
      tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.5;"></i>
                        <p class="mt-3 text-muted">No stock requirements found</p>
                    </td>
                </tr>
            `;
      return;
    }

    tableBody.innerHTML = requirements
      .map(
        (req) => `
            <tr>
                <td>SR-${String(req.requirement_id).padStart(6, "0")}</td>
                <td>SO-${String(req.sales_order_id).padStart(6, "0")}</td>
                <td>${Utils.sanitizeHTML(req.customer_name || "N/A")}</td>
                <td>${Utils.sanitizeHTML(req.item_name)}</td>
                <td>${req.required_quantity}</td>
                <td>${req.available_quantity}</td>
                <td class="${
                  req.shortage_quantity > 0 ? "text-danger fw-bold" : ""
                }">${req.shortage_quantity}</td>
                <td>${req.current_stock}</td>
                <td>
                    <span class="badge bg-${req.status_color}">
                        ${req.status.toUpperCase()}
                    </span>
                </td>
                <td>
                    ${
                      req.needs_po
                        ? `
                        <button class="btn btn-sm btn-primary" onclick="createPOForRequirement(${req.requirement_id}, ${req.item_id}, ${req.shortage_quantity})">
                            <i class="bi bi-cart-plus"></i> Create PO
                        </button>
                    `
                        : `
                        <span class="text-success"><i class="bi bi-check-circle"></i> Ready</span>
                    `
                    }
                </td>
            </tr>
        `
      )
      .join("");
  }

  function updateSummary(summary, total) {
    if (summaryCards.total)
      summaryCards.total.textContent = summary.total || total;
    if (summaryCards.sufficient)
      summaryCards.sufficient.textContent = summary.sufficient || 0;
    if (summaryCards.shortage)
      summaryCards.shortage.textContent = summary.shortage || 0;
    if (summaryCards.critical)
      summaryCards.critical.textContent = summary.critical || 0;
  }

  window.createPOForRequirement = function (reqId, itemId, quantity) {
    // Redirect to PO creation with pre-filled data
    sessionStorage.setItem(
      "po_prefill",
      JSON.stringify({
        item_id: itemId,
        quantity: quantity,
        source: "stock_requirement",
      })
    );
    window.location.href = "purchase-orders.html";
  };

  // Initialize
  await loadStockRequirements();

  // Auto-refresh every 30 seconds
  setInterval(loadStockRequirements, 30000);

  console.log("✅ Stock Requirements module loaded");
})();
