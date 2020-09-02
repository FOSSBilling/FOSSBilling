/**
 * --------------------------------------------------------------------
 * jQuery-Plugin "visualize"
 * by Scott Jehl, scott@filamentgroup.com
 * http://www.filamentgroup.com
 * Copyright (c) 2009 Filament Group 
 * Dual licensed under the MIT (filamentgroup.com/examples/mit-license.txt) and GPL (filamentgroup.com/examples/gpl-license.txt) licenses.
 * 	
 * --------------------------------------------------------------------
 */
(function($) { 
$.fn.visualize = function(options, container){
	return $(this).each(function(){
		//configuration
		var o = $.extend({
			type: 'bar', //also available: area, pie, line
			width: $(this).width(), //height of canvas - defaults to table height
			height: $(this).height(), //height of canvas - defaults to table height
			appendTitle: true, //table caption text is added to chart
			title: null, //grabs from table caption if null
			appendKey: true, //color key is added to chart
			colors: ['#ae432e','#77ab13','#058dc7','#ef561a','#8d10ee','#5a3b16','#26a4ed','#f45a90','#e9e744'],
			textColors: [], //corresponds with colors array. null/undefined items will fall back to CSS
			parseDirection: 'x', //which direction to parse the table data
			pieMargin: 10, //pie charts only - spacing around pie
			pieLabelsAsPercent: true,
			pieLabelPos: 'inside',
			lineWeight: 4, //for line and area - stroke weight
			lineDots: false, //also available: 'single', 'double'
			dotInnerColor: "#ffffff", // only used for lineDots:'double'
			lineMargin: (options.lineDots?15:0), //for line and area - spacing around lines
			barGroupMargin: 10,
			chartId: '',
			xLabelParser: null, // function to parse labels as values
			valueParser: null, // function to parse values. must return a Number
			chartId: '',
			chartClass: '',
			barMargin: 1, //space around bars in bar chart (added to both sides of bar)
			yLabelInterval: 30, //distance between y labels
			interaction: false // only used for lineDots != false -- triggers mouseover and mouseout on original table
		},options);
		
		//reset width, height to numbers
		o.width = parseFloat(o.width);
		o.height = parseFloat(o.height);
		
		// reset padding if graph is not lines
		if(o.type != 'line' && o.type != 'area' ) {
			o.lineMargin = 0;
		}
		
		var self = $(this);
		
		// scrape data from html table
		var tableData = {};
		var colors = o.colors;
		var textColors = o.textColors;
		
		
		var parseLabels = function(direction){
			var labels = [];
			if(direction == 'x'){
				self.find('thead tr').each(function(i){
					$(this).find('th').each(function(j){
						if(!labels[j]) {
							labels[j] = [];
						}
						labels[j][i] = $(this).text()
					})
				});
			}
			else {
				self.find('tbody tr').each(function(i){
					$(this).find('th').each(function(j) {
						if(!labels[i]) {
							labels[i] = [];
						}
						labels[i][j] = $(this).text()
					});
				});
			}
			return labels;
		};
		
		var fnParse = o.valueParser || parseFloat;
		var dataGroups = tableData.dataGroups = [];
		if(o.parseDirection == 'x'){
			self.find('tbody tr').each(function(i,tr){
				dataGroups[i] = {};
				dataGroups[i].points = [];
				dataGroups[i].color = colors[i];
				if(textColors[i]){ dataGroups[i].textColor = textColors[i]; }
				$(tr).find('td').each(function(j,td){
					dataGroups[i].points.push( {
						value: fnParse($(td).text()),
						elem: td,
						tableCords: [i,j]
					} );
				});
			});
		} else {
			var cols = self.find('tbody tr:eq(0) td').size();
			for(var i=0; i<cols; i++){
				dataGroups[i] = {};
				dataGroups[i].points = [];
				dataGroups[i].color = colors[i];
				if(textColors[i]){ dataGroups[i].textColor = textColors[i]; }
				self.find('tbody tr').each(function(j){
					dataGroups[i].points.push( {
						value:  $(this).find('td').eq(i).text()*1,
						elem: this,
						tableCords: [i,j]
					} );
				});
			};
		}
		
	
		var allItems = tableData.allItems = [];
		$(dataGroups).each(function(i,row){
			var count = 0;
			$.each(row.points,function(j,point){
				allItems.push(point);
				count += point.value;
			});
			row.groupTotal = count;
		});
		
		tableData.dataSum = 0;
		tableData.topValue = 0;
		tableData.bottomValue = Infinity;
		$.each(allItems,function(i,item){
			tableData.dataSum += fnParse(item.value);
			if(fnParse(item.value,10)>tableData.topValue) {
				tableData.topValue = fnParse(item.value,10);
			}
			if(item.value<tableData.bottomValue) {
				tableData.bottomValue = fnParse(item.value);	
			}
		});
		var dataSum = tableData.dataSum;
		var topValue = tableData.topValue;
		var bottomValue = tableData.bottomValue;
		
		var xAllLabels = tableData.xAllLabels = parseLabels(o.parseDirection);
		var yAllLabels = tableData.yAllLabels = parseLabels(o.parseDirection==='x'?'y':'x');
	
		var xLabels = tableData.xLabels = [];
		$.each(tableData.xAllLabels,function(i,labels) {
			tableData.xLabels.push(labels[0]);
		});
		
		var totalYRange = tableData.totalYRange = tableData.topValue - tableData.bottomValue;
		
		var zeroLocX = tableData.zeroLocX = 0;
		
		if($.isFunction(o.xLabelParser)) {
			
			var xTopValue = null;
			var xBottomValue = null;

			$.each(xLabels,function(i,label) {
				label = xLabels[i] = o.xLabelParser(label);
				if(i === 0) {
					xTopValue = label;
					xBottomValue = label;
				}
				if(label>xTopValue) {
					xTopValue = label;
				}
				if(label<xBottomValue) {
					xBottomValue = label;
				}
			});

			var totalXRange = tableData.totalXRange = xTopValue - xBottomValue;

			
			var	xScale = tableData.xScale = (o.width -2*o.lineMargin) / totalXRange;
			var marginDiffX = 0;
			if(o.lineMargin) {
				var marginDiffX = -2*xScale-o.lineMargin;
			}
			zeroLocX = tableData.zeroLocX = xBottomValue + o.lineMargin;
			
			tableData.xBottomValue = xBottomValue;
			tableData.xTopValue = xTopValue;
			tableData.totalXRange = totalXRange;
		}
		
		var	yScale = tableData.yScale = (o.height - 2*o.lineMargin) / totalYRange;
		var zeroLocY = tableData.zeroLocY = (o.height-2*o.lineMargin) * (tableData.topValue/tableData.totalYRange) + o.lineMargin;
		
		var yLabels = tableData.yLabels = [];

		var numLabels = Math.floor((o.height - 2*o.lineMargin) / 30);
		var loopInterval = tableData.totalYRange / numLabels; //fix provided from lab
		loopInterval = Math.round(parseFloat(loopInterval)/5)*5;
		loopInterval = Math.max(loopInterval, 1);
		// var start = 
		for(var j=Math.round(parseInt(tableData.bottomValue)/5)*5; j<=tableData.topValue+loopInterval; j+=loopInterval){
			yLabels.push(j); 
		}
		if(yLabels[yLabels.length-1] > tableData.topValue+loopInterval) {
			yLabels.pop();
		} else if (yLabels[yLabels.length-1] <= tableData.topValue-10) {
			yLabels.push(tableData.topValue);
		}
				
		// populate some data
		$.each(dataGroups,function(i,row){
			row.yLabels = tableData.yAllLabels[i];
			$.each(row.points, function(j,point){
				point.zeroLocY = tableData.zeroLocY;
				point.zeroLocX = tableData.zeroLocX;
				point.xLabels = tableData.xAllLabels[j];
				point.yLabels = tableData.yAllLabels[i];
				point.color = row.color;
			});
		});
		
		try{console.log(tableData);}catch(e){}
		
		var charts = {};
		
		charts.pie = {
			interactionPoints: dataGroups,
			
			setup: function() {
				charts.pie.draw(true);
			},
			draw: function(drawHtml){	

				var centerx = Math.round(canvas.width()/2);
				var centery = Math.round(canvas.height()/2);
				var radius = centery - o.pieMargin;				
				var counter = 0.0;

				if(drawHtml) {
					canvasContain.addClass('visualize-pie');

					if(o.pieLabelPos == 'outside'){ canvasContain.addClass('visualize-pie-outside'); }	

					var toRad = function(integer){ return (Math.PI/180)*integer; };
					var labels = $('<ul class="visualize-labels"></ul>')
						.insertAfter(canvas);
				}


				//draw the pie pieces
				$.each(dataGroups, function(i,row){
					var fraction = row.groupTotal / dataSum;
                    if (fraction <= 0 || isNaN(fraction))
                        return;
					ctx.beginPath();
					ctx.moveTo(centerx, centery);
					ctx.arc(centerx, centery, radius, 
						counter * Math.PI * 2 - Math.PI * 0.5,
						(counter + fraction) * Math.PI * 2 - Math.PI * 0.5,
		                false);
			        ctx.lineTo(centerx, centery);
			        ctx.closePath();
			        ctx.fillStyle = dataGroups[i].color;
			        ctx.fill();
			        // draw labels
					if(drawHtml) {
				       	var sliceMiddle = (counter + fraction/2);
				       	var distance = o.pieLabelPos == 'inside' ? radius/1.5 : radius +  radius / 5;
				        var labelx = Math.round(centerx + Math.sin(sliceMiddle * Math.PI * 2) * (distance));
				        var labely = Math.round(centery - Math.cos(sliceMiddle * Math.PI * 2) * (distance));
				        var leftRight = (labelx > centerx) ? 'right' : 'left';
				        var topBottom = (labely > centery) ? 'bottom' : 'top';
				        var percentage = parseFloat((fraction*100).toFixed(2));

						// interaction variables
						row.canvasCords = [labelx,labely];
						row.zeroLocY = tableData.zeroLocY = 0; // related to zeroLocY and plugin API
						row.zeroLocX = tableData.zeroLocX = 0; // related to zeroLocX and plugin API
						row.value = row.groupTotal;


				        if(percentage){
				        	var labelval = (o.pieLabelsAsPercent) ? percentage + '%' : row.groupTotal;
					        var labeltext = $('<span class="visualize-label">' + labelval +'</span>')
					        	.css(leftRight, 0)
					        	.css(topBottom, 0);
					        	if(labeltext)
				        var label = $('<li class="visualize-label-pos"></li>')
				       			.appendTo(labels)
				        		.css({left: labelx, top: labely})
				        		.append(labeltext);	
				        labeltext
				        	.css('font-size', radius / 8)
				        	.css('margin-'+leftRight, -labeltext.width()/2)
				        	.css('margin-'+topBottom, -labeltext.outerHeight()/2);

				        if(dataGroups[i].textColor){ labeltext.css('color', dataGroups[i].textColor); }

				        }
					}
			      	counter+=fraction;
				});
			}
		};
		
		(function(){
			
			var xInterval;

			var drawPoint = function (ctx,x,y,color,size) {
				ctx.moveTo(x,y);
				ctx.beginPath();
				ctx.arc(x,y,size/2,0,2*Math.PI,false);
				ctx.closePath();
				ctx.fillStyle = color;
				ctx.fill();
			};

			charts.line = {
				
				interactionPoints: allItems,

				setup: function(area){

					if(area){ canvasContain.addClass('visualize-area'); }
					else{ canvasContain.addClass('visualize-line'); }

					//write X labels
					var xlabelsUL = $('<ul class="visualize-labels-x"></ul>')
						.width(canvas.width())
						.height(canvas.height())
						.insertBefore(canvas);

					if(!o.customXLabels) {
						xInterval = (canvas.width() - 2*o.lineMargin) / (xLabels.length -1);
						$.each(xLabels, function(i){ 
							var thisLi = $('<li><span>'+this+'</span></li>')
								.prepend('<span class="line" />')
								.css('left', o.lineMargin + xInterval * i)
								.appendTo(xlabelsUL);						
							var label = thisLi.find('span:not(.line)');
							var leftOffset = label.width()/-2;
							if(i == 0){ leftOffset = 0; }
							else if(i== xLabels.length-1){ leftOffset = -label.width(); }
							label
								.css('margin-left', leftOffset)
								.addClass('label');
						});
					} else {
						o.customXLabels(tableData,xlabelsUL);
					}

					//write Y labels
					var liBottom = (canvas.height() - 2*o.lineMargin) / (yLabels.length-1);
					var ylabelsUL = $('<ul class="visualize-labels-y"></ul>')
						.width(canvas.width())
						.height(canvas.height())
						// .css('margin-top',-o.lineMargin)
						.insertBefore(scroller);

					$.each(yLabels, function(i){
						var value = Math.floor(this);
						var posB = (value-bottomValue)*yScale + o.lineMargin;
						if(posB >= o.height-1 || posB < 0) {
							return;
						}
						var thisLi = $('<li><span>'+value+'</span></li>')
							.css('bottom', posB);
						if(Math.abs(posB) < o.height-1) {
							thisLi.prepend('<span class="line"  />');
						}
						thisLi.prependTo(ylabelsUL);
						
						var label = thisLi.find('span:not(.line)');
						var topOffset = label.height()/-2;
						if(!o.lineMargin) {
							if(i == 0){ topOffset = -label.height(); }
							else if(i== yLabels.length-1){ topOffset = 0; }
						}
						label
							.css('margin-top', topOffset)
							.addClass('label');
					});
					
					//start from the bottom left
					ctx.translate(zeroLocX,zeroLocY);
					
					charts.line.draw(area);

				},
				
				draw: function(area) {
					// prevent drawing on top of previous draw
					ctx.clearRect(-zeroLocX,-zeroLocY,o.width,o.height);
					// Calculate each point properties before hand
					var integer;
					$.each(dataGroups,function(i,row){
						integer = o.lineMargin; // the current offset
						$.each(row.points, function(j,point){
							if(o.xLabelParser) {
								point.canvasCords = [(xLabels[j]-zeroLocX)*xScale - xBottomValue,-(point.value*yScale)];
							} else {
								point.canvasCords = [integer,-(point.value*yScale)];
							}
							
							if(o.lineDots) {
								point.dotSize = o.dotSize||o.lineWeight*Math.PI;
								point.dotInnerSize = o.dotInnerSize||o.lineWeight*Math.PI/2;
								if(o.lineDots == 'double') {
									point.innerColor = o.dotInnerColor;
								}
							}
							integer+=xInterval;
						});
					});
					// fire custom event so we can enable rich interaction
					self.trigger('vizualizeBeforeDraw',{options:o,table:self,canvasContain:canvasContain,tableData:tableData});
					// draw lines and areas
					$.each(dataGroups,function(h){
						// Draw lines
						ctx.beginPath();
						ctx.lineWidth = o.lineWeight;
						ctx.lineJoin = 'round';
						$.each(this.points, function(g){
							var loc = this.canvasCords;
							if(g == 0) {
								ctx.moveTo(loc[0],loc[1]);
							}
							ctx.lineTo(loc[0],loc[1]);
						});
						ctx.strokeStyle = this.color;
						ctx.stroke();
						// Draw fills
						if(area){
							var integer = this.points[this.points.length-1].canvasCords[0];
							if (isFinite(integer))
								ctx.lineTo(integer,0);
							ctx.lineTo(o.lineMargin,0);
							ctx.closePath();
							ctx.fillStyle = this.color;
							ctx.globalAlpha = .3;
							ctx.fill();
							ctx.globalAlpha = 1.0;
						}
						else {ctx.closePath();}
					});
					// draw points
					if(o.lineDots) {
						$.each(dataGroups,function(h){
							$.each(this.points, function(g){
								drawPoint(ctx,this.canvasCords[0],this.canvasCords[1],this.color,this.dotSize);
								if(o.lineDots === 'double') {
									drawPoint(ctx,this.canvasCords[0],this.canvasCords[1],this.innerColor,this.dotInnerSize);
								}
							});
						});
					}
					
				}
			};
		
		})();
		
		charts.area = {
			setup: function() {
				charts.line.setup(true);
			},
			draw: charts.line.draw
		};
		
		(function(){

			var horizontal,bottomLabels;

			charts.bar = {
				setup:function(){
					/**
					 * We can draw horizontal or vertical bars depending on the
					 * value of the 'barDirection' option (which may be 'vertical' or
					 * 'horizontal').
					 */

					horizontal = (o.barDirection == 'horizontal');

					canvasContain.addClass('visualize-bar');

					/**
					 * Write labels along the bottom of the chart.	If we're drawing
					 * horizontal bars, these will be the yLabels, otherwise they
					 * will be the xLabels.	The positioning also varies slightly:
					 * yLabels are values, hence they will span the whole width of
					 * the canvas, whereas xLabels are supposed to line up with the
					 * bars.
					 */
					bottomLabels = horizontal ? yLabels : xLabels;

					var xInterval = canvas.width() / (bottomLabels.length - (horizontal ? 1 : 0));

					var xlabelsUL = $('<ul class="visualize-labels-x"></ul>')
						.width(canvas.width())
						.height(canvas.height())
						.insertBefore(canvas);

					$.each(bottomLabels, function(i){
						var thisLi = $('<li><span class="label">'+this+'</span></li>')
							.prepend('<span class="line" />')
							.css('left', xInterval * i)
							.width(xInterval)
							.appendTo(xlabelsUL);

						if (horizontal)	{
							var label = thisLi.find('span.label');
							label.css("margin-left", -label.width() / 2);
						}
					});

					/**
					 * Write labels along the left of the chart.	Follows the same idea
					 * as the bottom labels.
					 */
					var leftLabels = horizontal ? xLabels : yLabels;
					var liBottom = canvas.height() / (leftLabels.length - (horizontal ? 0 : 1));

					var ylabelsUL = $('<ul class="visualize-labels-y"></ul>')
						.width(canvas.width())
						.height(canvas.height())
						.insertBefore(canvas);

					$.each(leftLabels, function(i){
						var thisLi = $('<li><span>'+this+'</span></li>').prependTo(ylabelsUL);

						var label = thisLi.find('span:not(.line)').addClass('label');

						if (horizontal) {
							/**
							 * For left labels, we want to vertically align the text
							 * to the middle of its container, but we don't know how
							 * many lines of text we will have, since the labels could
							 * be very long.
							 *
							 * So we set a min-height of liBottom, and a max-height
							 * of liBottom + 1, so we can then check the label's actual
							 * height to determine if it spans one line or more lines.
							 */
							label.css({
								'min-height': liBottom,
								'max-height': liBottom + 1,
								'vertical-align': 'middle'
							});
							thisLi.css({'top': liBottom * i, 'min-height': liBottom});

							var r = label[0].getClientRects()[0];
							if (r.bottom - r.top == liBottom) {
								/* This means we have only one line of text; hence
								 * we can centre the text vertically by setting the line-height,
								 * as described at:
								 *   http://www.ampsoft.net/webdesign-l/vertical-aligned-nav-list.html
								 *
								 * (Although firefox has .height on the rectangle, IE doesn't,
								 * so we use r.bottom - r.top rather than r.height.)
								 */
								label.css('line-height', parseInt(liBottom) + 'px');
							}
							else {
								/*
								 * If there is more than one line of text, then we shouldn't
								 * touch the line height, but we should make sure the text
								 * doesn't overflow the container.
								 */
								label.css("overflow", "hidden");
							}
						}
						else {
							thisLi.css('bottom', liBottom * i).prepend('<span class="line" />');
							label.css('margin-top', -label.height() / 2)
						}
					});

					charts.bar.draw();

				},

				draw: function() {
					// Draw bars

					if (horizontal) {
						// for horizontal, keep the same code, but rotate everything 90 degrees
						// clockwise.
						ctx.rotate(Math.PI / 2);
					}
					else {
						// for vertical, translate to the top left corner.
						ctx.translate(0, zeroLocY);
					}

					// Don't attempt to draw anything if all the values are zero,
					// otherwise we will get weird exceptions from the canvas methods.
					if (totalYRange <= 0)
						return;

					var yScale = (horizontal ? canvas.width() : canvas.height()) / totalYRange;
					var barWidth = horizontal ? (canvas.height() / xLabels.length) : (canvas.width() / (bottomLabels.length));
					var linewidth = (barWidth - o.barGroupMargin*2) / dataGroups.length;

					for(var h=0; h<dataGroups.length; h++){
						ctx.beginPath();

						var strokeWidth = linewidth - (o.barMargin*2);
						ctx.lineWidth = strokeWidth;
						var points = dataGroups[h].points;
						var integer = 0;
						for(var i=0; i<points.length; i++){
							// If the last value is zero, IE will go nuts and not draw anything,
							// so don't try to draw zero values at all.
							if (points[i].value != 0) {
								var xVal = (integer-o.barGroupMargin)+(h*linewidth)+linewidth/2;
								xVal += o.barGroupMargin*2;

								ctx.moveTo(xVal, 0);
								ctx.lineTo(xVal, Math.round(-points[i].value*yScale));
	                        }
							integer+=barWidth;
						}
						ctx.strokeStyle = dataGroups[h].color;
						ctx.stroke();
						ctx.closePath();
					}

				}
			};
			
		})();
	
		//create new canvas, set w&h attrs (not inline styles)
		var canvasNode = document.createElement("canvas"); 
		var canvas = $(canvasNode)
			.attr({
				'height': o.height,
				'width': o.width
			});
		
		//get title for chart
		var title = o.title || self.find('caption').text();
		
		//create canvas wrapper div, set inline w&h, append
		var canvasContain = (container || $('<div '+(o.chartId?'id="'+o.chartId+'" ':'')+'class="visualize '+o.chartClass+'" role="img" aria-label="Chart representing data from the table: '+ title +'" />'))
			.height(o.height)
			.width(o.width);
		
		var scroller = $('<div class="visualize-scroller"></div>')
			.appendTo(canvasContain)
			.append(canvas);

		//title/key container
		if(o.appendTitle || o.appendKey){
			var infoContain = $('<div class="visualize-info"></div>')
				.appendTo(canvasContain);
		}
		
		//append title
		if(o.appendTitle){
			$('<div class="visualize-title">'+ title +'</div>').appendTo(infoContain);
		}
		
		
		//append key
		if(o.appendKey){
			var newKey = $('<ul class="visualize-key"></ul>');
			$.each(yAllLabels, function(i,label){
				$('<li><span class="visualize-key-color" style="background: '+dataGroups[i].color+'"></span><span class="visualize-key-label">'+ label +'</span></li>')
					.appendTo(newKey);
			});
			newKey.appendTo(infoContain);
		};		
		
		// init interaction
		if(o.interaction) {
			// sets the canvas to track interaction
			// IE needs one div on top of the canvas since the VML shapes prevent mousemove from triggering correctly.
			// Pie charts needs tracker because labels goes on top of the canvas and also messes up with mousemove
			var tracker = $('<div class="visualize-interaction-tracker"/>')
				.css({
					'height': o.height + 'px',
					'width': o.width + 'px',
					'position':'relative',
					'z-index': 200
				})
				.insertAfter(canvas);

			var triggerInteraction = function(overOut,data) {
				var data = $.extend({
					canvasContain:canvasContain,
					tableData:tableData
				},data);
				self.trigger('vizualize'+overOut,data);
			};

			var over=false, last=false, started=false;
			tracker.mousemove(function(e){
				var x,y,x1,y1,data,dist,i,current,selector,zLabel,elem,color,minDist,found,ev=e.originalEvent;

				// get mouse position relative to the tracker/canvas
				x = ev.layerX || ev.offsetX || 0;
				y = ev.layerY || ev.offsetY || 0;

				found = false;
				minDist = started?30000:(o.type=='pie'?(Math.round(canvas.height()/2)-o.pieMargin)/3:o.lineWeight*4);
				// iterate datagroups to find points with matching
				$.each(charts[o.type].interactionPoints,function(i,current){
					x1 = current.canvasCords[0] + zeroLocX;
					y1 = current.canvasCords[1] + (o.type=="pie"?0:zeroLocY);
					dist = Math.sqrt( (x1 - x)*(x1 - x) + (y1 - y)*(y1 - y) );
					if(dist < minDist) {
						found = current;
						minDist = dist;
					}
				});
				
				if(o.multiHover && found) {
					x = found.canvasCords[0] + zeroLocX;
					y = found.canvasCords[1] + (o.type=="pie"?0:zeroLocY);
					found = [found];
					$.each(charts[o.type].interactionPoints,function(i,current){
						if(current == found[0]) {return;}
						x1 = current.canvasCords[0] + zeroLocX;
						y1 = current.canvasCords[1] + zeroLocY;
						dist = Math.sqrt( (x1 - x)*(x1 - x) + (y1 - y)*(y1 - y) );
						if(dist <= o.multiHover) {
							found.push(current);
						}
					});
				}
				// trigger over and out only when state changes, instead of on every mousemove
				over = found;
				if(over != last) {
					if(over) {
						if(last) {
							triggerInteraction('Out',{point:last});
						}
						triggerInteraction('Over',{point:over});
						last = over;
					}
					if(last && !over) {
						triggerInteraction('Out',{point:last});
						last=false;
					}
					started=true;
				}
			});
			tracker.mouseleave(function(){
				triggerInteraction('Out',{
					point:last,
					mouseOutGraph:true
				});
				over = (last = false);
			});
		}
		
		//append new canvas to page
		if(!container){canvasContain.insertAfter(this); }
		if( typeof(G_vmlCanvasManager) != 'undefined' ){ G_vmlCanvasManager.init(); G_vmlCanvasManager.initElement(canvas[0]); }	
		
		//set up the drawing board
		var ctx = canvas[0].getContext('2d');
		
		// Scroll graphs
		scroller.scrollLeft(o.width-scroller.width());

		// init plugins
		$.each($.visualizePlugins,function(i,plugin){
			plugin.call(self,o,tableData);
		});

		//create chart
		charts[o.type].setup();
		
		if(!container){
			//add event for updating
			self.bind('visualizeRefresh', function(){
				self.visualize(o, $(this).empty()); 
			});
			//add event for redraw
			self.bind('visualizeRedraw', function(){
				charts[o.type].draw();
			});
		}
	}).next(); //returns canvas(es)
};
// create array for plugins. if you wish to make a plugin,
// just push your init funcion into this array
$.visualizePlugins = [];

})(jQuery);