/**
 * --------------------------------------------------------------------
 * Tooltip plugin for the jQuery-Plugin "Visualize"
 * Tolltip by IraĂŞ Carvalho, irae@irae.pro.br, http://irae.pro.br/en/
 * Copyright (c) 2010 IraĂŞ Carvalho
 * Dual licensed under the MIT (filamentgroup.com/examples/mit-license.txt) and GPL (filamentgroup.com/examples/gpl-license.txt) licenses.
 * 	
 * Visualize plugin by Scott Jehl, scott@filamentgroup.com
 * Copyright (c) 2009 Filament Group, http://www.filamentgroup.com
 *
 * --------------------------------------------------------------------
 */

(function($){
	$.visualizePlugins.push(function visualizeTooltip(options,tableData) {
		//configuration
		var o = $.extend({
			tooltip: false,
			tooltipalign: 'auto', // also available 'left' and 'right'
			tooltipvalign: 'top',
			tooltipclass: 'visualize-tooltip',
			tooltiphtml: function(data){
				if(options.multiHover) {
					var html='';
					for(var i=0;i<data.point.length;i++){
						html += '<p>'+data.point[i].value+' - '+data.point[i].yLabels[0]+'</p>';
					}
					return html;					
				} else {
					return '<p>'+data.point.value+' - '+data.point.yLabels[0]+'</p>';
				}
			},
			delay:false
		},options);
		
		// don't go any further if we are not to show anything
		if(!o.tooltip) {return;}
		
		var self = $(this),
			canvasContain = self.next(),
			scroller = canvasContain.find('.visualize-scroller'),
			scrollerW = scroller.width(),
			tracker = canvasContain.find('.visualize-interaction-tracker');
		
		// IE needs background color and opacity white or the tracker stays behind the tooltip
		tracker.css({
			backgroundColor:'white',
			opacity:0,
			zIndex:100
		});
		
		var tooltip = $('<div class="'+o.tooltipclass+'"/>').css({
				position:'absolute',
				display:'none',
				zIndex:90
			})
			.insertAfter(scroller.find('canvas'));

		var usescroll = true;
		
		if( typeof(G_vmlCanvasManager) != 'undefined' ){
			scroller.css({'position':'absolute'});
			tracker.css({marginTop:'-'+(o.height)+'px'});
		}
		
		
		self.bind('vizualizeOver',function visualizeTooltipOver(e,data){
			if(data.canvasContain.get(0) != canvasContain.get(0)) {return;} // for multiple graphs originated from same table
			if(o.multiHover) {
				var p = data.point[0].canvasCords;
			} else {
				var p = data.point.canvasCords;
			}
			var left,right,top,clasRem,clasAd,bottom,x=Math.round(p[0]+data.tableData.zeroLocX),y=Math.round(p[1]+data.tableData.zeroLocY);
			if(o.tooltipalign == 'left' || ( o.tooltipalign=='auto' && x-scroller.scrollLeft()<=scrollerW/2 ) ) {
				if($.browser.msie && ($.browser.version == 7 || $.browser.version == 6) ) {usescroll=false;} else {usescroll=true;}
				left = (x-(usescroll?scroller.scrollLeft():0))+'px';
				right = '';
				clasAdd="tooltipleft";
				clasRem="tooltipright";
			} else {
				if($.browser.msie && $.browser.version == 7) {usescroll=false;} else {usescroll=true;}
				left = '';
				right = (Math.abs(x-o.width)- (o.width-(usescroll?scroller.scrollLeft():0)-scrollerW) )+'px';
				clasAdd="tooltipright";
				clasRem="tooltipleft";
			}
			
			tooltip
				.addClass(clasAdd)
				.removeClass(clasRem)
				.html(o.tooltiphtml(data))
				.css({
					display:'block',
					top: y+'px',
					left: left,
					right: right
				});
		});
		
		self.bind('vizualizeOut',function visualizeTooltipOut(e,data){
			tooltip.css({display:'none'});
		});
		
	});
})(jQuery);