var bb = {
  post: function (url, params, jsonp) {
    $.ajax({
      type: "POST",
      url: bb.restUrl(url),
      data: params,
      dataType: 'json',
      error: function (jqXHR, textStatus, e) {
        bb.msg(e, 'error');
      },
      success: function (data) {
        if (data.error) {
          // bb.msg(data.error.message, 'error');
          boxbilling.message(data.error.message, 'error');
        } else {
          if (typeof jsonp === 'function') {
            return jsonp(data.result);
          } else if (window.hasOwnProperty('console')) {
            console.log(data.result);
          }
        }
      }
    });
  },
  get: function (url, params, jsonp) {
    $.ajax({
      type: "GET",
      url: bb.restUrl(url),
      data: params,
      dataType: 'json',
      error: function (jqXHR, textStatus, e) {
        bb.msg(e, 'error');
      },
      success: function (data) {
        if (data.error) {
          bb.msg(data.error.message, 'error');
        } else {
          if (typeof jsonp === 'function') {
            return jsonp(data.result);
          } else if (window.hasOwnProperty('console')) {
            console.log(data.result);
          }
        }
      }
    });
  },
  restUrl: function (url) {
    if (url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
      return url;
    }
    return $('meta[property="bb:url"]').attr("content") + 'index.php?_url=/api/' + url;
  },
  error: function (txt, code) {
    jAlert(txt, 'Error code: ' + code);
  },
  msg: function (txt, type) {
    jAlert(txt, type);
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
      success: function (data) { r = data; },
      async: false
    });
    return r;
  },
  _afterComplete: function (obj, result) {
    var jsonp = obj.attr('data-api-jsonp');

    if (jsonp !== undefined && window.hasOwnProperty(jsonp)) {
      return window[jsonp](result);
    }

    if (obj.hasClass('bb-rm-tr')) {
      obj.closest('tr').addClass('highlight').fadeOut();
      return;
    }

    if (obj.attr('data-api-reload') !== undefined) {
      this.reload();
      return;
    }

    if (obj.attr('data-api-redirect') !== undefined) {
      return this.redirect(obj.attr('data-api-redirect'));
    }

    if (obj.attr('data-api-msg') !== undefined) {
      this.msg(obj.attr('data-api-msg'), 'success');

      return;
    }

    if (result) {
      this.msg('Form updated', 'success');

      return;
    }
  },
  apiForm: function () {
    $('form.api-form').bind('submit', function () {
      var obj = $(this);

      bb.post(obj.attr('action'), obj.serialize(), function (result) {
        return bb._afterComplete(obj, result);
      });

      return false;
    });
  },
  apiLink: function () {
    $("a.api-link").bind('click', function () {
      var obj = $(this);
      if (obj.attr('data-api-confirm') !== undefined) {
        jConfirm(obj.attr('data-api-confirm'), 'Confirm your action', function (r) {
          if (r) bb.get(obj.attr('href'), {}, function (result) { return bb._afterComplete(obj, result); });
        });
      } else if (obj.attr('data-api-prompt') !== undefined) {
        jPrompt(obj.attr('data-api-prompt-text'), obj.attr('data-api-prompt-default'), obj.attr('data-api-prompt-title'), function (r) {
          if (r) {
            var p = {};
            var name = obj.attr('data-api-prompt-key');
            p[name] = r;
            bb.get(obj.attr('href'), p, function (result) { return bb._afterComplete(obj, result); });
          }
        });
      } else {
        bb.get(obj.attr('href'), {}, function (result) { return bb._afterComplete(obj, result); });
      }
      return false;
    });
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

//===== Left navigation submenu animation =====//

$("ul.sub li a").hover(function () {
  $(this).stop().animate({ color: "#3a6fa5" }, 400);
}, function () {
  $(this).stop().animate({ color: "#494949" }, 400);
});


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

  $().UItoTop();

  //===== Datepickers =====//
  $(".datepicker").datepicker({
    defaultDate: +7,
    autoSize: true,
    //appendText: '(yyyy-mm-dd)',
    dateFormat: 'yy-mm-dd'
  });

  //===== Tooltip =====//

  $('.leftDir').tipsy({ fade: true, gravity: 'e' });
  $('.rightDir').tipsy({ fade: true, gravity: 'w' });
  $('.topDir').tipsy({ fade: true, gravity: 's' });
  $('.botDir').tipsy({ fade: true, gravity: 'n' });

  $('.dd').click(function () {
    $('ul.menu_body', this).slideToggle(100);
  });

  //===== Form elements styling =====//
  $(".mainForm select, .mainForm input:checkbox, .mainForm input:radio, .mainForm input:file").uniform();

  //===== Collapsible elements management =====//
  $('.exp').collapsible({
    defaultOpen: 'current',
    cookieName: 'navAct',
    cssOpen: 'active',
    cssClose: 'inactive',
    speed: 300
  });

  $("div.simpleTabs").simpleTabs();


  $(document).delegate('div.msg span.close', 'click', function () {
    $(this).parent().slideUp(70);
    return false;
  });

  //===== Information boxes =====//
  $(".hideit").click(function () {
    $(this).fadeOut(400);
  });

  $("select.language_selector").bind('change', function () {
    bb.cookieCreate('BBLANG', $(this).val(), 7);
    bb.reload();
    return false;
  }).val(bb.cookieRead('BBLANG'));
});

const boxbilling = {
  message: (message, type = 'info') => {
    switch (type) {
      case 'error':
        color = 'red';
        title = 'Error';
        break;
      case 'warning':
        color = 'yellow';
        title = 'Warning';
        break;
      default:
        color = 'teal';
        title = 'Info';
    }

    const container = document.createElement('div');
    container.classList.add('fixed', 'w-full', 'md:w-1/2', 'xl:w-1/4', 'bottom-0', 'right-0', 'p-5');
    container.style.zIndex = 1070;

    const element = document.createElement('div');
    element.setAttribute('id', 'liveToast');
    element.classList.add('toast');
    element.setAttribute('role', 'alert');
    element.setAttribute('aria-live', 'assertive');
    element.setAttribute('aria-atomic', 'true');

    element.innerHTML = `
    <div class="bg-${color}-100 border-t-4 border-${color}-500 rounded text-${color}-900 px-4 py-3 shadow-md" role="alert">
      <div class="flex">
        <div class="py-1">
          <svg class="fill-current h-6 w-6 text-${color}-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z" /></svg>
        </div>
        <div>
          <p class="font-bold">${title}</p>
          <p class="text-sm">${message}</p>
        </div>
      </div>
    </div>
    `;

    container.appendChild(element);

    document.querySelector('body').appendChild(container);

    setTimeout(() => {
      container.remove();
    }, 5000);
  }
};
