/**
 * JANSTRO IMS - Accessibility & Mobile Menu Fixes
 * Fixes all button/input warnings and mobile menu
 */

(function () {
  "use strict";

  // Fix buttons without aria-labels
  document.querySelectorAll("button:not([aria-label])").forEach((btn) => {
    if (!btn.textContent.trim() && !btn.querySelector("[aria-label]")) {
      const icon = btn.querySelector("i");
      if (icon) {
        const classes = icon.className;
        if (classes.includes("bi-arrow-clockwise"))
          btn.setAttribute("aria-label", "Refresh");
        else if (classes.includes("bi-list"))
          btn.setAttribute("aria-label", "Toggle menu");
        else if (classes.includes("bi-download"))
          btn.setAttribute("aria-label", "Download");
        else if (classes.includes("bi-eye"))
          btn.setAttribute("aria-label", "View");
        else if (classes.includes("bi-pencil"))
          btn.setAttribute("aria-label", "Edit");
        else if (classes.includes("bi-trash"))
          btn.setAttribute("aria-label", "Delete");
        else btn.setAttribute("aria-label", "Action button");
      }
    }
  });

  // Fix select without aria-label
  document.querySelectorAll("select:not([aria-label])").forEach((sel) => {
    if (
      !sel.previousElementSibling ||
      sel.previousElementSibling.tagName !== "LABEL"
    ) {
      sel.setAttribute("aria-label", sel.id || "Selection dropdown");
    }
  });

  // Mobile menu functionality
  function initMobileMenu() {
    let toggleBtn = document.querySelector(".mobile-menu-toggle");
    const sidebar = document.querySelector(".sidebar");

    if (!toggleBtn && sidebar) {
      toggleBtn = document.createElement("button");
      toggleBtn.className = "mobile-menu-toggle";
      toggleBtn.setAttribute("aria-label", "Toggle menu");
      toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
      document.body.insertBefore(toggleBtn, document.body.firstChild);
    }

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        sidebar.classList.toggle("mobile-show");
      });

      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          !sidebar.contains(e.target) &&
          !toggleBtn.contains(e.target) &&
          sidebar.classList.contains("mobile-show")
        ) {
          sidebar.classList.remove("mobile-show");
        }
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMobileMenu);
  } else {
    initMobileMenu();
  }

  console.log("✅ Accessibility fixes applied");
})();
