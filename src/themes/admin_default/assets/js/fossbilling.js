/*eslint no-unused-vars: ["error", { "varsIgnorePattern": "boxbilling" }]*/

var bb = {

  /**
  * @deprecated This method will be removed in a future release. Use the new API wrapper instead. Check the documentation for more information.
  * @documentation https://fossbilling.org/docs/under-the-hood/api
  *
  * Leaving this here for backwards compatibility with templates using this method.
  */
  post: function (url, params, successHandler) {

    // We don't know which API is called (admin, client, guest), so we are directly using the makeRequest method and not specific methods like API.admin.post().
    // Templates willing to use the new API wrapper should use the corresponding methods and avoid using the makeRequest method directly.
    API.makeRequest("POST", bb.restUrl(url), params, successHandler, function (error) {
      FOSSBilling.message(error.message, 'error');
    });
    console.error("This theme or module is using a deprecated method. Please update it to use the new API wrapper instead. Documentation: https://fossbilling.org/docs/api/javascript");

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
    API.makeRequest("GET", bb.restUrl(url), params, successHandler, function (error) {
      FOSSBilling.message(error.message, 'error');
    });
    console.error("This theme or module is using a deprecated method. Please update it to use the new API wrapper instead. Documentation: https://fossbilling.org/docs/api/javascript");

  },

  restUrl: function (url) {
    if (url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
      return url;
    }
    return $('meta[property="bb:url"]').attr("content") + 'index.php?_url=/api/' + url;
  },

  /**
   * @deprecated Will be removed in a future release. Use FOSSBilling.message() instead.
   */
  error: function (txt, code) {
    FOSSBilling.message(`${txt} (${code})`, 'error');
  },

  /**
   * @deprecated Will be removed in a future release. Use FOSSBilling.message() instead.
   */
  msg: function (txt, type) {
    FOSSBilling.message(txt, type);
    console.error("This theme or module is using a deprecated method. Please update it to use FOSSBilling.message() instead of bb.msg().");
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
    var r = '';

    $.ajax({
      url: url,
      data: params,
      type: "GET",
      success: function (data) {
        r = data;
      },
      async: false
    });

    return r;
  },

  _afterComplete: function (obj, result) {
    var jsonp = obj.getAttribute('data-api-jsonp');

    if (jsonp !== null && window.hasOwnProperty(jsonp)) {
      return window[jsonp](result);
    }

    if (obj.classList.contains('bb-rm-tr')) {
      obj.closest('tr').classList.add('highlight');
      return;
    }

    if (obj.hasAttribute('data-api-redirect')) {
      window.location = obj.getAttribute('data-api-redirect');
      return;
    }

    if (obj.hasAttribute('data-api-reload')) {
      window.location.reload();
      return;
    }

    if (obj.hasAttribute('data-api-msg')) {
      FOSSBilling.message(obj.getAttribute('data-api-msg'), 'success');
      return;
    }

    if (result) {
      FOSSBilling.message('Form updated', 'success');
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
          if(!formData.get('CSRFToken')){
            formData.append('CSRFToken', Tools.getCSRFToken());
          }
          if(formElement.getAttribute('method').toLowerCase() != 'get'){
             data = formData.serializeJSON();
          }else{
            data =  formData.serialize();
          }
          API.makeRequest(formElement.getAttribute('method'), bb.restUrl(formElement.getAttribute('action')),  data , function (result) {
            return bb._afterComplete(formElement, result);
          }, function (error) {
            FOSSBilling.message(`${error.message} (${error.code})`, 'error');
          });
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

          if (linkElement.hasAttribute('data-api-confirm')) {
            if (confirm(linkElement.getAttribute('data-api-confirm'))) {
              API.makeRequest("GET", bb.restUrl(linkElement.getAttribute('href')), {'CSRFToken' : Tools.getCSRFToken()}, function (result) {
                return bb._afterComplete(linkElement, result)}, function (error) {
                  FOSSBilling.message(`${error.message} (${error.code})`, 'error');
                });
            }
          } else if (linkElement.hasAttribute('data-api-prompt')) {
            jPrompt(linkElement.getAttribute('data-api-prompt-text'), linkElement.getAttribute('data-api-prompt-default'), linkElement.getAttribute('data-api-prompt-title'), function (r) {
              if (r) {
                var p = {'CSRFToken' : Tools.getCSRFToken()};
                var name = linkElement.getAttribute('data-api-prompt-key');
                p[name] = r;
                API.makeRequest("GET", bb.restUrl(linkElement.getAttribute('href')), p, function (result) {
                  return bb._afterComplete(linkElement, result)}, function (error) {
                    FOSSBilling.message(`${error.message} (${error.code})`, 'error');
                  });
              }
            });
          } else {
            API.makeRequest("GET", bb.restUrl(linkElement.getAttribute('href')), {'CSRFToken' : Tools.getCSRFToken()}, function (result) {
              return bb._afterComplete(linkElement, result)}, function (error) {
                FOSSBilling.message(`${error.message} (${error.code})`, 'error');
              });
          }
          return false;
        });
      }
    }
  },

  menuAutoActive: function () {
    var matches = $('ul#menu li a').filter(function () {
      return document.location.href == this.href;
    });
    matches.parents('li').addClass('active');
  },

  cookieCreate: function (name, value, days) {
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      var expires = "; expires=" + date.toGMTString();
    }
    else var expires = "";
    document.cookie = name + "=" + value + expires + "; path=/";
  },

  cookieRead: function (name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == ' ') c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  },

  insertToTextarea: function (areaId, text) {
    var txtarea = document.getElementById(areaId);
    var scrollPos = txtarea.scrollTop;
    var strPos = 0;
    var br = ((txtarea.selectionStart || txtarea.selectionStart == '0') ?
      "ff" : (document.selection ? "ie" : false));
    if (br == "ie") {
      txtarea.focus();
      var range = document.selection.createRange();
      range.moveStart('character', -txtarea.value.length);
      strPos = range.text.length;
    }
    else if (br == "ff") strPos = txtarea.selectionStart;

    var front = (txtarea.value).substring(0, strPos);
    var back = (txtarea.value).substring(strPos, txtarea.value.length);
    txtarea.value = front + text + back;
    strPos = strPos + text.length;
    if (br == "ie") {
      txtarea.focus();
      var range = document.selection.createRange();
      range.moveStart('character', -txtarea.value.length);
      range.moveStart('character', strPos);
      range.moveEnd('character', 0);
      range.select();
    }
    else if (br == "ff") {
      txtarea.selectionStart = strPos;
      txtarea.selectionEnd = strPos;
      txtarea.focus();
    }
    txtarea.scrollTop = scrollPos;
    if ('undefined' !== typeof CKEDITOR) {
      CKEDITOR.instances[areaId].insertText(text);
    }

    return false
  }
}

