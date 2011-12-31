// This JS file contains all of the site's frontend logic.
// TODO: minify/compress this.

var filesUploading = [];
var album; // current album.

// We're done uploading a file (though it may have ended in success or error...!)
function doneFile() {
  var filesDoneUploading = $("#doneFiles").html();
  var totalFilesToUpload = $("#totalFiles").html();
  filesDoneUploading++;
  $("#doneFiles").html(filesDoneUploading);
  if(filesDoneUploading == totalFilesToUpload){
    doneAllFiles();
  } else {
    uploadAFile();
  }
}

// okay, we're finished uploading everything,
// switch to the album view.
function doneAllFiles() {
  filesUploading = [];
  // don't need to do the below, since we've already initialized the album view.
  //$("#frontpage-view").hide();
  //  albumViewInit();
}

function updateProgress(evt,whatFile,xhr) {
  // console.log('Progress on file '+whatFile+': loaded '+evt.loaded+' of '+evt.total);
  var delta = (evt.loaded - filesUploading[whatFile].bytesUploaded);
  var totalBytesUploaded = $("#progressBytes").val() + delta;
  $("#progressBytes").val(totalBytesUploaded);
  filesUploading[whatFile].bytesUploaded = evt.loaded;
}

// A file finished uploading successfully.
function transferComplete(evt,whatFile,xhr) {
  if(xhr.status != 200){
    alert("Error transferring file "+whatFile+": "+xhr.response);
    filesUploading[whatFile].status = 'error';
  } else {
    filesUploading[whatFile].status = 'done';
  }
  console.log('Finished upload of file '+whatFile);
  doneFile();
}

// A file'supload was cancelled by the user (e.g. by navigating away)
function transferCancelled(evt,whatFile,xhr) {
  console.log('Cancelled upload of file '+whatFile);
  alert('Cancelled file '+whatFile);
  filesUploading[whatFile].status = 'error';
  doneFile();
}

// A transfer failed (due to a network issue or server error)
// TODO: graceful resumes possible?
function transferFailed(evt,whatFile,xhr) {
  console.log('Aborted upload of file '+whatFile);
  alert('Aborted file '+whatFile);
  filesUploading[whatFile].status = 'error';
  doneFile();
}

// Find ONE file from the queue that has not yet begun (or finished) uploading and starts the upload process.
function uploadAFile(){
  for (var i=0, f; f = filesUploading[i]; i++) {
    if(!filesUploading[i].status && filesUploading[i].type.match('image.*')){
      filesUploading[i].status = 'uploading';

      var xhr = new XMLHttpRequest();  
      xhr.open("PUT", "/api/uploadFile.php");
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
  if(undefined === album){
    $.get('/api/newAlbum.php',
	  function(data) {
	    album = data.album;
	    console.log('got album '+album+' from the server.');
	    uploadAndCreateAlbum();
	  }, 'json')
      .error(function(x,status,err){ alert(status+": "+err); });
    return;
  }

  // Tally up the total number of files (and bytes) we're planning on sending.
  var totalBytesToUpload = 0;
  var totalFilesToUpload = 0;
  var i;
  var f;
  for (i=0; f=filesUploading[i]; i++) {
    if(f.type.match('image.*')){
      totalBytesToUpload += f.size;
      totalFilesToUpload++;
      filesUploading[i].bytesUploaded = 0;
    }
  }
  if(totalFilesToUpload == 0) {
    alert("None of those files were images.");
    return false;
  }

  $("#droptarget").hide();
  $("#totalFiles").html(totalFilesToUpload);
  $("#doneFiles").html(0);
  $("#progressBytes").attr("max",totalBytesToUpload);
  $("#progressBytes").val(0);
  $("#progressDiv").show();

  // Upload three files at a time. (as each one finishes, a new one will be kicked off.
  uploadAFile();
  uploadAFile();
  uploadAFile();
  
  // let's switch to the album view while the files upload. (TODO: show progress bars on each photo as they upload!)
  $("#frontpage-view").hide();
  history.pushState({'album':album,'action':'albumView'},'Viewing Album: '+album,'/album/'+album);
  albumViewInit();
}

// Called to initialize the "front page" view when we kick off the web app.
function frontpageViewInit() {
  // only show the drop target if the browser can handle drops!
  $("#album-view").hide();
  $("#frontpage-view").show();
  $("#progressDiv").hide();
  if (window.File && window.FileReader && window.FileList && window.Blob) {
    $("#nodroptarget").hide();
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
	filesUploading = oe.dataTransfer.files;
	uploadAndCreateAlbum();
      }
    }
    $("#droptarget").on('dragover', dragOver);
    $("#droptarget").on('dragenter', dragEnter);
    $("#droptarget").on('dragleave dragend', dragLeave);
    $("#droptarget").on('drop', drop);
  } else {
    $("#droptarget").hide();
    $("#files").change(function(evt){
			 filesUploading = evt.target.files;
			 $("#files").hide();
			 uploadAndCreateAlbum();
		       });
  }
}


