var bb = {
    event: function(name, params){
        var event = new CustomEvent(name, { detail: params });
        // Dispatch/Trigger/Fire the event
        document.dispatchEvent(event);
    },

    post: function(url, params, jsonp) {
        $.ajax({
            type: "POST",
            url: bb.restUrl(url),
            data: params,
            dataType: 'json',
            error: function(jqXHR, textStatus, e) {
                $('.wait').hide();
                bb.msg(e, 'error');
            },
            success: function(data) {
                if(data.error) {
                    $('.wait').hide();
                    bb.event('bb_ajax_post_message_error', data);
                    bb.msg(data.error.message, 'error');
                } else {
                    if(typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if(window.hasOwnProperty('console')) {
                        console.log(data.result);
                    }
                }
            }
        });
    },
    get: function(url, params, jsonp) {
        $.ajax({
            type: "GET",
            url: bb.restUrl(url),
            data: params,
            dataType: 'json',
            error: function(jqXHR, textStatus, e) {
                $('.wait').hide();
                bb.msg(e, 'error');
            },
            success: function(data) {
                if(data.error) {
                    $('.wait').hide();
                   bb.msg(data.error.message, 'error');
                } else {
                    if(typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if(window.hasOwnProperty('console')) {
                        console.log(data.result);
                    }
                }
            }
        });
    },
    restUrl: function(url) {
        if(url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
            return url;
        }
        return $('meta[property="bb:url"]').attr("content") + 'index.php?_url=/api/' + url;
    },
    reload: function() {
        location.reload(false);
    },
    redirect: function(url) {
        if(url === undefined) {
            window.location = $('meta[property="bb:url"]').attr("content");
        } else {
            window.location = url;
        }
    },
    currency: function(price, rate, title, multiply) {
        price = parseFloat(price) * parseFloat(rate);
        if(multiply !== undefined) {
            price = price * multiply;
        }
        return price.toFixed(2) + " " + title;
    },
    popup: function(txt) {

        $('#default_popup').remove();
        var popUp = $('<div id="default_popup" class="popup_block"><h3></h3></div>')
            .find('h3').text(txt)
            .parent()
            .appendTo('body');

        var popWidth = 500; //Gets the first query string value

        popUp.show().css({'width': Number( popWidth )}).prepend('<a href="#" class="close">X</a>');

        var popMargTop = (popUp.height() + 80) / 2;
        var popMargLeft = (popUp.width() + 80) / 2;

        //Apply Margin to Popup
        popUp.css({
            'margin-top' : -popMargTop,
            'margin-left' : -popMargLeft
        });

        $('body').append('<div id="fade"></div>');
        $('#fade').show();

        //Close Popups and Fade Layer
        $('a.close, #fade').bind('click', function() { //When clicking on the close or fade layer...
            $('#fade , .popup_block').hide(function() {
                $('#fade, a.close').remove();  //fade them both out
            });
            return false;
        });
        $("body").keyup(function(e){
            if(e.keyCode==27){
                $('#fade').click();
            }
        });
    },
    msg: function(txt, type) {
        $.jGrowl(txt);
    },
    apiForm: function() {
        $("form.api_form, form.api-form").bind('submit', function(){
            var redirect = $(this).attr('data-api-redirect');
            var jsonp = $(this).attr('data-api-jsonp');
            var msg = $(this).attr('data-api-msg');
            var reload = $(this).attr('data-api-reload');
            var url = $(this).attr('action');
            if($(this).attr('data-api-url')) {
                url = $(this).attr('data-api-url');
            }
            bb.post(
                url,
                $(this).serialize(),
                function(result) {
                    if(reload !== undefined) {
                        bb.reload();
                        return;
                    }
                    if(redirect !== undefined) {
                        bb.redirect(redirect);
                        return;
                    }
                    if(msg !== undefined) {
                        bb.msg(msg);
                        return;
                    }
                    if(jsonp !== undefined && window.hasOwnProperty(jsonp)) {
                        return window[jsonp](result);
                    }
                }
            );
            return false;
        });
    },
    apiLink: function() {
        $("a.api, a.api-link").bind('click', function(){
            var redirect = $(this).attr('data-api-redirect');
            var reload = $(this).attr('data-api-reload');
            var msg = $(this).attr('data-api-msg');
            var jsonp = $(this).attr('data-api-jsonp');
            bb.get(
                $(this).attr('href'),
                {},
                function(result) {
                    if(msg !== undefined) {
                        bb.msg(msg);
                        return;
                    }
                    if(reload !== undefined) {
                        bb.reload();
                        return;
                    }
                    if(jsonp !== undefined && window.hasOwnProperty(jsonp)) {
                        return window[jsonp](result);
                    }
                    bb.redirect(redirect);
                }
            );
            return false;
        });
    },
    MenuAutoActive: function() {
        var matches = $('ul.main li a').filter(function() {
            return document.location.href == this.href;
        });
        matches.parents('li').addClass('active');
    },
    cookieCreate: function (name,value,days) {
        if (days) {
            var date = new Date();
            date.setTime(date.getTime()+(days*24*60*60*1000));
            var expires = "; expires="+date.toGMTString();
        }
        else var expires = "";
        document.cookie = name+"="+value+expires+"; path=/";
    },
    cookieRead: function (name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    },
    CurrencySelector: function() {
        $("select.currency_selector").bind('change', function(){
            bb.post(
                'guest/cart/set_currency',
                {currency: $(this).val()},
                function(result) {
                    bb.reload();
                }
            );
            return false;
        });
    },
    LanguageSelector: function() {

        $("select.language_selector").bind('change', function(){
            bb.cookieCreate('BBLANG', $(this).val(), 7);
            bb.reload();
            return false;
        }).val(bb.cookieRead('BBLANG'));
    }
}

//===== Placeholder for all browsers =====//

$("input").each(
    function(){
        if($(this).val()=="" && $(this).attr("placeholder")!=""){
        $(this).val($(this).attr("placeholder"));
        $(this).focus(function(){
            if($(this).val()==$(this).attr("placeholder")) $(this).val("");
        });
        $(this).blur(function(){
            if($(this).val()=="") $(this).val($(this).attr("placeholder"));
        });
    }
});

jQuery(function ($) {
    $('.loading').ajaxStart(function() {
        $(this).show();
    }).ajaxStop(function() {
        $(this).hide();
    });

    if($("select.currency_selector").length){bb.CurrencySelector();}
    if($("select.language_selector").length){bb.LanguageSelector();}
    if($("ul.main").length){bb.MenuAutoActive();}
	if($("a.api, a.api-link").length){bb.apiLink();}
	if($("form.api_form, form.api-form").length){bb.apiForm();}


    $('#login-form-link').bind('click', function(){
        $(this).fadeOut();
        $('#login-form').slideDown();
        return false;
    });

    if(jQuery().tipsy) {
        $('a.show-tip').tipsy({fade: true, delayIn: 500});
    };

    $("li.language_selector").bind('click', function(){
        bb.cookieCreate('BBLANG', $(this).attr('data-language-code'), 7);
        bb.reload();
        return false;
    }).val(bb.cookieRead('BBLANG'));

});