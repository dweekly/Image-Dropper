<?
error_reporting(E_ALL);
require_once("api/lib.inc");

// need to have an album to look at, sillypants.
if(!$_GET['album']){ header("Location: /"); }
$album = $_GET['album'];

// TODO: security controls around looking at albums.


?><!doctype html>
<html>
<head>
<title>Photo Album</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<style>
body { height: 100%; margin:0; padding:0; overflow:hidden;
background:
  radial-gradient(black 15%, transparent 16%) 0 0,
  radial-gradient(black 15%, transparent 16%) 8px 8px,
  radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 0 1px,
  radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 8px 9px;
background-color:#282828;
background-size:16px 16px;
background:
  -webkit-radial-gradient(black 15%, transparent 16%) 0 0,
  -webkit-radial-gradient(black 15%, transparent 16%) 8px 8px,
  -webkit-radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 0 1px,
  -webkit-radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 8px 9px;
background-color:#282828;
background-size:16px 16px;;
}
h1 h2 h3 { text-rendering: optimizeLegibility; }
h1 { font-size:2em; color:white;  }
ul { list-style-type: none; margin:0; padding:0; }
ul li { text-align: center; padding:5px; border-bottom:1px solid #666; background-color:rgba(0,0,0,0.3); }
ul li:nth-child(even) { background-color:rgba(0,0,0,0.8); }
.thumb:hover { background-color:rgba(255,255,200,1.0); }
.thumb { background:url(spinner.gif) center center no-repeat; }
#home { height: 85px; }
#leftnavbg { position:aboslute; top:0; left:0; width:138px; height:3000px; background-color:#666;
 opacity: 0.7; box-shadow:rgba(0,0,0,0.7) 10px 0px; display:none; }
#leftnav { display:block; position:aboslute; top:0; left:0; width:138px; height:100%; }
#leftnav h1 { text-align:center; }
#picscroll { height:500px; cursor: grab; cursor: -moz-grab; cursor: -webkit-grab; overflow:auto; padding-bottom:100px; }
#picscroll img { display:block; background-size:contain; width:128px; height:128px; }
#maindisplay { display:block; position:absolute; top:0; left:138px;
 background-repeat: no-repeat; background-size:contain;
 width:80%; height:100%; outline:0; }

/* iPhone4 images should be twice as dense */
@media only screen and (-webkit-min-device-pixel-ratio: 2),
       only screen and (min-device-pixel-ratio: 2) {

}
</style>
</head>
<body>
<div id="bodytex">
 <div id="leftnavbg">
 </div>
 <div id="leftnav">
  <div id="home">
   <h1>New Album</h1>
  </div>
  <div id="picscroll">
<ul id="piclist">
</ul>
  </div>
 </div>
 <div id="maindisplay">
 </div>
</div>
<script>
<?
// instead of fetching album list over AJAX, let's just generate it here and save the poor client a round trip.
/* $.get("/api/getAlbum.php?album=<?=$_GET['album']?>", function(data) { albumObj = data.album; loadAlbum(); }, 'json'); */

echo "var albumObj = " . json_encode(array("id" => $album, "pics" => getPicListForAlbum($album))) . ";\n";
echo "loadAlbum();\n";
?>

// if we fail to load one of the thumbs, show a spinner and try again in a second.
function retryThumb(img) {
  var curAttempt = img.data('attempt');
  console.log('retrying url '+img.attr('src'));
  var curTime = (new Date).getTime();
  img.attr('src',img.data('origSrc')+'?q='+curAttempt+'&t='+curTime);
}

$('img').error(function() {
  var curAttempt = $(this).data('attempt');
  if(undefined === curAttempt){ curAttempt = 1; }
  curAttempt++;
  $(this).data('attempt', curAttempt);
  if(undefined === $(this).data('origSrc')){ // save the original source url
    $(this).data('origSrc',$(this).attr('src'));
  }
  if(curAttempt > 15) {
    console.log("Too many tries to fetch "+this.src+", giving up.\n");
  } else {
    var retryTimeout = 500; // how many ms to wait before retrying.
    if(curAttempt >= 4){ retryTimeout = 1000; }
    if(curAttempt >= 7){ retryTimeout = 2000; }
    if(curAttempt >= 11){ retryTimeout = 4000; }
    if(curAttempt >= 14){ retryTimeout = 10000; }      
    console.log('Attempt '+curAttempt+' to load '+this.src+'...');
    setTimeout(retryThumb,retryTimeout,$(this));
  }
});

// resize scroll and handle vertical drags
var amScrolling = 0;
$("#picscroll").
 height($(window).height()-$("#home").height()-10).
 mousedown(function(evt){
	     amScrolling = 1; // may be scrolling? could just be clicking.
	     $(this).data('y', evt.clientY).data('scrollTop', this.scrollTop);
	     return false; }).
 mouseup(function(evt){
	   amScrolling = 0; // definitely not scrolling anymore.
	 }).
 mouseleave(function(evt){
	   amScrolling = 0; // definitely not scrolling anymore.
	 }).
 mousemove(function(evt){
	     if(amScrolling == 1){ // maybe scrolling...
	       // see if I have dragged far enough to definitely be scrolling
	       if(Math.abs($(this).data('y') - evt.clientY) > 20){
		 amScrolling = 2;
	       }
	     }
	     if(amScrolling == 2) {
	       this.scrollTop = $(this).data('scrollTop') + $(this).data('y') - evt.clientY;
	     }});

// when someone clicks on a picture, make it the main one.
function thumbClickCheck(evt) {
  if(amScrolling != 2){
    curImage = $(this).attr('pic');
    showMainImage();
  }
}
$(".thumb").mouseup(thumbClickCheck);

function loadAlbum(){
  // WAY faster to build up textual list, then append. See http://www.learningjquery.com/2009/03/43439-reasons-to-use-append-correctly
  var thumbLIs = '';
  // Use 100px thumbs at first (ripped from EXIF data if available, so probably visible first)
  // TODO: use bigger thumbs if on a much bigger display?
  $.each(albumObj.pics, function(k,v){ thumbLIs += "<li class=\"thumb\" pic=\""+v+"\"><img src=\"albums/"+albumObj.id+"/thumb_100/"+v+"\" /></li>"; });
  $("#piclist").append(thumbLIs);
  curImage = albumObj.pics[0]; // show first image in album by default.
  //  $("#thumb_"+curImage).css("width","200");
  showMainImage();
  $(window).resize(onResize);
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
  var tdir = '/masters/';
  if (mainwidth < 256 ) { tdir = "/thumb_256/"; }
  else if (mainwidth < 1024 ) { tdir = "/thumb_1024/"; }
  else if (mainwidth < 1920 ) { tdir = "/thumb_1920/"; }
  $("#maindisplay").css('background-image','url(albums/'+albumObj.id+tdir+curImage+')');
};
</script>
</body>
</html>
