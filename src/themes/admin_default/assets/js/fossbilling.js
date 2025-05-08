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

  //===== Global ajax methods =====//
  // Setup global AJAX loading indicators
  document.addEventListener("DOMContentLoaded", function() {
    (() => {
      const originalXHR = window.XMLHttpRequest;
      let activeRequests = 0;

      window.XMLHttpRequest = function() {
        const xhr = new originalXHR();

        // Track when request starts
        const originalOpen = xhr.open;
        xhr.open = function() {
          if (activeRequests === 0) {
            // Show all loading elements when first request starts
            document.querySelectorAll('.loading').forEach(el => {
              el.style.display = '';
            });
          }
          activeRequests++;

          return originalOpen.apply(this, arguments);
        };

        // Track when request ends
        xhr.addEventListener('loadend', () => {
          activeRequests--;
          if (activeRequests === 0) {
            // Hide all loading elements when last request completes
            document.querySelectorAll('.loading').forEach(el => {
              el.style.display = 'none';
            });
          }
        });

        return xhr;
      };
    })();

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

    //===== Form elements styling =====//
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

    //===== Information boxes =====//
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
