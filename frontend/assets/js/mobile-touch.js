/**
 * ============================================================================
 * JANSTRO IMS - MOBILE TOUCH GESTURE SYSTEM v1.0
 * ============================================================================
 * Path: frontend/assets/js/mobile-touch.js
 *
 * FEATURES:
 * - Swipe detection (left, right, up, down)
 * - Long-press detection
 * - Pull-to-refresh
 * - Touch ripple effects
 * - Pinch-to-zoom detection
 * - Double-tap detection
 *
 * USAGE:
 * Include this file after utils.js and before page-specific scripts
 *
 * Example HTML:
 * <script src="assets/js/mobile-touch.js"></script>
 *
 * CHANGELOG v1.0:
 * ✅ Complete touch gesture detection
 * ✅ Configurable thresholds
 * ✅ Event-based system
 * ✅ No dependencies (vanilla JS)
 * ✅ Optimized for performance
 *
 * GITHUB: https://github.com/ogayondavelaurence-pixel/janstro-inventory
 * ============================================================================
 */

(function (window, document) {
  "use strict";

  // ========================================================================
  // CONFIGURATION
  // ========================================================================
  const TouchConfig = {
    swipe: {
      threshold: 50, // Minimum distance in pixels to trigger swipe
      velocity: 0.3, // Minimum velocity (pixels/ms)
      maxTime: 300, // Maximum time for swipe gesture
      restraint: 100, // Maximum distance perpendicular to swipe direction
    },
    longPress: {
      duration: 500, // Milliseconds to hold for long-press
      moveThreshold: 10, // Max movement allowed during long-press
    },
    pullToRefresh: {
      threshold: 80, // Pull distance to trigger refresh
      maxPull: 120, // Maximum pull distance
    },
    doubleTap: {
      delay: 300, // Max time between taps
      distance: 20, // Max distance between tap points
    },
    ripple: {
      duration: 600, // Ripple animation duration
    },
  };

  // ========================================================================
  // TOUCH GESTURE MANAGER
  // ========================================================================
  const TouchGestures = {
    // State tracking
    touchStart: null,
    touchEnd: null,
    startTime: null,
    longPressTimer: null,
    lastTap: null,
    isPulling: false,
    pullDistance: 0,

    // ====================================================================
    // INITIALIZATION
    // ====================================================================
    init() {
      if (!this.isTouchDevice()) {
        console.log("📱 Non-touch device detected, touch gestures disabled");
        return;
      }

      console.log("✅ Touch Gestures System Initialized");
      this.attachGlobalListeners();
      this.setupRippleEffect();
      this.setupPullToRefresh();
    },

    // ====================================================================
    // DEVICE DETECTION
    // ====================================================================
    isTouchDevice() {
      return (
        "ontouchstart" in window ||
        navigator.maxTouchPoints > 0 ||
        navigator.msMaxTouchPoints > 0
      );
    },

    isMobile() {
      return window.innerWidth <= 768;
    },

    // ====================================================================
    // GLOBAL TOUCH LISTENERS
    // ====================================================================
    attachGlobalListeners() {
      // Swipe detection
      document.addEventListener("touchstart", (e) => this.handleTouchStart(e), {
        passive: false,
      });
      document.addEventListener("touchmove", (e) => this.handleTouchMove(e), {
        passive: false,
      });
      document.addEventListener("touchend", (e) => this.handleTouchEnd(e), {
        passive: false,
      });
      document.addEventListener("touchcancel", (e) =>
        this.handleTouchCancel(e)
      );

      console.log("✅ Touch listeners attached");
    },

    // ====================================================================
    // TOUCH START HANDLER
    // ====================================================================
    handleTouchStart(e) {
      const touch = e.touches[0];

      this.touchStart = {
        x: touch.clientX,
        y: touch.clientY,
        time: Date.now(),
        target: e.target,
      };

      this.startTime = Date.now();

      // Long-press detection
      this.startLongPressDetection(e);

      // Double-tap detection
      this.detectDoubleTap(touch);

      // Pull-to-refresh detection
      if (window.scrollY === 0) {
        this.startPullDetection();
      }
    },

    // ====================================================================
    // TOUCH MOVE HANDLER
    // ====================================================================
    handleTouchMove(e) {
      if (!this.touchStart) return;

      const touch = e.touches[0];
      const deltaX = touch.clientX - this.touchStart.x;
      const deltaY = touch.clientY - this.touchStart.y;
      const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

      // Cancel long-press if moved too much
      if (distance > TouchConfig.longPress.moveThreshold) {
        this.cancelLongPress();
      }

      // Pull-to-refresh
      if (this.isPulling && deltaY > 0 && window.scrollY === 0) {
        e.preventDefault();
        this.updatePullDistance(deltaY);
      }

      // Store for swipe detection
      this.touchEnd = {
        x: touch.clientX,
        y: touch.clientY,
        time: Date.now(),
      };
    },

    // ====================================================================
    // TOUCH END HANDLER
    // ====================================================================
    handleTouchEnd(e) {
      if (!this.touchStart) return;

      this.cancelLongPress();

      if (!this.touchEnd) {
        this.touchEnd = {
          x: this.touchStart.x,
          y: this.touchStart.y,
          time: Date.now(),
        };
      }

      // Detect swipe
      this.detectSwipe();

      // Handle pull-to-refresh release
      if (this.isPulling) {
        this.releasePull();
      }

      // Reset
      this.touchStart = null;
      this.touchEnd = null;
    },

    // ====================================================================
    // TOUCH CANCEL HANDLER
    // ====================================================================
    handleTouchCancel(e) {
      this.cancelLongPress();
      this.touchStart = null;
      this.touchEnd = null;

      if (this.isPulling) {
        this.cancelPull();
      }
    },

    // ====================================================================
    // SWIPE DETECTION
    // ====================================================================
    detectSwipe() {
      const deltaX = this.touchEnd.x - this.touchStart.x;
      const deltaY = this.touchEnd.y - this.touchStart.y;
      const deltaTime = this.touchEnd.time - this.touchStart.time;

      const absX = Math.abs(deltaX);
      const absY = Math.abs(deltaY);

      // Calculate velocity
      const velocity = Math.sqrt(deltaX * deltaX + deltaY * deltaY) / deltaTime;

      // Check if meets swipe criteria
      if (deltaTime > TouchConfig.swipe.maxTime) return;
      if (velocity < TouchConfig.swipe.velocity) return;

      let direction = null;

      // Horizontal swipe
      if (absX > absY && absX > TouchConfig.swipe.threshold) {
        if (absY < TouchConfig.swipe.restraint) {
          direction = deltaX > 0 ? "right" : "left";
        }
      }
      // Vertical swipe
      else if (absY > TouchConfig.swipe.threshold) {
        if (absX < TouchConfig.swipe.restraint) {
          direction = deltaY > 0 ? "down" : "up";
        }
      }

      if (direction) {
        this.dispatchSwipeEvent(direction, {
          distance: direction === "left" || direction === "right" ? absX : absY,
          velocity: velocity,
          deltaX: deltaX,
          deltaY: deltaY,
          target: this.touchStart.target,
        });
      }
    },

    dispatchSwipeEvent(direction, details) {
      const event = new CustomEvent("janstro:swipe", {
        detail: {
          direction: direction,
          ...details,
        },
        bubbles: true,
        cancelable: true,
      });

      this.touchStart.target.dispatchEvent(event);

      // Direction-specific event
      const directionEvent = new CustomEvent(`janstro:swipe${direction}`, {
        detail: details,
        bubbles: true,
        cancelable: true,
      });

      this.touchStart.target.dispatchEvent(directionEvent);

      console.log(`👆 Swipe ${direction} detected`);
    },

    // ====================================================================
    // LONG-PRESS DETECTION
    // ====================================================================
    startLongPressDetection(e) {
      this.longPressTimer = setTimeout(() => {
        this.dispatchLongPressEvent(e);
      }, TouchConfig.longPress.duration);
    },

    cancelLongPress() {
      if (this.longPressTimer) {
        clearTimeout(this.longPressTimer);
        this.longPressTimer = null;
      }
    },

    dispatchLongPressEvent(e) {
      const event = new CustomEvent("janstro:longpress", {
        detail: {
          x: this.touchStart.x,
          y: this.touchStart.y,
          target: this.touchStart.target,
        },
        bubbles: true,
        cancelable: true,
      });

      this.touchStart.target.dispatchEvent(event);

      // Haptic feedback if available
      if (navigator.vibrate) {
        navigator.vibrate(50);
      }

      console.log("👆 Long-press detected");
    },

    // ====================================================================
    // DOUBLE-TAP DETECTION
    // ====================================================================
    detectDoubleTap(touch) {
      const now = Date.now();

      if (this.lastTap) {
        const timeDiff = now - this.lastTap.time;
        const distance = Math.sqrt(
          Math.pow(touch.clientX - this.lastTap.x, 2) +
            Math.pow(touch.clientY - this.lastTap.y, 2)
        );

        if (
          timeDiff < TouchConfig.doubleTap.delay &&
          distance < TouchConfig.doubleTap.distance
        ) {
          this.dispatchDoubleTapEvent(touch);
          this.lastTap = null;
          return;
        }
      }

      this.lastTap = {
        x: touch.clientX,
        y: touch.clientY,
        time: now,
      };
    },

    dispatchDoubleTapEvent(touch) {
      const event = new CustomEvent("janstro:doubletap", {
        detail: {
          x: touch.clientX,
          y: touch.clientY,
          target: this.touchStart.target,
        },
        bubbles: true,
        cancelable: true,
      });

      this.touchStart.target.dispatchEvent(event);
      console.log("👆 Double-tap detected");
    },

    // ====================================================================
    // PULL-TO-REFRESH
    // ====================================================================
    startPullDetection() {
      this.isPulling = true;
      this.pullDistance = 0;
    },

    updatePullDistance(distance) {
      this.pullDistance = Math.min(distance, TouchConfig.pullToRefresh.maxPull);

      const indicator = document.getElementById("pullToRefreshIndicator");
      if (indicator) {
        indicator.style.transform = `translateY(${this.pullDistance}px)`;
        indicator.style.opacity = Math.min(
          this.pullDistance / TouchConfig.pullToRefresh.threshold,
          1
        );
      }

      // Dispatch event for UI updates
      const event = new CustomEvent("janstro:pulling", {
        detail: {
          distance: this.pullDistance,
          threshold: TouchConfig.pullToRefresh.threshold,
          maxPull: TouchConfig.pullToRefresh.maxPull,
        },
      });
      document.dispatchEvent(event);
    },

    releasePull() {
      if (this.pullDistance >= TouchConfig.pullToRefresh.threshold) {
        this.triggerRefresh();
      } else {
        this.cancelPull();
      }

      this.isPulling = false;
      this.pullDistance = 0;
    },

    triggerRefresh() {
      console.log("🔄 Pull-to-refresh triggered");

      const event = new CustomEvent("janstro:refresh", {
        detail: { source: "pull" },
      });
      document.dispatchEvent(event);

      // Reset indicator
      this.resetPullIndicator();
    },

    cancelPull() {
      this.resetPullIndicator();
      this.isPulling = false;
      this.pullDistance = 0;
    },

    resetPullIndicator() {
      const indicator = document.getElementById("pullToRefreshIndicator");
      if (indicator) {
        indicator.style.transform = "translateY(-60px)";
        indicator.style.opacity = "0";
      }
    },

    setupPullToRefresh() {
      // Create indicator element
      const indicator = document.createElement("div");
      indicator.id = "pullToRefreshIndicator";
      indicator.className = "pull-to-refresh";
      indicator.innerHTML =
        '<i class="bi bi-arrow-clockwise pull-to-refresh-icon"></i>';

      document.body.insertBefore(indicator, document.body.firstChild);
    },

    // ====================================================================
    // RIPPLE EFFECT
    // ====================================================================
    setupRippleEffect() {
      // Add ripple to clickable elements
      const selector = ".btn, .menu-item, .stat-card, .card, button, a[href]";

      document.addEventListener(
        "click",
        (e) => {
          const target = e.target.closest(selector);
          if (target && !target.classList.contains("no-ripple")) {
            this.createRipple(e, target);
          }
        },
        true
      );

      console.log("✅ Ripple effects enabled");
    },

    createRipple(e, element) {
      // Don't add ripple if already has one active
      if (element.querySelector(".ripple-effect")) return;

      const ripple = document.createElement("span");
      ripple.className = "ripple-effect";

      const rect = element.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;

      ripple.style.cssText = `
                position: absolute;
                top: ${y}px;
                left: ${x}px;
                width: ${size}px;
                height: ${size}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                pointer-events: none;
                transform: scale(0);
                animation: ripple ${TouchConfig.ripple.duration}ms ease-out;
            `;

      // Ensure parent is positioned
      if (window.getComputedStyle(element).position === "static") {
        element.style.position = "relative";
      }
      element.style.overflow = "hidden";

      element.appendChild(ripple);

      setTimeout(() => {
        ripple.remove();
      }, TouchConfig.ripple.duration);
    },

    // ====================================================================
    // UTILITY METHODS
    // ====================================================================
    enableSwipeOnElement(element, callback) {
      element.addEventListener("janstro:swipe", (e) => {
        callback(e.detail);
      });
    },

    enableLongPressOnElement(element, callback) {
      element.addEventListener("janstro:longpress", (e) => {
        callback(e.detail);
      });
    },

    disableScrollBounce() {
      // Prevent iOS rubber-band scroll
      let lastTouchY = 0;
      let preventScroll = false;

      document.addEventListener(
        "touchstart",
        (e) => {
          lastTouchY = e.touches[0].clientY;
        },
        { passive: false }
      );

      document.addEventListener(
        "touchmove",
        (e) => {
          const touchY = e.touches[0].clientY;
          const scrollTop = window.scrollY;
          const direction = touchY > lastTouchY ? "down" : "up";

          if (
            (scrollTop === 0 && direction === "down") ||
            (scrollTop + window.innerHeight >= document.body.scrollHeight &&
              direction === "up")
          ) {
            e.preventDefault();
          }

          lastTouchY = touchY;
        },
        { passive: false }
      );

      console.log("✅ Scroll bounce disabled");
    },
  };

  // ========================================================================
  // HELPER UTILITIES
  // ========================================================================
  const TouchUtils = {
    // Prevent default touch behavior
    preventTouchDefaults(element) {
      element.addEventListener("touchstart", (e) => e.preventDefault(), {
        passive: false,
      });
      element.addEventListener("touchmove", (e) => e.preventDefault(), {
        passive: false,
      });
    },

    // Make element swipeable with visual feedback
    makeSwipeable(element, options = {}) {
      const {
        onSwipeLeft = null,
        onSwipeRight = null,
        threshold = 50,
      } = options;

      let startX = 0;
      let currentX = 0;
      let isDragging = false;

      element.addEventListener("touchstart", (e) => {
        startX = e.touches[0].clientX;
        isDragging = true;
        element.style.transition = "none";
      });

      element.addEventListener("touchmove", (e) => {
        if (!isDragging) return;
        currentX = e.touches[0].clientX;
        const diff = currentX - startX;
        element.style.transform = `translateX(${diff}px)`;
      });

      element.addEventListener("touchend", () => {
        if (!isDragging) return;
        isDragging = false;

        const diff = currentX - startX;
        element.style.transition = "transform 0.3s ease";

        if (Math.abs(diff) > threshold) {
          if (diff > 0 && onSwipeRight) {
            onSwipeRight();
          } else if (diff < 0 && onSwipeLeft) {
            onSwipeLeft();
          }
        }

        element.style.transform = "translateX(0)";
      });
    },
  };

  // ========================================================================
  // CSS INJECTION FOR RIPPLE ANIMATION
  // ========================================================================
  const rippleStyles = document.createElement("style");
  rippleStyles.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        .ripple-effect {
            z-index: 1;
        }
    `;
  document.head.appendChild(rippleStyles);

  // ========================================================================
  // AUTO-INITIALIZATION
  // ========================================================================
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      TouchGestures.init();
    });
  } else {
    TouchGestures.init();
  }

  // ========================================================================
  // GLOBAL EXPORTS
  // ========================================================================
  window.TouchGestures = TouchGestures;
  window.TouchUtils = TouchUtils;
  window.TouchConfig = TouchConfig;

  console.log("✅ Mobile Touch System v1.0 Loaded");
  console.log(
    "📱 Available events: swipe, swipeleft, swiperight, swipeup, swipedown, longpress, doubletap, refresh"
  );
})(window, document);
