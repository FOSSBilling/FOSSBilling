/*eslint no-unused-vars: ["error", { "varsIgnorePattern": "boxbilling" }]*/

import backToTop from "./ui/backToTop";

globalThis.bb = {
  /**
   * @deprecated This method will be removed in a future release. Use the new API wrapper instead. Check the documentation for more information.
   * @documentation https://fossbilling.org/docs/under-the-hood/api
   *
   * Leaving this here for backwards compatibility with templates using this method.
   */
  post: function (url, params, successHandler) {
    // We don't know which API is called (admin, client, guest), so we are directly using the makeRequest method and not specific methods like API.admin.post().
    // Templates willing to use the new API wrapper should use the corresponding methods and avoid using the makeRequest method directly.
    API.makeRequest(
      "POST",
      bb.restUrl(url),
      JSON.stringify(params),
      successHandler,
      function (error) {
        FOSSBilling.message(error.message, "error");
      }
    );
    console.error(
      "This theme or module is using a deprecated method. Please update it to use the new API wrapper instead. Documentation: https://fossbilling.org/docs/api/javascript"
    );
  },

  /**
   * @deprecated This method will be removed in a future release. Use the new API wrapper instead. Check the documentation for more information.
   * @documentation https://fossbilling.org/docs/under-the-hood/api
   *
   * Leaving this here for backwards compatibility with templates using this method.
   */
  get: function (url, params, successHandler) {
    // We don't know which API is called (admin, client, guest), so we are directly using the makeRequest method and not specific methods like API.admin.post().
    // Templates willing to use the new API wrapper should use the corresponding methods and avoid using the makeRequest method directly.
    API.makeRequest(
      "GET",
      bb.restUrl(url),
      params,
      successHandler,
      function (error) {
        FOSSBilling.message(error.message, "error");
      }
    );
    console.error(
      "This theme or module is using a deprecated method. Please update it to use the new API wrapper instead. Documentation: https://fossbilling.org/docs/api/javascript"
    );
  },

  restUrl: function (url) {
    if (url.indexOf("http://") > -1 || url.indexOf("https://") > -1) {
      return url;
    }
    return (
      $('meta[property="bb:url"]').attr("content") +
      "index.php?_url=/api/" +
      url
    );
  },

  /**
   * @deprecated Will be removed in a future release. Use FOSSBilling.message() instead.
   */
  error: function (txt, code) {
    FOSSBilling.message(`${txt} (${code})`, "error");
  },

  /**
   * @deprecated Will be removed in a future release. Use FOSSBilling.message() instead.
   */
  msg: function (txt, type) {
    FOSSBilling.message(txt, type);
    console.error(
      "This theme or module is using a deprecated method. Please update it to use FOSSBilling.message() instead of bb.msg()."
    );
  },

  redirect: function (url) {
    if (url === undefined) {
      this.reload();
    }
    window.location = url;
  },

  reload: function () {
    window.location.reload(true);
  },

  load: function (url, params) {
    var r = "";

    $.ajax({
      url: url,
      data: params,
      type: "GET",
      success: function (data) {
        r = data;
      },
      async: false,
    });

    return r;
  },

  _afterComplete: function (obj, result) {
    var jsonp = obj.getAttribute("data-api-jsonp");

    if (jsonp !== null && window.hasOwnProperty(jsonp)) {
      return window[jsonp](result);
    }

    if (obj.classList.contains("bb-rm-tr")) {
      obj.closest("tr").classList.add("highlight");
      return;
    }

    if (obj.hasAttribute("data-api-redirect")) {
      window.location = obj.getAttribute("data-api-redirect");
      return;
    }

    if (obj.hasAttribute("data-api-reload")) {
      window.location.reload();
      return;
    }

    if (obj.hasAttribute("data-api-msg")) {
      FOSSBilling.message(obj.getAttribute("data-api-msg"), "success");
      return;
    }

    if (result) {
      FOSSBilling.message("Form updated", "success");
      return;
    }
  },

  apiForm: function () {
    const formElements = document.getElementsByClassName("api-form");

    if (formElements.length > 0) {
      for (let i = 0; i < formElements.length; i++) {
        const formElement = formElements[i];

        formElement.addEventListener("submit", function (event) {
          // Prevent the default form submit action. We will handle it ourselves.
          event.preventDefault();
          const formData = new FormData(formElement);

          // Get all CKEditor instances and replace the original textarea values with the updated content.
          if (
            typeof editors !== "undefined" &&
            Array.isArray(editors) &&
            editors.length > 0
          ) {
            let editorContentOnRequiredAttr = false;
            Object.keys(editors).forEach(function (name) {
              editorContentOnRequiredAttr = editors[name].required
                ? editors[name].editor.getData() !== ""
                : true;
              formData.set(name, editors[name].editor.getData());
            });
            if (!editorContentOnRequiredAttr) {
              return FOSSBilling.message(
                "At least one of the required fields are empty",
                "error"
              );
            }
          }

          let data;
          if (formElement.getAttribute("method").toLowerCase() !== "get") {
            data = formData.serializeJSON();
          } else {
            data = formData.serialize();
          }

          let buttons = document.querySelectorAll("button:not([disabled])");

          buttons.forEach(function (button) {
            button.setAttribute("disabled", "true");
          });

          API.makeRequest(
            formElement.getAttribute("method"),
            bb.restUrl(formElement.getAttribute("action")),
            data,
            function (result) {
              buttons.forEach(function (button) {
                button.removeAttribute("disabled");
              });
              return bb._afterComplete(formElement, result);
            },
            function (error) {
              buttons.forEach(function (button) {
                button.removeAttribute("disabled");
              });
              FOSSBilling.message(`${error.message} (${error.code})`, "error");
            }
          );
        });
      }
    }
  },

  apiLink: function () {
    const linkElements = document.getElementsByClassName("api-link");

    if (linkElements.length > 0) {
      for (let i = 0; i < linkElements.length; i++) {
        const linkElement = linkElements[i];

        linkElement.addEventListener("click", function (event) {
          // Prevent the default form click action. We will handle it ourselves.
          event.preventDefault();

          if (linkElement.dataset.apiConfirm) {
            Modals.create({
              type: linkElement.dataset.apiType
                ? linkElement.dataset.apiType
                : "small-confirm",
              confirmButton: linkElement.dataset.apiConfirmBtn
                ? linkElement.dataset.apiConfirmBtn
                : "Confirm",
              content: linkElement.dataset.apiConfirmContent
                ? linkElement.dataset.apiConfirmContent
                : "",
              confirmButtonColor: linkElement.dataset.apiConfirmBtnColor
                ? linkElement.dataset.apiConfirmBtnColor
                : "primary",
              title: linkElement.dataset.apiConfirm,
              confirmCallback: function () {
                API.makeRequest(
                  "GET",
                  bb.restUrl(linkElement.getAttribute("href")),
                  {},
                  function (result) {
                    return bb._afterComplete(linkElement, result);
                  },
                  function (error) {
                    FOSSBilling.message(
                      `${error.message} (${error.code})`,
                      "error"
                    );
                  }
                );
              },
            });
          } else if (linkElement.dataset.apiPrompt) {
            Modals.create({
              type: "prompt",
              title: linkElement.dataset.apiPromptTitle,
              label: linkElement.dataset.apiPromptText
                ? linkElement.dataset.apiPromptText
                : "Label",
              value: linkElement.dataset.apiPromptDefault
                ? linkElement.dataset.apiPromptDefault
                : "",
              promptConfirmCallback: function (value) {
                if (value) {
                  const p = {};
                  const name = linkElement.dataset.apiPromptKey;
                  p[name] = value;
                  API.makeRequest(
                    "GET",
                    bb.restUrl(linkElement.getAttribute("href")),
                    p,
                    function (result) {
                      return bb._afterComplete(linkElement, result);
                    },
                    function (error) {
                      FOSSBilling.message(
                        `${error.message} (${error.code})`,
                        "error"
                      );
                    }
                  );
                }
              },
            });
          } else {
            API.makeRequest(
              "GET",
              bb.restUrl(linkElement.getAttribute("href")),
              {},
              function (result) {
                return bb._afterComplete(linkElement, result);
              },
              function (error) {
                FOSSBilling.message(
                  `${error.message} (${error.code})`,
                  "error"
                );
              }
            );
          }
          return false;
        });
      }
    }
  },

  menuAutoActive: function () {
    var matches = $("ul#menu li a").filter(function () {
      return document.location.href == this.href;
    });
    matches.parents("li").addClass("active");
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
  },

  insertToTextarea: function (areaId, text) {
    var txtarea = document.getElementById(areaId);
    var scrollPos = txtarea.scrollTop;
    var strPos = 0;
    var br =
      txtarea.selectionStart || txtarea.selectionStart == "0"
        ? "ff"
        : document.selection
        ? "ie"
        : false;
    if (br == "ie") {
      txtarea.focus();
      var range = document.selection.createRange();
      range.moveStart("character", -txtarea.value.length);
      strPos = range.text.length;
    } else if (br == "ff") strPos = txtarea.selectionStart;

    var front = txtarea.value.substring(0, strPos);
    var back = txtarea.value.substring(strPos, txtarea.value.length);
    txtarea.value = front + text + back;
    strPos = strPos + text.length;
    if (br == "ie") {
      txtarea.focus();
      var range = document.selection.createRange();
      range.moveStart("character", -txtarea.value.length);
      range.moveStart("character", strPos);
      range.moveEnd("character", 0);
      range.select();
    } else if (br == "ff") {
      txtarea.selectionStart = strPos;
      txtarea.selectionEnd = strPos;
      txtarea.focus();
    }
    txtarea.scrollTop = scrollPos;
    if ("undefined" !== typeof CKEDITOR) {
      CKEDITOR.instances[areaId].insertText(text);
    }

    return false;
  },

  currency: function (price, rate, title, multiply) {
    price = parseFloat(price) * parseFloat(rate);
    if (multiply !== undefined) {
      price = price * multiply;
    }
    return price.toFixed(2) + " " + title;
  },
};

