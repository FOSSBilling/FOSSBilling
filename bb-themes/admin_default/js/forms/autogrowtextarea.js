/*!
 * Autogrow Textarea Plugin Version v2.0
 * http://www.technoreply.com/autogrow-textarea-plugin-version-2-0
 *
 * Copyright 2011, Jevin O. Sewaruth
 *
 * Date: March 13, 2011
 */
jQuery.fn.autoGrow = function(){return this.each(function(){var colsDefault = this.cols;var rowsDefault = this.rows;var grow = function() {growByRef(this);}
var growByRef = function(obj) {var linesCount = 0;var lines = obj.value.split('\n');for (var i=lines.length-1; i>=0; --i){linesCount += Math.floor((lines[i].length / colsDefault) + 1);}
if (linesCount >= rowsDefault)obj.rows = linesCount + 1;else obj.rows = rowsDefault;}
var characterWidth = function (obj){var characterWidth = 0; var temp1 = 0; var temp2 = 0; var tempCols = obj.cols; obj.cols = 1; temp1 = obj.offsetWidth; obj.cols = 2; temp2 = obj.offsetWidth; characterWidth = temp2 - temp1; obj.cols = tempCols; return characterWidth;}
this.style.height = "auto";this.style.overflow = "hidden";this.onkeyup = grow;this.onfocus = grow;this.onblur = grow;growByRef(this);});};


/*
* Placeholder plugin for jQuery
* ---
* Copyright 2010, Daniel Stocks (http://webcloud.se)
* Released under the MIT, BSD, and GPL Licenses.
*/

(function(b){function d(a){this.input=a;a.attr("type")=="password"&&this.handlePassword();b(a[0].form).submit(function(){if(a.hasClass("placeholder")&&a[0].value==a.attr("placeholder"))a[0].value=""})}d.prototype={show:function(a){if(this.input[0].value===""||a&&this.valueIsPlaceholder()){if(this.isPassword)try{this.input[0].setAttribute("type","text")}catch(b){this.input.before(this.fakePassword.show()).hide()}this.input.addClass("placeholder");this.input[0].value=this.input.attr("placeholder")}},
hide:function(){if(this.valueIsPlaceholder()&&this.input.hasClass("placeholder")&&(this.input.removeClass("placeholder"),this.input[0].value="",this.isPassword)){try{this.input[0].setAttribute("type","password")}catch(a){}this.input.show();this.input[0].focus()}},valueIsPlaceholder:function(){return this.input[0].value==this.input.attr("placeholder")},handlePassword:function(){var a=this.input;a.attr("realType","password");this.isPassword=!0;if(b.browser.msie&&a[0].outerHTML){var c=b(a[0].outerHTML.replace(/type=(['"])?password\1/gi,
"type=$1text$1"));this.fakePassword=c.val(a.attr("placeholder")).addClass("placeholder").focus(function(){a.trigger("focus");b(this).hide()});b(a[0].form).submit(function(){c.remove();a.show()})}}};var e=!!("placeholder"in document.createElement("input"));b.fn.placeholder=function(){return e?this:this.each(function(){var a=b(this),c=new d(a);c.show(!0);a.focus(function(){c.hide()});a.blur(function(){c.show(!1)});b.browser.msie&&(b(window).load(function(){a.val()&&a.removeClass("placeholder");c.show(!0)}),
a.focus(function(){if(this.value==""){var a=this.createTextRange();a.collapse(!0);a.moveStart("character",0);a.select()}}))})}})(jQuery);