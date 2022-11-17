// ----------------------------------------------------------------------------
// markItUp!
// ----------------------------------------------------------------------------
// Copyright (C) 2011 Jay Salvat
// http://markitup.jaysalvat.com/
// ----------------------------------------------------------------------------
// Html tags
// http://en.wikipedia.org/wiki/html
// ----------------------------------------------------------------------------
// Basic set. Feel free to add more tags
// ----------------------------------------------------------------------------
function returnSettings(assetPath){
	var mySettings = {
		previewParser: function(content) {
			var r = '';
			$.ajax({
				url: $('meta[property="bb:client_area"]').attr("content") + 'preview/markdown',
				data: {content: content},
				type: "POST",
				success: function(data){ r = data; },
				async: false
			});
			return r;
		}, 
		onShiftEnter:  	{keepDefault:false, openWith:'\n\n'},
		onTab:    		{keepDefault:false, replaceWith:'    '},
		markupSet:  [ 	
			{name:'Bold', key:'B', openWith:'**', closeWith:'**'},
			{name:'Italic', key:'I', openWith:'_', closeWith:'_'},
			{name:'Strike Through', key:'S', openWith:'~~', closeWith:'~~' },
			{separator:'---------------' },
			{name:'Bulleted List', openWith:'- '},
			{name:'Numeric List', openWith:function(markItUp) {
				return markItUp.line+'. ';
			}},
			{separator:'---------------' },
			{name:'Picture', key:'P', replaceWith:'![[![Alternative text]!]]([![Url:!:http://]!])'},
			{name:'Link', key:'L', openWith:'[', closeWith:']([![Url:!:http://]!])', placeHolder:'Your text to link here...'},
			{separator:'---------------' },
			{name:'Clean', className:'clean', replaceWith:function(markitup) { return markitup.selection.replace(/<(.*?)>/g, "") } },		
			{name:'Preview', className:'preview',  call:'preview'}
		],
		previewTemplatePath: assetPath
	}
	return mySettings;
}
