(function ($) {
	$.wysiwyg.dialog.register("default", function () {
		var that = this;
		
		this._$dialog = null;
		
		this.init = function() {
			var abstractDialog	= this,
				content 		= this.options.content;
				
			if(typeof content == 'object') {
				if(typeof content.html == 'function') {
					content = content.html();
				}
				else if(typeof content.toString == 'function') {
					content = content.toString();
				}
			}
			
			that._$dialog = $('<div class="wysiwyg-dialog"></div>');
			
			var $topbar = $('<div class="wysiwyg-dialog-topbar"><div class="wysiwyg-dialog-close-wrapper"></div><div class="wysiwyg-dialog-title">'+this.options.title+'</div></div>');
			var $link = $('<a href="#" class="wysiwyg-dialog-close-button">X</a>');
			
			$link.click(function () {
				abstractDialog.close(); // this is important it makes sure that is close from the abstract $.wysiwyg.dialog instace, not just locally 
			});

			$topbar.find('.wysiwyg-dialog-close-wrapper').prepend($link);
			
			var $dcontent = $('<div class="wysiwyg-dialog-content">'+content+'</div>');
			
			that._$dialog.append($topbar).append($dcontent);
			
			that._$dialog.hide();
			
			$("body").append(that._$dialog);
			
			return that._$dialog;
		};
		
		this.show = function () {
			that._$dialog.show();
			return that._$dialog;
		}
		
		this.hide = function () {
			that._$dialog.hide();
			return that._$dialog;
		};
		
		this.destroy = function() {
			that._$dialog.remove();
			return that._$dialog;
		};
	});
})(jQuery);