//===== Tabs =====//
$.fn.simpleTabs = function () {

  //Default Action
  $(this).find(".tab_content").hide(); //Hide all content
  $(this).find("ul.tabs li:first").addClass("activeTab").show(); //Activate first tab
  $(this).find(".tab_content:first").show(); //Show first tab content

  //On Click Event
  $("ul.tabs li").click(function () {
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
    $('a[href="' + document.location.hash + '"]').parent().click();
    $(window).scrollTo(window.location.href.indexOf('#'))
  }

};//end function

const FOSSBilling = {
  message: (message, type = 'info') => {
    switch (type) {
      case 'error':
        color = 'danger';
        break;
      case 'warning':
        color = 'warning';
        break;
      default:
        color = 'primary';
    }

    const container = document.createElement('div');
    container.classList.add('position-fixed', 'bottom-0', 'end-0', 'p-3');
    container.style.zIndex = 1070;

    const element = document.createElement('div');
    element.setAttribute('id', 'liveToast');
    element.classList.add('toast');
    element.setAttribute('role', 'alert');
    element.setAttribute('aria-live', 'assertive');
    element.setAttribute('aria-atomic', 'true');

    element.innerHTML = `
            <div class="toast-header">
                <span class="p-2 border border-light bg-${color} rounded-circle me-2"></span>
                <strong class="me-auto">System message</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;

    element.addEventListener('hidden.bs.toast', () => {
      container.remove();
    });

    const toast = new bootstrap.Toast(element);

    container.appendChild(element);

    document.querySelector('body').appendChild(container);

    toast.show();
  }
};

/**
  * Fallback for modules that still use the old "boxbilling" object. It will display deprecation warnings in the console and will be removed entirely in the future.
  * @deprecated Will be removed in a future release. Use FOSSBilling.message() instead.
  */
const boxbilling = {
  message: (message, type = 'info') => {
    console.warn('The "boxbilling" object is deprecated and will be removed in a future release. Use FOSSBilling.message() instead.');
    FOSSBilling.message(message, type);
  }
}

$(function () {

  //===== Global ajax methods =====//
  $('.loading').ajaxStart(function () {
    $(this).show();
  }).ajaxStop(function () {
    $(this).hide();
  });

  //===== Api forms and links =====//
  if ($("form.api-form").length) { bb.apiForm(); }
  if ($("a.api-link").length) { bb.apiLink(); }
  //if($("ul#menu").length){bb.menuAutoActive();}

  // Initialize backToTop
  FOSSBilling.backToTop();

  //===== Datepickers =====//
  $(".datepicker").datepicker({
    defaultDate: +7,
    autoSize: true,
    //appendText: '(yyyy-mm-dd)',
    dateFormat: 'yy-mm-dd'
  });

  //===== Form elements styling =====//
  // $(".mainForm select, .mainForm input:checkbox, .mainForm input:radio, .mainForm input:file").uniform();
  $(".mainForm input:checkbox, .mainForm input:radio, .mainForm input:file").uniform();

  $("div.simpleTabs").simpleTabs();


  $(document).delegate('div.msg span.close', 'click', function () {
    $(this).parent().slideUp(70);
    return false;
  });

  //===== Information boxes =====//
  $(".hideit").click(function () {
    $(this).fadeOut(400);
  });

  $("select.js-language-selector").bind('change', function () {
    bb.cookieCreate('BBLANG', $(this).val(), 7);
    bb.reload();

    return false;
  }).val(bb.cookieRead('BBLANG'));
});