///////////////// Album View functions ////////////////

var amScrolling = 0;
var curImage;

// if we fail to load one of the thumbs, show a spinner and try again in a second.
function retryImage(img) {
  var curAttempt = img.data('attempt');
  var curTime = (new Date).getTime();
  img.attr('src',img.data('origSrc')+'?q='+curAttempt+'&t='+curTime);
}

// when someone clicks on a picture, make it the main one.
function thumbClickCheck(evt) {
  console.log("Hm, check to see if we meant to click on a thumbnail.");
  if(amScrolling != 2){
    curImage = $(this).attr('pic');
    history.pushState({'album':album,'curImage':curImage,'action':'picView'},'Viewing Picture: '+album,'/album/'+album+'/#'+curImage);
    showMainImage();
  }
}

// If there's an error loading an image, handle it by retrying a number of times,
// WITH cache-busting, since we hope the server will eventually vend the file.
// TODO: show a spinner over the loading image?
function imageError(image){
  var curAttempt = image.data('attempt');
  if(undefined === curAttempt){ curAttempt = 1; }
  curAttempt++;
  image.data('attempt', curAttempt);
  if(undefined === image.data('origSrc')){ // save the original source url
    image.data('origSrc',image.attr('src'));
  }
  var sourceURI = image.data('origSrc');
  if(curAttempt > 15) {
    console.log("Too many tries to fetch "+sourceURI+", giving up.\n");
  } else {
    var retryTimeout = 500; // how many ms to wait before retrying.
    if(curAttempt >= 4){ retryTimeout = 1000; }
    if(curAttempt >= 7){ retryTimeout = 2000; }
    if(curAttempt >= 11){ retryTimeout = 4000; }
    if(curAttempt >= 14){ retryTimeout = 10000; }      
    console.log('Attempt '+curAttempt+' to load '+sourceURI+'...');
    setTimeout(retryImage,retryTimeout,image);
  }
}

function loadAlbum(){
  // WAY faster to build up textual list, then append. See http://www.learningjquery.com/2009/03/43439-reasons-to-use-append-correctly
  var thumbLIs = '';
  // Use 100px thumbs at first (ripped from EXIF data if available, so probably visible first)
  // TODO: use bigger thumbs if on a much bigger display?
  // TODO: cache the lists more aggressively?

  $.each(albumObj.pics,
	 function(img, picObj){
	   if(img){
	     var thdir = "masters";
	     if(picObj.th100){
	       thdir = "thumb_100";
	     } else {
	       if(picObj.exif){
		 thdir = "thumb_exif";
	       }
	     }
	     thumbLIs += "<li class=\"thumb\" pic=\""+img+"\"><img src=\"/album/"+albumObj.id+"/"+thdir+"/"+img+"\" /></li>";
	   }
	 });

  $("#piclist").html('');
  $("#piclist").append(thumbLIs);

  $("#piclist .thumb").mouseup(thumbClickCheck);
  $('#piclist img').error(function(){imageError($(this));});
  $('#piclist img').load(function(){imageLoaded($(this));});

  // resize scroll and handle vertical drags
  $("#picscroll").
    height($(window).height()-$("#home").height()-10).
    mousedown(function(evt){
		console.log("may be scrolling?");
		amScrolling = 1; // may be scrolling? could just be clicking.
		$(this).data('y', evt.clientY).data('scrollTop', this.scrollTop);
		return false; }).
    mouseup(function(evt){
	      console.log("not scrolling, mouse is up.");
	      amScrolling = 0; // definitely not scrolling anymore.
	    }).
    mouseleave(function(evt){
		 console.log("not scrolling, mouse left");
		 amScrolling = 0; // definitely not scrolling anymore.
	       }).
    mousemove(function(evt){
		if(amScrolling == 1){ // maybe scrolling...
		  // see if I have dragged far enough to definitely be scrolling
		  if(Math.abs($(this).data('y') - evt.clientY) > 20){
		    console.log("ok, mouse definitely scrolling now.");
		    amScrolling = 2;
		  }
		}
		if(amScrolling == 2) {
		  this.scrollTop = $(this).data('scrollTop') + $(this).data('y') - evt.clientY;
		}});
  
  if(undefined === curImage) {
    // HACK HACK FIXME - i just want the first image in the album!
    $.each(albumObj.pics, function (img,picObj) { if (undefined === curImage){ curImage = img; } });
  }
  showMainImage();
  $(window).resize(onResize);
}

