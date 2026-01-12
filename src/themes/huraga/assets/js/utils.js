/**
 * Huraga theme utilities - minimal version of FOSSBilling utilities
 * Only includes functionality actually used by the Huraga theme
 */

globalThis.FOSSBilling = {
  message: (message, type = "info") => {
    let color;
    switch (type) {
      case "error":
        color = "danger";
        break;
      case "warning":
        color = "warning";
        break;
      default:
        color = "primary";
    }

    const container = document.querySelector(".toast-container");
    if (!container) {
      console.warn("Toast container not found for FOSSBilling.message()");
      return;
    }

    const element = document.createElement("div");
    container.appendChild(element);
    element.classList.add("toast", "show");
    element.setAttribute("role", "alert");
    element.setAttribute("aria-live", "assertive");
    element.setAttribute("aria-atomic", "true");

    // Create header div and its children elements
    const headerDiv = document.createElement("div");
    headerDiv.className = "toast-header";

    const spanEl = document.createElement("span");
    spanEl.className = `p-2 border border-light bg-${color} rounded-circle me-2`;
    headerDiv.appendChild(spanEl);

    const strongEl = document.createElement("strong");
    strongEl.className = "me-auto";
    strongEl.textContent = "System message";
    headerDiv.appendChild(strongEl);

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "btn-close";
    closeButton.setAttribute("data-bs-dismiss", "toast");
    closeButton.setAttribute("aria-label", "Close");
    headerDiv.appendChild(closeButton);

    element.appendChild(headerDiv);

    // Create body div and set its text content
    const bodyDiv = document.createElement("div");
    bodyDiv.className = "toast-body";
    bodyDiv.textContent = message;
    element.appendChild(bodyDiv);

    element.addEventListener("hidden.bs.toast", () => {
      container.removeChild(element);
    });

    // Create a new Bootstrap toast instance and show it
    const toast = new bootstrap.Toast(element);
    toast.show();
  }
};