//===== Tabs =====//
$.fn.simpleTabs = function () {
  //Default Action
  $(this).find(".tab_content").hide(); //Hide all content
  $(this).find("ul.tabs li:first").addClass("activeTab").show(); //Activate first tab
  $(this).find(".tab_content:first").show(); //Show first tab content

  //On Click Event
  $("ul.tabs li").on("click", function () {
    $(this).parent().parent().find("ul.tabs li").removeClass("activeTab"); //Remove any "active" class
    $(this).addClass("activeTab"); //Add "active" class to selected tab
    $(this).parent().parent().find(".tab_content").hide(); //Hide all tab content
    var activeTab = $(this).find("a").attr("href"); //Find the rel attribute value to identify the active tab + content
    $(activeTab).show(); //Fade in the active content
    //document.location.hash = activeTab;
    return false;
  });

  // select active tab
  if ($(document.location.hash).length) {
    $('a[href="' + document.location.hash + '"]')
      .parent()
      .trigger();
    $(window).scrollTop(window.location.href.indexOf("#"));
  }
}; //end function

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
};

$(function () {
  //===== Global ajax methods =====//
  $(document)
    .ajaxStart(() => {
      $(".loading").show();
    })
    .ajaxStop(() => {
      $(".loading").hide();
    });

  //===== Api forms and links =====//
  if ($("form.api-form").length) {
    bb.apiForm();
  }
  if ($("a.api-link").length) {
    bb.apiLink();
  }
  //if($("ul#menu").length){bb.menuAutoActive();}

  // Initialize backToTop
  FOSSBilling.backToTop = backToTop;
  FOSSBilling.backToTop();

  //===== Form elements styling =====//

  $("div.simpleTabs").simpleTabs();

  $(document).on("click", "div.msg span.close", function () {
    $(this).parent().slideUp(70);
    return false;
  });

  //===== Information boxes =====//
  $(".hideit").on("click", function () {
    $(this).fadeOut(400);
  });
});
