/**
 * ============================================================================
 * PROFILE SETTINGS MODULE v6.0 - COMPLETE ENHANCED VERSION
 * ============================================================================
 * Path: frontend/assets/js/profile-settings.js
 *
 * ENHANCEMENTS v6.0:
 * ✅ Button loading states with spinners
 * ✅ Drag & drop support for profile picture
 * ✅ Enhanced visual feedback
 * ✅ Smooth animations
 * ✅ Better error handling
 * ✅ Mobile-friendly
 * ============================================================================
 */
(async function () {
  "use strict";

  const BASE_URL = window.location.origin;
  let currentUser = null;
  let selectedFile = null;

  // ========================================================================
  // UTILITY FUNCTIONS
  // ========================================================================

  /**
   * Set button loading state with spinner
   */
  function setButtonLoading(button, loading) {
    if (!button) return;

    if (loading) {
      button.dataset.originalText = button.innerHTML;
      button.classList.add("loading");
      button.disabled = true;
    } else {
      button.classList.remove("loading");
      button.disabled = false;
      if (button.dataset.originalText) {
        button.innerHTML = button.dataset.originalText;
      }
    }
  }

  /**
   * Convert file to base64
   */
  function fileToBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  /**
   * Format date
   */
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

  /**
   * Format date and time
   */
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

  /**
   * Get user initials
   */
  function getInitials(name) {
    if (!name) return "?";
    const parts = name.trim().split(" ");
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  }

  // ========================================================================
  // LOAD USER PROFILE DATA
  // ========================================================================
  async function loadUserProfile() {
    try {
      const response = await API.getCurrentUser();

      if (response.success && response.data) {
        currentUser = response.data;
        localStorage.setItem("janstro_user", JSON.stringify(currentUser));

        const elements = {
          username: document.getElementById("username"),
          fullName: document.getElementById("fullName"),
          email: document.getElementById("email"),
          phone: document.getElementById("phone"),
          role: document.getElementById("role"),
          lastLogin: document.getElementById("lastLogin"),
          memberSince: document.getElementById("memberSince"),
        };

        if (elements.username)
          elements.username.value = currentUser.username || "";
        if (elements.fullName) elements.fullName.value = currentUser.name || "";
        if (elements.email) elements.email.value = currentUser.email || "";
        if (elements.phone) elements.phone.value = currentUser.contact_no || "";
        if (elements.role)
          elements.role.value = currentUser.role_name || currentUser.role || "";

        if (elements.lastLogin && currentUser.last_login) {
          elements.lastLogin.textContent = formatDateTime(
            currentUser.last_login
          );
        }

        if (elements.memberSince && currentUser.created_at) {
          elements.memberSince.textContent = formatDate(currentUser.created_at);
        }

        loadProfilePicture();
      } else {
        throw new Error("Invalid response structure");
      }
    } catch (error) {
      console.error("❌ Load profile error:", error);
      Utils.showToast("Failed to load profile", "error");

      if (error.message && error.message.includes("401")) {
        setTimeout(() => (window.location.href = "index.html"), 2000);
      }
    }
  }

  // ========================================================================
  // LOAD PROFILE PICTURE
  // ========================================================================
  function loadProfilePicture() {
    const profilePicture =
      currentUser.profile_picture_thumb || currentUser.profile_picture;
    const initials = getInitials(currentUser.name || currentUser.username);

    const initialsElement = document.getElementById("profileInitials");
    const imageElement = document.getElementById("profileImage");
    const removeSection = document.getElementById("removePictureSection");

    if (profilePicture && profilePicture.trim() !== "") {
      let imageSrc;

      // Base64 image (legacy)
      if (profilePicture.startsWith("data:image")) {
        imageSrc = profilePicture;
      }
      // Full URL (external)
      else if (
        profilePicture.startsWith("http://") ||
        profilePicture.startsWith("https://")
      ) {
        imageSrc = profilePicture;
      }
      // Public path - Direct browser access
      else {
        imageSrc = BASE_URL + profilePicture;
      }

      imageElement.src = imageSrc;
      imageElement.style.display = "block";
      initialsElement.style.display = "none";
      removeSection.style.display = "block";

      console.log("✅ Profile picture loaded:", imageSrc);
    } else {
      initialsElement.textContent = initials;
      initialsElement.style.display = "block";
      imageElement.style.display = "none";
      removeSection.style.display = "none";
    }
  }

  // ========================================================================
  // SETUP EVENT LISTENERS - ENHANCED
  // ========================================================================
  function setupEventListeners() {
    const fileInput = document.getElementById("profilePictureInput");
    const previewContainer = document.getElementById("previewContainer");
    const btnUpload = document.getElementById("btnUploadPicture");
    const btnCancel = document.getElementById("btnCancelUpload");
    const btnRemove = document.getElementById("btnRemovePicture");
    const uploadBtn = document.querySelector(".picture-upload-btn");

    // ✅ ENHANCED: File input with visual feedback
    if (uploadBtn) {
      uploadBtn.addEventListener("mousedown", () => {
        uploadBtn.style.transform = "scale(0.9)";
      });

      uploadBtn.addEventListener("mouseup", () => {
        uploadBtn.style.transform = "";
      });

      uploadBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput?.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (file) handleFileSelection(file);
      });

      // ✅ ENHANCED: Drag & drop support
      const dropZone = document.querySelector(".profile-picture");
      if (dropZone) {
        dropZone.addEventListener("dragover", (e) => {
          e.preventDefault();
          dropZone.style.borderColor = "#667eea";
          dropZone.style.transform = "scale(1.05)";
        });

        dropZone.addEventListener("dragleave", () => {
          dropZone.style.borderColor = "";
          dropZone.style.transform = "";
        });

        dropZone.addEventListener("drop", (e) => {
          e.preventDefault();
          dropZone.style.borderColor = "";
          dropZone.style.transform = "";

          const file = e.dataTransfer.files[0];
          if (file && file.type.startsWith("image/")) {
            handleFileSelection(file);
          } else {
            Utils.showToast("Please drop an image file", "error");
          }
        });
      }
    }

    if (btnUpload) {
      btnUpload.addEventListener("click", () => {
        if (selectedFile) uploadProfilePicture(selectedFile);
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener("click", () => {
        selectedFile = null;
        if (fileInput) fileInput.value = "";
        if (previewContainer) {
          // ✅ ENHANCED: Smooth hide animation
          previewContainer.style.opacity = "0";
          previewContainer.style.transform = "translateY(-20px)";
          setTimeout(() => {
            previewContainer.classList.remove("show");
            previewContainer.style.opacity = "";
            previewContainer.style.transform = "";
          }, 300);
        }
      });
    }

    if (btnRemove) {
      btnRemove.addEventListener("click", removeProfilePicture);
    }

    const profileForm = document.getElementById("profileForm");
    if (profileForm) {
      profileForm.addEventListener("submit", handleProfileUpdate);
    }

    const passwordForm = document.getElementById("passwordForm");
    if (passwordForm) {
      passwordForm.addEventListener("submit", handlePasswordChange);
    }
  }

  // ========================================================================
  // FILE SELECTION HANDLER
  // ========================================================================
  function handleFileSelection(file) {
    const validTypes = [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "image/gif",
      "image/webp",
    ];
    if (!validTypes.includes(file.type)) {
      Utils.showToast(
        "Please select a valid image file (JPG, PNG, GIF, WebP)",
        "error"
      );
      return;
    }

    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
      Utils.showToast("Image size must be less than 5MB", "error");
      return;
    }

    selectedFile = file;

    const reader = new FileReader();
    reader.onload = (e) => {
      const previewImage = document.getElementById("previewImage");
      const previewContainer = document.getElementById("previewContainer");

      if (previewImage) previewImage.src = e.target.result;
      if (previewContainer) previewContainer.classList.add("show");
    };
    reader.readAsDataURL(file);
  }

  // ========================================================================
  // UPLOAD PROFILE PICTURE - ENHANCED WITH LOADING STATE
  // ========================================================================
  async function uploadProfilePicture(file) {
    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    const uploadBtn = document.getElementById("btnUploadPicture");

    try {
      // ✅ ENHANCED: Show loading state
      setButtonLoading(uploadBtn, true);
      Utils.showToast("Uploading profile picture...", "info");

      const base64 = await fileToBase64(file);

      const response = await fetch(
        `${API.baseURL}/users/${currentUser.user_id}/profile-picture`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${API.getToken()}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            image: base64,
            filename: file.name,
          }),
        }
      );

      const result = await response.json();

      if (!response.ok) {
        throw new Error(
          result.message || `Upload failed: ${response.statusText}`
        );
      }

      if (result.success) {
        Utils.showToast("Profile picture uploaded successfully!", "success");

        currentUser.profile_picture = result.data.profile_picture;
        currentUser.profile_picture_thumb = result.data.profile_picture_thumb;
        localStorage.setItem("janstro_user", JSON.stringify(currentUser));

        if (window.AppCore && window.AppCore.refreshNavbarProfilePicture) {
          window.AppCore.refreshNavbarProfilePicture(
            result.data.profile_picture_thumb || result.data.profile_picture
          );
        }

        loadProfilePicture();

        // ✅ ENHANCED: Hide preview with animation
        const previewContainer = document.getElementById("previewContainer");
        if (previewContainer) {
          previewContainer.style.opacity = "0";
          previewContainer.style.transform = "translateY(-20px)";
          setTimeout(() => {
            previewContainer.classList.remove("show");
            previewContainer.style.opacity = "";
            previewContainer.style.transform = "";
          }, 300);
        }

        const fileInput = document.getElementById("profilePictureInput");
        if (fileInput) fileInput.value = "";
        selectedFile = null;
      } else {
        throw new Error(result.message || "Upload failed");
      }
    } catch (error) {
      console.error("❌ Upload failed:", error);
      Utils.showToast(
        "Failed to upload profile picture: " + error.message,
        "error"
      );
    } finally {
      // ✅ ENHANCED: Always remove loading state
      setButtonLoading(uploadBtn, false);
    }
  }

  // ========================================================================
  // REMOVE PROFILE PICTURE - ENHANCED WITH LOADING STATE
  // ========================================================================
  async function removeProfilePicture() {
    const removeBtn = document.getElementById("btnRemovePicture");

    if (!confirm("Are you sure you want to remove your profile picture?")) {
      return;
    }

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    try {
      // ✅ ENHANCED: Show loading state
      setButtonLoading(removeBtn, true);
      Utils.showToast("Removing profile picture...", "info");

      const response = await fetch(
        `${API.baseURL}/users/${currentUser.user_id}/profile-picture`,
        {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${API.getToken()}`,
          },
        }
      );

      const result = await response.json();

      if (result.success) {
        Utils.showToast("Profile picture removed", "success");

        currentUser.profile_picture = null;
        currentUser.profile_picture_thumb = null;
        localStorage.setItem("janstro_user", JSON.stringify(currentUser));

        if (window.AppCore && window.AppCore.refreshNavbarProfilePicture) {
          window.AppCore.refreshNavbarProfilePicture(null);
        }

        loadProfilePicture();
      } else {
        Utils.showToast(result.message || "Remove failed", "error");
      }
    } catch (error) {
      console.error("❌ Remove error:", error);
      Utils.showToast("Failed to remove profile picture", "error");
    } finally {
      // ✅ ENHANCED: Always remove loading state
      setButtonLoading(removeBtn, false);
    }
  }

  // ========================================================================
  // UPDATE PROFILE - ENHANCED WITH LOADING STATE
  // ========================================================================
  async function handleProfileUpdate(e) {
    e.preventDefault();

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    // ✅ ENHANCED: Get submit button for loading state
    const submitBtn = e.target.querySelector('[type="submit"]');

    const data = {
      name: document.getElementById("fullName").value.trim(),
      email: document.getElementById("email").value.trim(),
      contact_no: document.getElementById("phone").value.trim(),
      profile_picture: currentUser.profile_picture || null,
    };

    if (!data.name) {
      Utils.showToast("Name is required", "error");
      return;
    }

    if (!data.email || !Utils.validateEmail(data.email)) {
      Utils.showToast("Valid email is required", "error");
      return;
    }

    try {
      // ✅ ENHANCED: Show loading state
      setButtonLoading(submitBtn, true);

      const response = await API.updateUser(currentUser.user_id, data);

      if (response.success) {
        Utils.showToast("Profile updated successfully!", "success");

        currentUser.name = data.name;
        currentUser.email = data.email;
        currentUser.contact_no = data.contact_no;
        localStorage.setItem("janstro_user", JSON.stringify(currentUser));

        if (window.AppCore && window.AppCore.initNavbar) {
          window.AppCore.currentUser = currentUser;
          window.AppCore.initNavbar();
        }

        await loadUserProfile();
      } else {
        Utils.showToast(response.message || "Update failed", "error");
      }
    } catch (error) {
      console.error("❌ Profile update error:", error);
      Utils.showToast(error.message || "Failed to update profile", "error");
    } finally {
      // ✅ ENHANCED: Always remove loading state
      setButtonLoading(submitBtn, false);
    }
  }

  // ========================================================================
  // CHANGE PASSWORD - ENHANCED WITH LOADING STATE
  // ========================================================================
  async function handlePasswordChange(e) {
    e.preventDefault();

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    // ✅ ENHANCED: Get submit button for loading state
    const submitBtn = e.target.querySelector('[type="submit"]');

    const currentPassword = document.getElementById("currentPassword").value;
    const newPassword = document.getElementById("newPassword").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    if (!currentPassword) {
      Utils.showToast("Current password is required", "error");
      return;
    }

    if (!newPassword || newPassword.length < 8) {
      Utils.showToast("New password must be at least 8 characters", "error");
      return;
    }

    if (newPassword !== confirmPassword) {
      Utils.showToast("Passwords do not match", "error");
      return;
    }

    try {
      // ✅ ENHANCED: Show loading state
      setButtonLoading(submitBtn, true);

      const response = await fetch(
        `${API.baseURL}/users/${currentUser.user_id}/change-password`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${API.getToken()}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
          }),
        }
      );

      const result = await response.json();

      if (result.success) {
        Utils.showToast(
          "Password changed successfully! Please log in again.",
          "success"
        );
        document.getElementById("passwordForm").reset();

        setTimeout(() => {
          API.logout();
        }, 3000);
      } else {
        Utils.showToast(result.message || "Password change failed", "error");
      }
    } catch (error) {
      console.error("❌ Password change error:", error);
      Utils.showToast("Failed to change password", "error");
    } finally {
      // ✅ ENHANCED: Always remove loading state
      setButtonLoading(submitBtn, false);
    }
  }

  // ========================================================================
  // INITIALIZATION
  // ========================================================================
  document.addEventListener("DOMContentLoaded", async () => {
    console.log("🔧 Profile Settings v6.0: Enhanced UI/UX Mode");
    await loadUserProfile();
    setupEventListeners();

    // Add smooth scroll
    document.querySelectorAll(".profile-card").forEach((card) => {
      card.style.scrollBehavior = "smooth";
    });

    console.log("✅ Profile Settings Enhanced UI/UX Loaded");
  });

  console.log("✅ Profile Settings Module v6.0 Complete");
})();
