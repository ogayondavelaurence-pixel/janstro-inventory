/**
 * JANSTRO IMS - Complete Accessibility Fix
 */
(function () {
  "use strict";

  // Fix all button accessibility issues
  function fixButtons() {
    document
      .querySelectorAll("button:not([aria-label]):not([title])")
      .forEach((btn) => {
        if (!btn.textContent.trim()) {
          const icon = btn.querySelector("i");
          if (icon) {
            const cls = icon.className;
            if (cls.includes("bi-arrow-clockwise"))
              btn.setAttribute("aria-label", "Refresh data");
            else if (cls.includes("bi-list"))
              btn.setAttribute("aria-label", "Toggle navigation menu");
            else if (cls.includes("bi-download"))
              btn.setAttribute("aria-label", "Download/Export");
            else if (cls.includes("bi-eye"))
              btn.setAttribute("aria-label", "View details");
            else if (cls.includes("bi-pencil"))
              btn.setAttribute("aria-label", "Edit");
            else if (cls.includes("bi-trash"))
              btn.setAttribute("aria-label", "Delete");
            else if (cls.includes("bi-check"))
              btn.setAttribute("aria-label", "Confirm");
            else if (cls.includes("bi-x"))
              btn.setAttribute("aria-label", "Cancel");
            else btn.setAttribute("aria-label", "Action button");
          }
        }
      });
  }

  // Fix select accessibility
  function fixSelects() {
    document
      .querySelectorAll("select:not([aria-label]):not([title])")
      .forEach((sel) => {
        const label = sel.previousElementSibling;
        if (!label || label.tagName !== "LABEL") {
          const placeholder = sel.options[0]?.text || "Select option";
          sel.setAttribute("aria-label", placeholder);
        }
      });
  }

  // Fix input[type=month] fallback for Firefox/Safari
  function fixMonthInputs() {
    const monthInputs = document.querySelectorAll('input[type="month"]');
    monthInputs.forEach((input) => {
      // Test if browser supports type="month"
      if (input.type !== "month") {
        // Fallback: convert to text with placeholder
        input.type = "text";
        input.placeholder = "YYYY-MM";
        input.pattern = "\\d{4}-\\d{2}";
      }
    });
  }

  // Mobile menu handler
  function initMobileMenu() {
    let toggleBtn = document.querySelector(".mobile-menu-toggle");
    const sidebar = document.querySelector(".sidebar");

    if (!toggleBtn && sidebar && window.innerWidth <= 768) {
      toggleBtn = document.createElement("button");
      toggleBtn.className = "mobile-menu-toggle";
      toggleBtn.setAttribute("aria-label", "Toggle navigation menu");
      toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
      document.body.insertBefore(toggleBtn, document.body.firstChild);
    }

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        sidebar.classList.toggle("mobile-show");
        toggleBtn.setAttribute(
          "aria-expanded",
          sidebar.classList.contains("mobile-show")
        );
      });

      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          !sidebar.contains(e.target) &&
          !toggleBtn.contains(e.target) &&
          sidebar.classList.contains("mobile-show")
        ) {
          sidebar.classList.remove("mobile-show");
          toggleBtn.setAttribute("aria-expanded", "false");
        }
      });
    }
  }

  // Initialize all fixes
  function init() {
    fixButtons();
    fixSelects();
    fixMonthInputs();
    initMobileMenu();
    console.log("✅ Accessibility fixes applied");
  }

  // Run on load and after DOM changes
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Re-run fixes after dynamic content loads
  const observer = new MutationObserver(() => {
    fixButtons();
    fixSelects();
  });

  observer.observe(document.body, { childList: true, subtree: true });
})();
