jQuery(function($){
$('input:file[multiple]').change(
function(e){
    console.log(e.currentTarget.files);
    var numFiles = e.currentTarget.files.length;
        for (i=0;i<numFiles;i++){
            fileSize = parseInt(e.currentTarget.files[i].size, 10)/1024;
            filesize = Math.round(fileSize);
            $('<li />').text(e.currentTarget.files[i].name).appendTo($(this).siblings('.output'));
            $('<span />').addClass('filesize').text('(' + filesize + 'kb)').appendTo($(this).siblings('.output li:last'));
        }
});
});