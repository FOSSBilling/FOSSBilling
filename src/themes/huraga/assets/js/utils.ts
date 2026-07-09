/**
 * Huraga theme utilities - minimal version of FOSSBilling utilities
 */

window.FOSSBilling = Object.assign(window.FOSSBilling || {}, {
  message: (message: string, type = "info") => {
    let color: string;
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

    const bodyDiv = document.createElement("div");
    bodyDiv.className = "toast-body";
    bodyDiv.textContent = message;
    element.appendChild(bodyDiv);

    element.addEventListener("hidden.bs.toast", () => {
      container.removeChild(element);
    });

    const toast = new bootstrap.Toast(element);
    toast.show();
  }
});
