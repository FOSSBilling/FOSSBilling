(function($) {

	$.wysiwyg.controls = {
		bold: {
			tags: ["b", "strong"],
			css: {
				fontWeight: "bold"
			},
			tooltip: "Bold",
			hotkey: { "ctrl": 1, "key": 66 }
		},
		highlight: {
			tooltip:   "Highlight",
			className: "highlight",
			css:{
				backgroundColor: "rgb(255, 255, 102)"
			},
			command: ($.browser.msie || $.browser.safari) ? "backcolor" : "hilitecolor",
			exec: function(wysiwyg){}
		},		
		h1: {
			className: "h1",
			command: ($.browser.msie || $.browser.safari) ? "FormatBlock" : "heading",
			args: 	 ($.browser.msie || $.browser.safari) ? "<h1>" : "h1",
			tags: ["h1"],
			tooltip: "Header 1"
		},		
		h2: {
			className: "h2",
			command: ($.browser.msie || $.browser.safari) ? "FormatBlock" : "heading",
			args: 	 ($.browser.msie || $.browser.safari) ? "<h2>" : "h2",
			tags: ["h2"],
			tooltip: "Header 2"
		},		
		h3: {
			className: "h3",
			command: ($.browser.msie || $.browser.safari) ? "FormatBlock" : "heading",
			args: 	 ($.browser.msie || $.browser.safari) ? "<h3>" : "h3",
			tags: ["h3"],
			tooltip: "Header 3"
		},
		indent:{ 
			tooltip: "Indent" 
		},
		insertHorizontalRule:{
			tags: ["hr"],
			tooltip: "Insert Horizontal Rule"
		},
		insertImage: {
			exec:function(){ },
			tags: ["img"],
			tooltip: "Insert image"
		},		
		insertOrderedList: {
			tags: ["ol"],
			tooltip: "Insert Ordered List"
		},		
		insertUnorderedList: {
			tags: ["ul"],
			tooltip: "Insert Unordered List"
		},		
		italic: {
			tags: ["i", "em"],
			css: { fontStyle: "italic" },
			tooltip: "Italic"
		},		
		justifyCenter: {
			tags: ["center"],
			css: { textAlign: "center" },
			tooltip: "Justify Center"
		},
		justifyFull: {
			css: { textAlign: "justify" },
			tooltip: "Justify Full"
		},
		justifyLeft: {
			css: { textAlign: "left" },
			tooltip: "Justify Left"
		},
		justifyRight: {
			css: { textAlign: "right" },
			tooltip: "Justify Right"
		},
		link: {
			exec: function(){},
			tags: ["a"],
			tooltip: "Create link"
		},		
		outdent: { tooltip: "Outdent"},
		paste: { tooltip: "Paste" },
		redo: { tooltip: "Redo" },
		strikeThrough: {
			tags: ["s", "strike"],
			css: { textDecoration: "line-through" },
			tooltip: "Strike-through"
		},
		subscript: {
			tags: ["sub"],
			tooltip: "Subscript"
		},
		superscript: {
			tags: ["sup"],
			tooltip: "Superscript"
		},
		underline: {
			tags: ["u"],
			css: { textDecoration: "underline" },
			tooltip: "Underline"
		},
		undo: {
			tooltip: "Undo"
		}		
	};
	
})(jQuery);