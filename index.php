<!doctype html>
<html>
<head>
  <title>Photos to Share? Drop them here!</title>
  <script src="/jquery-1.7.1.min.js"></script>
  <script src="/site.js"></script>
  <link href="/site.css" rel='stylesheet' type='text/css' />
</head>

<body>

<!-- Show the front page view by default until the user has started uploading -->
<div id="frontpage-view">
  <h1>Photos to share?</h1>
  <div id="droptarget">Drop them here!</div>
  <div id="nodroptarget">Upload them here: <input type="file" id="files" name="files[]" multiple /></div>
  <div id="progressDiv">
    Uploading <span id="doneFiles">0</span> of <span id="totalFiles">0</span> images...
    <br />
    <progress id="progressBytes" value="0" max="100"></progress>
  </div>
  <div id="explain">
    We'll upload them to our server and give you a beautiful online album. <strong>Easy&nbsp;peasy</strong>!
  </div>
</div>

<!-- The album view -->
<div id="album-view">
  <div id="leftnav">
    <div id="picscroll">
      <ul id="piclist"></ul>
    </div>
  </div>
  <div id="maindisplay"></div>
</div>

<script>
<?

// enable the HTML5 URLs to actually load the proper album / picture / etc
// Technically, we could just do this in JS and CDN this static HTML,
// but it seems...cruel to ask the browser to go fetch this back from us?
if($_GET['album']){
  require_once("api/lib.inc");
  $album = $_GET['album'];
  if(!preg_match("/^[a-z0-9]+$/",$album)){ nok("Invalid Album Chars"); }
  if(!file_exists("album/$album")){ nok("Album Does Not Exist"); }
  print("album = \"".$album."\";\n");
  if($_GET['curImage']){
    $curImage = $_GET['curImage'];
    if(!preg_match("/^[a-zA-Z_ 0-9\.\-]+$/",$curImage)){ nok("Invalid curImage Chars"); }
    if(!file_exists("album/$album/$curImage")){ nok("Image Does Not Exist"); }
    print("curImage = \"".$curImage."\";\n");
  }
  // TODO: preload albumObj instead of making client ask for it?
  print("albumViewInit();\n");
}

?>
</script>
</body>
</html>
