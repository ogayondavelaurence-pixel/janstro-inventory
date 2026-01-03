/* Path: frontend/assets/js/accessibility-fix.js */
(function (window, document) {
  "use strict";
  const Accessibility = {
    /**
     * Initialize all accessibility features
     */
    init() {
      this.setupKeyboardNavigation();
      this.setupFocusManagement();
      this.setupAriaLabels();
      this.setupMobileAccessibility();
      this.setupFormAccessibility();
      this.setupModalAccessibility();
      this.setupSkipLinks();
      console.log("✅ Accessibility Module v1.0 Loaded (WCAG 2.1 AA)");
    },
    /**
     * Setup keyboard navigation
     * Allows keyboard-only users to navigate the system
     */
    setupKeyboardNavigation() {
      // ESC key to close modals
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          const openModal = document.querySelector(".modal.show");
          if (openModal) {
            const closeBtn = openModal.querySelector(".btn-close");
            if (closeBtn) closeBtn.click();
          }
        }
      });

      // Tab key trap for modals
      document.addEventListener("keydown", (e) => {
        if (e.key === "Tab") {
          const modal = document.querySelector(".modal.show");
          if (modal) {
            const focusableElements = modal.querySelectorAll(
              'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
              lastElement.focus();
              e.preventDefault();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
              firstElement.focus();
              e.preventDefault();
            }
          }
        }
      });

      // Spacebar/Enter to activate buttons
      document.addEventListener("keydown", (e) => {
        if (e.key === " " || e.key === "Enter") {
          const target = e.target;
          if (
            target.tagName === "BUTTON" ||
            target.getAttribute("role") === "button"
          ) {
            e.preventDefault();
            target.click();
          }
        }
      });
    },

    /**
     * Setup focus management
     * Ensures visible focus indicators and proper focus flow
     */
    setupFocusManagement() {
      // Ensure focus is visible (override Bootstrap defaults)
      const style = document.createElement("style");
      style.textContent = `
    *:focus {
      outline: 2px solid #667eea !important;
      outline-offset: 2px !important;
    }
    
    button:focus, 
    a:focus, 
    input:focus, 
    select:focus, 
    textarea:focus {
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3) !important;
    }
    
    .skip-link {
      position: absolute;
      top: -40px;
      left: 0;
      background: #667eea;
      color: white;
      padding: 8px 16px;
      text-decoration: none;
      z-index: 10000;
      border-radius: 0 0 4px 0;
    }
    
    .skip-link:focus {
      top: 0;
    }
  `;
      document.head.appendChild(style);

      // Auto-focus first input in modals
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.classList && node.classList.contains("modal")) {
              setTimeout(() => {
                const firstInput = node.querySelector(
                  'input:not([type="hidden"]), select, textarea'
                );
                if (firstInput) firstInput.focus();
              }, 300);
            }
          });
        });
      });

      observer.observe(document.body, { childList: true, subtree: true });
    },

    /**
     * Setup ARIA labels for dynamic content
     * Ensures screen readers can understand the interface
     */
    setupAriaLabels() {
      // Add ARIA labels to tables
      document.querySelectorAll("table").forEach((table) => {
        if (!table.hasAttribute("aria-label")) {
          const caption =
            table.querySelector("caption")?.textContent ||
            table
              .closest(".card")
              ?.querySelector(".card-header h3, .card-header h5")
              ?.textContent?.trim() ||
            "Data table";
          table.setAttribute("aria-label", caption);
        }
      });

      // Add ARIA labels to buttons without text
      document.querySelectorAll("button").forEach((btn) => {
        if (!btn.textContent.trim() && !btn.hasAttribute("aria-label")) {
          const icon = btn.querySelector("i");
          if (icon) {
            const iconClass = icon.className;
            let label = "Button";

            if (iconClass.includes("bi-pencil")) label = "Edit";
            else if (iconClass.includes("bi-trash")) label = "Delete";
            else if (iconClass.includes("bi-eye")) label = "View";
            else if (iconClass.includes("bi-plus")) label = "Add";
            else if (iconClass.includes("bi-arrow-clockwise"))
              label = "Refresh";
            else if (iconClass.includes("bi-download")) label = "Download";
            else if (iconClass.includes("bi-check")) label = "Confirm";
            else if (iconClass.includes("bi-x")) label = "Close";

            btn.setAttribute("aria-label", label);
          }
        }
      });

      // Add ARIA live regions for dynamic updates
      document
        .querySelectorAll("#inventoryTable, #poTable, #soTable")
        .forEach((table) => {
          if (!table.hasAttribute("aria-live")) {
            table.setAttribute("aria-live", "polite");
            table.setAttribute("aria-atomic", "false");
          }
        });
    },

    /**
     * Setup mobile accessibility
     * Ensures touch targets are large enough and properly spaced
     */
    setupMobileAccessibility() {
      // Ensure minimum touch target size (44x44px - WCAG 2.1)
      const style = document.createElement("style");
      style.textContent = `
    @media (max-width: 768px) {
      button, 
      a, 
      input[type="checkbox"], 
      input[type="radio"], 
      .btn {
        min-width: 44px !important;
        min-height: 44px !important;
      }
      
      .table td, 
      .table th {
        padding: 12px 8px !important;
      }
      
      /* Increase spacing between clickable elements */
      .btn + .btn {
        margin-left: 8px;
      }
    }
  `;
      document.head.appendChild(style);
    },

    /**
     * Setup form accessibility
     * Ensures forms are properly labeled and validated
     */
    setupFormAccessibility() {
      // Auto-link labels to inputs
      document.querySelectorAll("input, select, textarea").forEach((input) => {
        if (!input.id && !input.hasAttribute("aria-label")) {
          const label = input
            .closest(".form-group, .mb-3")
            ?.querySelector("label");
          if (label && label.textContent.trim()) {
            input.setAttribute("aria-label", label.textContent.trim());
          }
        }
      });

      // Add required indicators
      document
        .querySelectorAll(
          "input[required], select[required], textarea[required]"
        )
        .forEach((input) => {
          if (!input.hasAttribute("aria-required")) {
            input.setAttribute("aria-required", "true");
          }
        });

      // Add invalid indicators
      document.addEventListener(
        "invalid",
        (e) => {
          e.target.setAttribute("aria-invalid", "true");
        },
        true
      );

      document.addEventListener("input", (e) => {
        if (e.target.validity.valid) {
          e.target.removeAttribute("aria-invalid");
        }
      });
    },

    /**
     * Setup modal accessibility
     * Ensures modals are accessible to keyboard and screen reader users
     */
    setupModalAccessibility() {
      document.querySelectorAll(".modal").forEach((modal) => {
        if (!modal.hasAttribute("role")) {
          modal.setAttribute("role", "dialog");
          modal.setAttribute("aria-modal", "true");
        }

        const title = modal.querySelector(".modal-title");
        if (title && title.id) {
          modal.setAttribute("aria-labelledby", title.id);
        }
      });
    },

    /**
     * Setup skip links
     * Allows keyboard users to skip navigation
     */
    setupSkipLinks() {
      if (!document.querySelector(".skip-link")) {
        const skipLink = document.createElement("a");
        skipLink.href = "#main-content";
        skipLink.className = "skip-link";
        skipLink.textContent = "Skip to main content";
        document.body.insertBefore(skipLink, document.body.firstChild);

        // Add ID to main content if missing
        const mainContent = document.querySelector(".main-content");
        if (mainContent && !mainContent.id) {
          mainContent.id = "main-content";
          mainContent.setAttribute("tabindex", "-1");
        }

        skipLink.addEventListener("click", (e) => {
          e.preventDefault();
          mainContent.focus();
        });
      }
    },

    /**
     * Announce message to screen readers
     * @param {string} message - Message to announce
     * @param {string} priority - 'polite' or 'assertive'
     */
    announce(message, priority = "polite") {
      let announcer = document.getElementById("aria-announcer");
      if (!announcer) {
        announcer = document.createElement("div");
        announcer.id = "aria-announcer";
        announcer.setAttribute("aria-live", priority);
        announcer.setAttribute("aria-atomic", "true");
        announcer.style.cssText =
          "position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;";
        document.body.appendChild(announcer);
      }

      announcer.textContent = "";
      setTimeout(() => {
        announcer.textContent = message;
      }, 100);
    },
  };
  // Auto-initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => Accessibility.init());
  } else {
    Accessibility.init();
  }
  // Expose globally
  window.Accessibility = Accessibility;
})(window, document);
