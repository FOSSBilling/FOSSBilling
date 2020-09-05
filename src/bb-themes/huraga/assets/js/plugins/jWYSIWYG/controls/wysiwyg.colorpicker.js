/**
 * Controls: Colorpicker plugin
 * 
 * Depends on jWYSIWYG, (farbtastic || other colorpicker plugins)
 */
(function ($) {
	"use strict";

	if (undefined === $.wysiwyg) {
		throw "wysiwyg.colorpicker.js depends on $.wysiwyg";
	}

	if (!$.wysiwyg.controls) {
		$.wysiwyg.controls = {};
	}

	/*
	 * Wysiwyg namespace: public properties and methods
	 */
	$.wysiwyg.controls.colorpicker = {
		modalOpen: false,
		color: {
			back: {
				prev: "#ffffff",
				palette: []
			},
			fore: {
				prev: "#123456",
				palette: []
			}
		},

		addColorToPalette: function (type, color) {
			if (-1 === $.inArray(color, this.color[type].palette)) {
				this.color[type].palette.push(color);
			} else {
				this.color[type].palette.sort(function (a, b) {
					if (a === color) {
						return 1;
					}

					return 0;
				});
			}
		},

		init: function (Wysiwyg) {
			if ($.wysiwyg.controls.colorpicker.modalOpen === true) {
				return false;
			} else {
				$.wysiwyg.controls.colorpicker.modalOpen = true;
			}
			var self = this, elements, dialog, colorpickerHtml, dialogReplacements, key, translation;

			dialogReplacements = {
				legend: "Colorpicker",
				color: "Color",
				submit: "Apply",
				reset: "Cancel"
			};

			colorpickerHtml = '<form class="wysiwyg"><fieldset><legend>{legend}</legend>' +
				'<ul class="palette"></ul>' +
				'<label>{color}: <input type="text" name="color" value="#123456"/></label>' +
				'<div class="wheel"></div>' +
				'<input type="submit" class="button" value="{submit}"/> ' +
				'<input type="reset" value="{reset}"/></fieldset></form>';

			for (key in dialogReplacements) {
				if ($.wysiwyg.i18n) {
					translation = $.wysiwyg.i18n.t(dialogReplacements[key], "dialogs.colorpicker");

					if (translation === dialogReplacements[key]) { // if not translated search in dialogs 
						translation = $.wysiwyg.i18n.t(dialogReplacements[key], "dialogs");
					}

					dialogReplacements[key] = translation;
				}

				colorpickerHtml = colorpickerHtml.replace("{" + key + "}", dialogReplacements[key]);
			}

			if ($.modal) {
				elements = $(colorpickerHtml);

				if ($.farbtastic) {
					this.renderPalette(elements, "fore");
					elements.find(".wheel").farbtastic(elements.find("input:text"));
				}

				$.modal(elements.html(), {
					maxWidth: Wysiwyg.defaults.formWidth,
					maxHeight: Wysiwyg.defaults.formHeight,
					overlayClose: true,

					onShow: function (dialog) {
						$("input:submit", dialog.data).click(function (e) {
							var color = $('input[name="color"]', dialog.data).val();
							self.color.fore.prev = color;
							self.addColorToPalette("fore", color);

							if ($.browser.msie) {
								Wysiwyg.ui.returnRange();
							}

							Wysiwyg.editorDoc.execCommand('ForeColor', false, color);
							$.modal.close();
							return false;
						});
						$("input:reset", dialog.data).click(function (e) {
							if ($.browser.msie) {
								Wysiwyg.ui.returnRange();
							}

							$.modal.close();
							return false;
						});
						$("fieldset", dialog.data).click(function (e) {
							e.stopPropagation();
						});
					},

					onClose: function (dialog) {
						$.wysiwyg.controls.colorpicker.modalOpen = false;
						$.modal.close();
					}
				});
			} else if ($.fn.dialog) {
				elements = $(colorpickerHtml);

				if ($.farbtastic) {
					this.renderPalette(elements, "fore");
					elements.find(".wheel").farbtastic(elements.find("input:text"));
				}

				dialog = elements.appendTo("body");
				dialog.dialog({
					modal: true,

					open: function (event, ui) {
						$("input:submit", elements).click(function (e) {
							var color = $('input[name="color"]', dialog).val();
							self.color.fore.prev = color;
							self.addColorToPalette("fore", color);

							if ($.browser.msie) {
								Wysiwyg.ui.returnRange();
							}

							Wysiwyg.editorDoc.execCommand('ForeColor', false, color);
							$(dialog).dialog("close");
							return false;
						});
						$("input:reset", elements).click(function (e) {
							if ($.browser.msie) {
								Wysiwyg.ui.returnRange();
							}

							$(dialog).dialog("close");
							return false;
						});
						$('fieldset', elements).click(function (e) {
							e.stopPropagation();
						});
					},

					close: function (event, ui) {
						$.wysiwyg.controls.colorpicker.modalOpen = false;
						dialog.dialog("destroy");
						dialog.remove();
					}
				});
			} else {
				if ($.farbtastic) {
					elements = $("<div/>")
						.css({"position": "fixed",
							"z-index": 2000,
							"left": "50%", "top": "50%", "background": "rgb(0, 0, 0)",
							"margin-top": -1 * Math.round(Wysiwyg.defaults.formHeight / 2),
							"margin-left": -1 * Math.round(Wysiwyg.defaults.formWidth / 2)})
						.html(colorpickerHtml);
					this.renderPalette(elements, "fore");
					elements.find("input[name=color]").val(self.color.fore.prev);
					elements.find(".wheel").farbtastic(elements.find("input:text"));
					$("input:submit", elements).click(function (event) {
						var color = $('input[name="color"]', elements).val();
						self.color.fore.prev = color;
						self.addColorToPalette("fore", color);

						if ($.browser.msie) {
							Wysiwyg.ui.returnRange();
						}

						Wysiwyg.editorDoc.execCommand('ForeColor', false, color);

						$(elements).remove();
						$.wysiwyg.controls.colorpicker.modalOpen = false;
						return false;
					});
					$("input:reset", elements).click(function (event) {

						if ($.browser.msie) {
							Wysiwyg.ui.returnRange();
						}

						$(elements).remove();
						$.wysiwyg.controls.colorpicker.modalOpen = false;
						return false;
					});
					$("body").append(elements);
					elements.click(function (e) {
						e.stopPropagation();
					});
				}
			}
		},

		renderPalette: function (jqObj, type) {
			var palette = jqObj.find(".palette"),
				bind = function () {
					var color = $(this).text();
					jqObj.find("input[name=color]").val(color);
					// farbtastic binds on keyup
					if ($.farbtastic) {
						jqObj.find("input[name=color]").trigger("keyup");
					}
				},
				colorExample,
				colorSelect,
				i;

			for (i = this.color[type].palette.length - 1; i > -1; i -= 1) {
				colorExample = $("<div/>").css({
					"float": "left",
					"width": "16px",
					"height": "16px",
					"margin": "0px 5px 0px 0px",
					"background-color": this.color[type].palette[i]
				});

				colorSelect = $("<li>" + this.color[type].palette[i] + "</li>")
					.css({"float": "left", "list-style": "none"})
					.append(colorExample)
					.bind("click.wysiwyg", bind);

				palette.append(colorSelect).css({"margin": "0px", "padding": "0px"});
			}
		}
	};
})(jQuery);