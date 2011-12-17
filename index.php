<!doctype html>
<html>
<head>
<title>Photos to Share? Drop them here!</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<link href='http://fonts.googleapis.com/css?family=Viga|Tangerine' rel='stylesheet' type='text/css' />
<link href='site.css' rel='stylesheet' type='text/css' />
</head>
<body>
<form>
<h1>Photos to share?</h1>

<div id="progressDiv">
Uploading <span id="doneFiles">0</span> of <span id="totalFiles">0</span> images...
<br />
<progress id="progressBytes" value="0" max="100"></progress>
</div>

<script>
$("#progressDiv").hide();
var totalFilesToUpload = 0;
var filesDoneUploading = 0;
var totalBytesToUpload = 0; // how many bytes are we going to have to upload?
var totalBytesUploaded = 0; // how many bytes have we uploaded already?
var files = []; // array of files being uploaded
var album = ""; // ID for album we're uploading to.
var MAX_IN_FLIGHT = 3; // only allow this many files to be uploaded at once to the server.

// We're done uploading a file (though it may have ended in success or error...!)
function doneFile() {
  filesDoneUploading++;
  $("#doneFiles").html(filesDoneUploading);
  if(filesDoneUploading == totalFilesToUpload){
    doneAllFiles();
  } else {
    uploadAFile();
  }
}

function doneAllFiles() {
  $("#droptarget").show();
  $("#files").show();
  $("#progressDiv").hide();
  files = [];
  // redirect to new album!
   window.location.href = "/ui.php?album="+album;
}

function updateProgress(evt,whatFile,xhr) {
  // console.log('Progress on file '+whatFile+': loaded '+evt.loaded+' of '+evt.total);
  var delta = (evt.loaded - files[whatFile].bytesUploaded);
  totalBytesUploaded += delta;
  $("#progressBytes").val(totalBytesUploaded);
  files[whatFile].bytesUploaded = evt.loaded;
}

// A file finished uploading successfully.
function transferComplete(evt,whatFile,xhr) {
  if(xhr.status != 200){
    alert("Error transferring file "+whatFile+": "+xhr.response);
    files[whatFile].status = 'error';
  } else {
    files[whatFile].status = 'done';
  }
  console.log('Finished upload of file '+whatFile);
  doneFile();
}

// A file'supload was cancelled by the user (e.g. by navigating away)
function transferCancelled(evt,whatFile,xhr) {
  console.log('Cancelled upload of file '+whatFile);
  alert('Cancelled file '+whatFile);
  files[whatFile].status = 'error';
  doneFile();
}

// A transfer failed (due to a network issue or server error)
// TODO: graceful resumes possible?
function transferFailed(evt,whatFile,xhr) {
  console.log('Aborted upload of file '+whatFile);
  alert('Aborted file '+whatFile);
  files[whatFile].status = 'error';
  doneFile();
}

// Find ONE file from the queue that has not yet begun (or finished) uploading and starts the upload process.
function uploadAFile(){
  for (var i=0, f; f = files[i]; i++) {
    if(!files[i].status && files[i].type.match('image.*')){
      files[i].status = 'uploading';

      var xhr = new XMLHttpRequest();  
      xhr.open("PUT", "api/uploadFile.php");
      xhr.setRequestHeader("X-File-Name",f.name);
      xhr.setRequestHeader("X-Size",f.size);
      xhr.setRequestHeader("X-Album",album);
      xhr.overrideMimeType(f.type);

      // Semi-wacky use of closures here to pass through context (file #) to handler.
      // I am *sure* there is a much much better way to do this. Ah, well.
      function _f(f,fileNum,xhrarg) { return (function(evt){f(evt,fileNum,xhrarg)}); }
      xhr.upload.addEventListener("progress", _f(updateProgress,i,xhr), false);
      xhr.addEventListener("load",  _f(transferComplete,i,xhr), false);
      xhr.addEventListener("error", _f(transferFailed,i,xhr), false);
      xhr.addEventListener("abort", _f(transferCancelled,i,xhr), false);
      xhr.send(f);
      return true;
    }
  }
  return false; // nothing more to upload!
}


// upload a new batch of files to the server.
function uploadAndCreateAlbum() {
  // Making a new album; we need to fetch an album ID from the server.
  if(album == ""){
    $.get('api/newAlbum.php',
	  function(data) {
	    album = data.album;
	    console.log('got album '+album+' from the server.');
	    uploadAndCreateAlbum();
	  }, 'json')
      .error(function(x,status,err){ alert(status+": "+err); });
    return;
  }

  // Tally up the total number of files (and bytes) we're planning on sending.
  totalBytesToUpload = 0;
  totalBytesUploaded = 0;
  totalFilesToUpload = 0;
  filesDoneUploading = 0;
  var i;
  var f;
  for (i=0; f=files[i]; i++) {
    if(f.type.match('image.*')){
      totalBytesToUpload += f.size;
      totalFilesToUpload++;
      files[i].bytesUploaded = 0;
    }
  }
  if(totalFilesToUpload == 0) {
    alert("None of those files were images.");
    return false;
  }

  $("#droptarget").hide();
  $("#totalFiles").html(totalFilesToUpload);
  $("#doneFiles").html(filesDoneUploading);
  $("#progressBytes").attr("max",totalBytesToUpload);
  $("#progressBytes").val(totalBytesUploaded);
  $("#progressDiv").show();

  // Fire off up to the maximum number of PUT requests;
  for (i=0; i<MAX_IN_FLIGHT; i++) {
    uploadAFile();
  }
}
// only emit the drop target if the browser can handle drops!
if (window.File && window.FileReader && window.FileList && window.Blob) {
  document.write('<div id="droptarget">Drop them here!</div>');
  function dragOver(e){
    oe = e.originalEvent;
    oe.preventDefault();
    oe.dataTransfer.dropEffect = 'copy';
  }
  function dragEnter(e){
    $(this).addClass('dragOver');
  }
  function dragLeave(e){
    $(this).removeClass('dragOver');
  }
  function drop(e){
    $(this).removeClass('dragOver');
    oe = e.originalEvent;
    oe.stopPropagation();
    if(oe.dataTransfer.files.length){
      files = oe.dataTransfer.files;
      uploadAndCreateAlbum();
    }
  }
  $("#droptarget").on('dragover', dragOver);
  $("#droptarget").on('dragenter', dragEnter);
  $("#droptarget").on('dragleave dragend', dragLeave);
  $("#droptarget").on('drop', drop);
} else {
  document.write('<div id="nodroptarget">Upload them here: <input type="file" id="files" name="files[]" multiple /></div>');
  $("#files").change(function(evt){
		       files = evt.target.files;
		       $("#files").hide();
		       uploadAndCreateAlbum();
		     });
}
</script>
<div id="explain">
We'll upload them to our server and give you a beautiful online album. <strong>Easy&nbsp;peasy</strong>!
</div>
</form>
</body>
</html>
