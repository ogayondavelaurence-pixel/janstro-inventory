/**
 * Workflow Form Validation
 */
const FormValidation = {
  // Sales Order validation
  validateSalesOrder(formData) {
    const errors = [];

    if (!formData.customer_name?.trim()) {
      errors.push("Customer name is required");
    }

    if (!formData.item_id) {
      errors.push("Please select an item");
    }

    const qty = parseInt(formData.quantity);
    if (!qty || qty <= 0) {
      errors.push("Quantity must be greater than 0");
    }

    if (
      formData.contact_number &&
      !this.validatePhone(formData.contact_number)
    ) {
      errors.push("Invalid phone number format (use 09XXXXXXXXX)");
    }

    if (formData.email && !this.validateEmail(formData.email)) {
      errors.push("Invalid email address");
    }

    return { valid: errors.length === 0, errors };
  },

  // Purchase Order validation
  validatePurchaseOrder(formData) {
    const errors = [];

    if (!formData.supplier_id) {
      errors.push("Please select a supplier");
    }

    if (!formData.item_id) {
      errors.push("Please select an item");
    }

    const qty = parseInt(formData.quantity);
    if (!qty || qty <= 0) {
      errors.push("Quantity must be greater than 0");
    }

    const price = parseFloat(formData.unit_price);
    if (price !== undefined && price < 0) {
      errors.push("Unit price cannot be negative");
    }

    return { valid: errors.length === 0, errors };
  },

  // Phone validation (Philippine)
  validatePhone(phone) {
    const cleaned = phone.replace(/[\s\-]/g, "");
    return /^(09|\+639)\d{9}$/.test(cleaned);
  },

  // Email validation
  validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  },

  // Display errors
  showErrors(errors, containerId = "formErrors") {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = errors
      .map(
        (err) => `
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> ${err}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `
      )
      .join("");

    container.scrollIntoView({ behavior: "smooth", block: "nearest" });
  },
};

window.FormValidation = FormValidation;
console.log("✅ Form Validation module loaded");
