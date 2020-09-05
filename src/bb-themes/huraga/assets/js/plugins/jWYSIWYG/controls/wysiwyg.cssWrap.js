/**
 * Controls: Element CSS Wrapper plugin
 *
 * Depends on jWYSIWYG
 * 
 * By Yotam Bar-On (https://github.com/tudmotu)
 */
(function ($) {
	if (undefined === $.wysiwyg) {
		throw "wysiwyg.cssWrap.js depends on $.wysiwyg";
	}
	/* For core enhancements #143
	$.wysiwyg.ui.addControl("cssWrap", {
		visible : false,
		groupIndex: 6,
		tooltip: "CSS Wrapper",
		exec: function () { 
				$.wysiwyg.controls.cssWrap.init(this);
			}
	}
	*/	
	if (!$.wysiwyg.controls) {
		$.wysiwyg.controls = {};
	}

	/*
	 * Wysiwyg namespace: public properties and methods
	 */
	$.wysiwyg.controls.cssWrap = {
		init: function (Wysiwyg) {
			var self = this, formWrapHtml, key, translation,
				dialogReplacements = {
					legend	: "Wrap Element",
					wrapperType : "Wrapper Type",
					ID : "ID",
					"class" : "Class",
					wrap  : "Wrap",
					unwrap: "Unwrap",
					cancel   : "Cancel"
				};

			formWrapHtml = '<form class="wysiwyg"><fieldset><legend>{legend}</legend>' +
				'<div class="wysiwyg-dialogRow"><label>{wrapperType}: &nbsp;<select name="type"><option value="span">Span</option><option value="div">Div</option></select></label></div>' +
				'<div class="wysiwyg-dialogRow"><label>{ID}: &nbsp;<input name="id" type="text" /></label></div>' + 
				'<div class="wysiwyg-dialogRow"><label>{class}: &nbsp;<input name="class" type="text" /></label></div>' +
				'<div class="wysiwyg-dialogRow"><input type="button" class="button cssWrap-unwrap" style="display:none;" value="{unwrap}"/></label>' +
				'<input type="submit"  class="button cssWrap-submit" value="{wrap}"/></label>' +
				'<input type="reset" class="button cssWrap-cancel" value="{cancel}"/></div></fieldset></form>';

			for (key in dialogReplacements) {
				if ($.wysiwyg.i18n) {
					translation = $.wysiwyg.i18n.t(dialogReplacements[key]);
					if (translation === dialogReplacements[key]) { // if not translated search in dialogs 
						translation = $.wysiwyg.i18n.t(dialogReplacements[key], "dialogs");
					}
					dialogReplacements[key] = translation;
				}
				formWrapHtml = formWrapHtml.replace("{" + key + "}", dialogReplacements[key]);
			}
			if (!$(".wysiwyg-dialog-wrapper").length) {
				$(formWrapHtml).appendTo("body");
				$("form.wysiwyg").dialog({
					modal: true,
					open: function (ev, ui) {
						var $this = $(this), range	= Wysiwyg.getInternalRange(), common, $nodeName;
						// We make sure that there is some selection:
						if (range) {
							if ($.browser.msie) {
								Wysiwyg.ui.focus();
							}
							common	= $(range.commonAncestorContainer);
						} else {
							alert("You must select some elements before you can wrap them.");
							$this.dialog("close");
							return 0;
						}
						$nodeName = range.commonAncestorContainer.nodeName.toLowerCase();
						// If the selection is already a .wysiwygCssWrapper, then we want to change it and not double-wrap it.
						if (common.parent(".wysiwygCssWrapper").length) {
							alert(common.parent(".wysiwygCssWrapper").get(0).nodeName.toLowerCase());
							$this.find("select[name=type]").val(common.parent(".wysiwygCssWrapper").get(0).nodeName.toLowerCase());
							$this.find("select[name=type]").attr("disabled", "disabled");
							$this.find("input[name=id]").val(common.parent(".wysiwygCssWrapper").attr("id"));
							$this.find("input[name=class]").val(common.parent(".wysiwygCssWrapper").attr("class").replace('wysiwygCssWrapper ', ''));
							// Add the "unwrap" button:
							$("form.wysiwyg").find(".cssWrap-unwrap").show();
							$("form.wysiwyg").find(".cssWrap-unwrap").click(function (e) {
								e.preventDefault();
								if ($nodeName !== "body") {
									common.unwrap();
								}
								$this.dialog("close");
								return 1;
							});
						}
						// Submit button.
						$("form.wysiwyg").find(".cssWrap-submit").click(function (e) {
							e.preventDefault();
							var $wrapper = $("form.wysiwyg").find("select[name=type]").val(),
								$id = $("form.wysiwyg").find("input[name=id]").val(),
								$class = $("form.wysiwyg").find("input[name=class]").val();

							if ($nodeName !== "body") {
								// If the selection is already a .wysiwygCssWrapper, then we want to change it and not double-wrap it.
								if (common.parent(".wysiwygCssWrapper").length) {
									common.parent(".wysiwygCssWrapper").attr("id", $class);
									common.parent(".wysiwygCssWrapper").attr("class", $class);
								} else {
									common.wrap("<" + $wrapper + " id=\"" + $id + "\" class=\"" + "wysiwygCssWrapper " + $class + "\"/>");
								}
							} else {
								// Currently no implemntation for if $nodeName == 'body'.
							}
							$this.dialog("close");
						});
						// Cancel button.
						$("form.wysiwyg").find(".cssWrap-cancel").click(function (e) {
							e.preventDefault();
							$this.dialog("close");
							return 1;
						});
					},
					close: function () {
						$(this).dialog("destroy");
						$(this).remove();
					}
				});
				Wysiwyg.saveContent();
			}
			$(Wysiwyg.editorDoc).trigger("editorRefresh.wysiwyg");
			return 1;
		}
	};
})(jQuery);
