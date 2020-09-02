var working_path = '/';
var $editor_tabs;
var openFiles = [];

// Array Remove - By John Resig (MIT Licensed)
Array.prototype.remove = function(from, to) {
  var rest = this.slice((to || from) + 1 || this.length);
  this.length = from < 0 ? this.length + from : from;
  return this.push.apply(this, rest);
};

$(function(){
	$(document).disableTextSelect();
	$editor_tabs = $("#edit-content").tabs({
		tabTemplate: '<li><a href="#{href}"><span>#{label}</span></a><span class="ui-icon ui-icon-close"></span></li>',
		add: function(event, ui) {
			var dpath = openFiles[ui.index].path;
			var npath = bbUrl+'/filemanager/editor&file='+dpath;
			var html = '<iframe class="editor-iframe" src="'+npath+'"></iframe>';
            $(ui.tab).parent().find('span.ui-icon-close').attr('data-path', dpath);
			$(ui.panel).html(html);
			resizeStage();
			$editor_tabs.tabs('select',ui.index);
		},
		create: function(event, ui) { 
			setTimeout(function(){ resizeStage(); },1000);
		}
	});
	$( "#edit-content" ).delegate('li span.ui-icon-close', "click", function() {
		var index = $( "li", $editor_tabs ).index( $( this ).parent() );
		$editor_tabs.tabs( "remove", index );
        dpath = $( this ).attr('data-path');
        for(var i=0;i < openFiles.length; i++){
            if(openFiles[i].path == dpath){
                openFiles.remove(i);
            }
        }
	});
    
	loadFiles(working_path);
	
	$("#edit-dir").click(function(e){
		$("#path-tree").stop(true,true).slideToggle();
	});
	$("#edit-files").mouseleave(function(e){
		$("#path-tree").stop(true,true).slideUp();
	});
	$("#path-tree li").on('click',function(e){
		mx = $("#path-tree li").index(this);
		var newpath = $(this).attr('data-path');
		if(working_path == newpath){
			$("#path-tree").stop(true,true).hide();
			return false;
		}
		$("#path-tree li").each(function(idx,ele){
			if(idx==mx){
				return idx;
			}else{
				$(ele).remove();
			}
		});
		working_path = newpath;
		$("#select-dir").text(working_path).attr('data-path',working_path);
		loadFiles(working_path);
		$("#path-tree").stop(true,true).hide();
	});

	resizeStage();
	setTimeout(function(){ resizeStage(); },100);
	
});
function loadFiles(dir){
	if(dir.substr(dir.length-4) == '..//'){
		dirarray = working_path.split('/');
		dirarray.pop();
		dirarray.pop();
		var newdir = dirarray.join('/');
		if(newdir.length == 0){
			newdir = '/';
		}
		dir = newdir;
	}
	working_path = dir;

	$("#select-dir").text(dir);
	$("#select-dir").attr('data-path', dir);
	
	var pathtree = dir.split('/');
	pathtree.pop();
	var tree = '';
	$("#path-tree").html('');
	$(pathtree).each(function(idx){
		tree += pathtree[idx]+'/';
		$("#path-tree").prepend('<li data-path="'+tree+'"><span class="icon icon-dir"></span> '+tree+'</li>');
	});
	

	jQuery.post(apiUrl+'admin/filemanager/get_list',{path:dir},function(result){
		$("#file-list").html('');
		var html = '';
        d = result.result;
		if(d.filecount == 0){
			
		}else{
			for(var i=0;i<d.files.length;i++){
				switch(d.files[i].type){
					case 'dir':
						var class_val = 'icon icon-dir';
						break;
					case 'file':
						var class_val = 'icon icon-file';
						break;
				}
				html += '<li class="file-'+d.files[i].type+'" data-filename="'+d.files[i].filename+'" data-path="'+d.files[i].path+'"><span class="'+class_val+'"></span><span class="file-name">'+d.files[i].filename+'</span></li>';
			}
		}
		$("#file-list").html(html);

		if(working_path != '/'){
			$("#file-list").prepend('<li class="file-dir" data-filename="/../" data-path="'+absoDir+"/"+working_path+'/../"><span class="icon icon-dir"></span><span class="file-name">..</span></li>');
		}


		$("#file-list li").draggable({
			revert: 'invalid'
		});
		$("#file-list li.file-dir").droppable({
			hoverClass: 'selected-item',
			over: function(event, ui) {},
			drop: function(event,ui){
				from = $(ui.draggable).attr('data-path');
				to = $(this).attr('data-path');
				$.post(apiUrl+'admin/filemanager/move_file',{path:from, to:to},function(d){
					loadFiles(working_path);
				});
				$(ui.draggable).fadeOut('fast');
			}
		});
        $('#file-list li').on('click', function(e){
			$(this).siblings().removeClass('selected-item');
			$(this).addClass('selected-item');
		});
        $('#file-list li.file-file').on('dblclick', function(e){
			var idx = $editor_tabs.tabs("length");
			var path = $(this).attr('data-path');
			var filename = $(this).attr('data-filename');
			var newid ="tab-"+idx;
			var item = {'path':path,'name':filename};
            
			for(var i=0;i < openFiles.length; i++){
				if(openFiles[i].path == path){
					$editor_tabs.tabs('select',i);
					return false;			
				}
			}
			
			openFiles[idx] = item;
			$editor_tabs.tabs('add', "#"+newid, filename);
			$("#"+newid).attr('data-path',path);
		});
        $('#file-list li.file-dir').on('dblclick', function(e){
			var newpath = working_path+$(this).attr('data-filename')+"/";
			loadFiles(newpath);
		});		
        
        if(openFile) {
            $('li[data-filename="'+openFile+'"]').dblclick();
        }
	});

}

function newDir(){
	var txt = prompt("Directory name:");
	if(txt.length > 0){
		mkpath = working_path+'/'+txt;
		$.post(apiUrl+'admin/filemanager/new_item',{path:mkpath,type:'dir'},function(d){
			loadFiles(working_path);
		});
	}
}
function newFile(){
	var txt = prompt("File name:");
	if(txt.length > 0){
		mkpath = working_path+'/'+txt;
		$.post(apiUrl+'admin/filemanager/new_item',{path:mkpath,type:'file'},function(d){
			loadFiles(working_path);
		});
	}
}

$(window).resize(function(){
	resizeStage();
});

function resizeStage(){
	var h = $(window).height() - $("#main-nav").outerHeight();
	$("#edit-files").css('height',h+'px');
	var w = $(window).width() - $("#edit-files").outerWidth();
	$("#edit-content").css('height',h+'px');
	$("#edit-content").css('width',w+'px');
	var fh = h - 25;
	$(".editor-iframe").css('width',w+'px');
	$(".editor-iframe").css('height',fh+'px');
}
function oc(a)
{
  var o = {};
  for(var i=0;i<a.length;i++)
  {
    o[a[i]]='';
  }
  return o;
}