function imageLoaded(image){
  // TODO: ensure image actually looks okay, cache?
  // console.log("Successfully loaded "+image.attr("src"));
}

function onResize(){
  $("#picscroll").height($(window).height()-$("#home").height()-10); // resize thumbnail scroller
  showMainImage(); // thumbnail dimensions may have changed!
}

function showMainImage(){
  var mainwidth = $(window).width() - 138;
  $("#maindisplay").width(mainwidth);
  var mainheight = $(window).height();
  $("#maindisplay").height(mainheight);

  // use the best available thumbnail other than the master, unless that's the only thing we've got.
  var curPic = albumObj.pics[curImage];
  var tdir = '';
  if(curPic.exif){ tdir = "/thumb_exif/"; } // okay, if there's an exif thumb, use that.

  if(curPic.th100 && mainwidth < 100){ tdir = "/thumb_100/"; } 
  else if(curPic.th256 && mainwidth < 256){ tdir = "/thumb_256/"; } 
  else if(curPic.th1024 && mainwidth < 1024){ tdir = "/thumb_1024/"; } 
  else if(curPic.th1920 && mainwidth < 1920){ tdir = "/thumb_1920/"; } 
  else tdir = '/masters/';

  $("#maindisplay").html("<img id=\"mainImage\" src=\"/album/"+albumObj.id+tdir+curImage+"\" />");
  $("#maindisplay img").error(function(){imageError($(this));});
  //  $("#maindisplay").css('background-image','url(/album/'+albumObj.id+tdir+curImage+')');
};

// initialize the album view!
function albumViewInit(){
  if(undefined === album){ alert("Album was expected!"); }

  // TODO: show a spinner or something while we load the album view?
  $("#album-view").show();
  $.get("/api/getAlbum.php?album="+album,
	function(data) {
	  albumObj = data.album;
	  loadAlbum();
	}, 'json');
}


window.addEventListener('popstate', function(event) {

			  // we're browsing here for the first time, with no state (just surfed in),
			  // so try to deconstruct their state from the URL.
			  if(null === event.state){
			    var path = window.location.pathname.slice(1).split('/');
			    if('album' == path[0]) {
			      album = path[1];
			      if('' !==  window.location.hash.slice(1)){
				curImage =  window.location.hash.slice(1);
			      }
			      albumViewInit();
			    } else {
			      album = undefined;
			      frontpageViewInit();
			    }
			  } else {
			    // otherwise, we've clicked 'back' to a prior state.
			    // pop the state and display the appropriate view.
			    if('albumView' == event.state.action){
			      album = event.state.album;
			      curImage = undefined;
			      albumViewInit();
			    }
			    if('picView' == event.state.action){
			      album = event.state.album;
			      curImage = event.state.curImage;
			      albumViewInit();
			    }
			    if('frontpageView' == event.state.action){
			      album = undefined;
			      curImage = undefined;
			      frontpageViewInit();
			    }
			  }
			});
