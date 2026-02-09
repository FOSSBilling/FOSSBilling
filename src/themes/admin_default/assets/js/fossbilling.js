import backToTop from "./ui/backToTop";

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

    const container = document.querySelector(".toast-container"); // Get the existing container or create if not present

    const element = document.createElement("div");
    container.appendChild(element);
    element.classList.add("toast", "show"); // Add 'show' class to display the toast immediately
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
    bodyDiv.textContent = message; // Safely set the message content
    element.appendChild(bodyDiv);

    element.addEventListener("hidden.bs.toast", () => {
      container.removeChild(element); // Remove the toast element from the container when it's hidden
    });

    // Create a new Bootstrap toast instance and show it
    const toast = new bootstrap.Toast(element);
    toast.show();
  },

  cookieCreate: function (name, value, days) {
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      var expires = "; expires=" + date.toGMTString();
    } else var expires = "";
    document.cookie = name + "=" + value + expires + "; path=/";
  },

  cookieRead: function (name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == " ") c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  }
  };

  document.addEventListener('DOMContentLoaded', function() {
    // Global error handler for unhandled Promise rejections
    window.addEventListener('unhandledrejection', function(event) {
      const error = event.reason;
      let message = 'An unexpected error occurred';
      if (error && typeof error === 'object') {
        message = error.message || error.code || message;
      } else if (typeof error === 'string') {
        message = error;
      }
      FOSSBilling.message(message, 'error');
    });

    // Global error handler for synchronous errors
    window.addEventListener('error', function(event) {
      let displayMessage = event && event.message ? event.message : 'An unexpected error occurred';
      if (event && event.error && event.error.message) {
        displayMessage = event.error.message;
      }
      FOSSBilling.message(displayMessage, 'error');
    });

    // Attach event listeners to all forms and links with data-fb-api attribute.
    if (document.querySelector("form[data-fb-api]")) {
      API._apiForm();
    };
    if (document.querySelector("a[data-fb-api]")) {
      API._apiLink();
    }

    // Initialize backToTop
    FOSSBilling.backToTop = backToTop;
    FOSSBilling.backToTop();
    });

    document.addEventListener("click", function(event) {
      const target = event.target;
      if (target.matches("div.msg span.close") || target.closest("div.msg span.close")) {
        event.preventDefault();
        const parent = target.parentElement;

        // Simple slide up effect
        const originalHeight = parent.offsetHeight;
        parent.style.overflow = "hidden";
        parent.style.transition = "height 70ms";
        parent.style.height = originalHeight + "px";

        setTimeout(() => {
          parent.style.height = "0";
          setTimeout(() => {
            parent.style.display = "none";
          }, 70);
        }, 10);

        return false;
      }
    });

    document.querySelectorAll('.hideit').forEach(element => {
      element.addEventListener('click', function() {
        // Simple fade out effect
        let opacity = 1;
        const fadeEffect = setInterval(() => {
          if (opacity > 0) {
            opacity -= 0.1;
            this.style.opacity = opacity;
          } else {
            clearInterval(fadeEffect);
            this.style.display = 'none';
          }
        }, 40); // 40ms * 10 steps ~= 400ms duration
      });
    });
  });
