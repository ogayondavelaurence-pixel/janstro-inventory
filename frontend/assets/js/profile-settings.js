/**
 * ============================================================================
 * PROFILE SETTINGS MODULE - FIXED IMAGE URLS v4.1
 * ============================================================================
 * Path: frontend/assets/js/profile-settings.js
 *
 * ✅ CRITICAL FIX: Image URL construction
 * - Database paths already start with /janstro-inventory/
 * - Don't prepend API_BASE (causes double path)
 * - Just use window.location.origin + path
 * ============================================================================
 */
(async function () {
  "use strict";

  // ✅ FIX: Get base URL correctly
  const BASE_URL = window.location.origin; // http://localhost:8080

  let currentUser = null;
  let selectedFile = null;

  // ========================================================================
  // LOAD USER PROFILE DATA
  // ========================================================================
  async function loadUserProfile() {
    try {
      console.log("📥 Loading user profile...");
      const response = await API.getCurrentUser();

      console.log("📦 Profile response:", response);

      if (response.success && response.data) {
        currentUser = response.data;

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

        console.log("✅ Profile loaded successfully");
      } else {
        throw new Error("Invalid response structure");
      }
    } catch (error) {
      console.error("❌ Load profile error:", error);
      Utils.showToast("Failed to load profile", "error");

      if (error.message && error.message.includes("401")) {
        setTimeout(() => {
          window.location.href = "index.html";
        }, 2000);
      }
    }
  }

  // ========================================================================
  // ✅ FIXED: LOAD PROFILE PICTURE WITH CORRECT URL
  // ========================================================================
  function loadProfilePicture() {
    const profilePicture =
      currentUser.profile_picture_thumb || currentUser.profile_picture;
    const initials = getInitials(currentUser.name || currentUser.username);

    const initialsElement = document.getElementById("profileInitials");
    const imageElement = document.getElementById("profileImage");
    const removeSection = document.getElementById("removePictureSection");

    if (profilePicture && profilePicture.trim() !== "") {
      // ✅ CRITICAL FIX: Construct URL correctly
      let imageSrc;

      if (profilePicture.startsWith("data:image")) {
        // Base64 image
        imageSrc = profilePicture;
      } else if (
        profilePicture.startsWith("http://") ||
        profilePicture.startsWith("https://")
      ) {
        // Full URL
        imageSrc = profilePicture;
      } else {
        // Path starts with /janstro-inventory/...
        // Just prepend the origin
        imageSrc = `${BASE_URL}${profilePicture}`;
      }

      console.log("🖼️ Loading profile picture:", imageSrc);

      imageElement.src = imageSrc;
      imageElement.style.display = "block";
      initialsElement.style.display = "none";
      removeSection.style.display = "block";

      console.log("✅ Profile picture loaded");
    } else {
      initialsElement.textContent = initials;
      initialsElement.style.display = "block";
      imageElement.style.display = "none";
      removeSection.style.display = "none";

      console.log("ℹ️ No profile picture, showing initials:", initials);
    }
  }

  function getInitials(name) {
    if (!name) return "?";
    const parts = name.trim().split(" ");
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  }

  // ========================================================================
  // SETUP EVENT LISTENERS
  // ========================================================================
  function setupEventListeners() {
    const fileInput = document.getElementById("profilePictureInput");
    const previewContainer = document.getElementById("previewContainer");
    const previewImage = document.getElementById("previewImage");
    const btnUpload = document.getElementById("btnUploadPicture");
    const btnCancel = document.getElementById("btnCancelUpload");
    const btnRemove = document.getElementById("btnRemovePicture");

    if (fileInput) {
      fileInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (file) {
          handleFileSelection(file);
        }
      });
    }

    if (btnUpload) {
      btnUpload.addEventListener("click", () => {
        if (selectedFile) {
          uploadProfilePicture(selectedFile);
        }
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener("click", () => {
        selectedFile = null;
        if (fileInput) fileInput.value = "";
        if (previewContainer) previewContainer.classList.remove("show");
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

      console.log("✅ File preview loaded");
    };
    reader.readAsDataURL(file);
  }

  // ========================================================================
  // UPLOAD PROFILE PICTURE
  // ========================================================================
  async function uploadProfilePicture(file) {
    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    try {
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

      console.log("📦 Upload response:", result);

      if (result.success) {
        Utils.showToast("Profile picture uploaded successfully!", "success");

        // Update currentUser immediately
        currentUser.profile_picture = result.data.profile_picture;
        currentUser.profile_picture_thumb = result.data.profile_picture_thumb;

        // Refresh navbar
        if (window.AppCore && window.AppCore.refreshNavbarProfilePicture) {
          window.AppCore.refreshNavbarProfilePicture(
            result.data.profile_picture_thumb || result.data.profile_picture
          );
        }

        // Refresh sidebar
        if (window.AppCore && window.AppCore.initSidebar) {
          window.AppCore.initSidebar();
        }

        // Update page display
        loadProfilePicture();

        // Hide preview
        const previewContainer = document.getElementById("previewContainer");
        const fileInput = document.getElementById("profilePictureInput");
        if (previewContainer) previewContainer.classList.remove("show");
        if (fileInput) fileInput.value = "";
        selectedFile = null;
      } else {
        Utils.showToast(result.message || "Upload failed", "error");
      }
    } catch (error) {
      console.error("❌ Upload error:", error);
      Utils.showToast("Failed to upload profile picture", "error");
    }
  }

  // ========================================================================
  // REMOVE PROFILE PICTURE
  // ========================================================================
  async function removeProfilePicture() {
    if (!confirm("Are you sure you want to remove your profile picture?")) {
      return;
    }

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

    try {
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

      console.log("📦 Remove response:", result);

      if (result.success) {
        Utils.showToast("Profile picture removed", "success");

        currentUser.profile_picture = null;
        currentUser.profile_picture_thumb = null;

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
    }
  }

  // ========================================================================
  // UPDATE PROFILE (NAME, EMAIL, PHONE)
  // ========================================================================
  async function handleProfileUpdate(e) {
    e.preventDefault();

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

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
      console.log("💾 Saving profile...", data);

      const response = await API.updateUser(currentUser.user_id, data);

      console.log("📦 Update response:", response);

      if (response.success) {
        Utils.showToast("Profile updated successfully!", "success");

        currentUser.name = data.name;
        currentUser.email = data.email;
        currentUser.contact_no = data.contact_no;

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
    }
  }

  // ========================================================================
  // CHANGE PASSWORD
  // ========================================================================
  async function handlePasswordChange(e) {
    e.preventDefault();

    if (!currentUser || !currentUser.user_id) {
      Utils.showToast("User not loaded", "error");
      return;
    }

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
      console.log("🔑 Changing password...");

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

      console.log("📦 Password change response:", result);

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
    }
  }

  // ========================================================================
  // HELPER FUNCTIONS
  // ========================================================================

  function fileToBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

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

  // ========================================================================
  // INITIALIZATION
  // ========================================================================
  document.addEventListener("DOMContentLoaded", async () => {
    console.log("🔧 Profile Settings: Initializing (Fixed URLs v4.1)");
    await loadUserProfile();
    setupEventListeners();
  });

  console.log("✅ Profile Settings Module Loaded (Fixed URLs v4.1)");
})();
