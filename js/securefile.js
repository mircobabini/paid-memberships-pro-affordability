(function ($) {
	tinymce.create('tinymce.plugins.securefile', {  
        init : function(ed, url) {
            ed.addButton('securefile', {  
                title : 'Add a Securefile',  
                image : url+'/icon.png',  
                onclick : function() {  
					var send_attachment_bkp = wp.media.editor.send.attachment;

					wp.media.editor.send.attachment = function(props, attachment) {
	                    ed.selection.setContent('[securefile id="'+attachment.id+'"][/securefile]');  
						wp.media.editor.send.attachment = send_attachment_bkp;
					}

					wp.media.editor.open();
					return false;
                }  
            });  
        },  
        createControl : function(n, cm) {  
            return null;  
        },  
    });  
    tinymce.PluginManager.add('securefile', tinymce.plugins.securefile);  
})(jQuery);