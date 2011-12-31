<?

// Common library of functions & settings

$ALBUM_ROOT_DIR = "/var/www/album/";

// If the request was NOT OK, die.
function nok($msg,$code=400){
  if(400 === $code){
    header("HTTP/1.1 400 Bad Request ($msg)");
  } else {
    header("HTTP/1.1 $code $msg");
  }
  die("NOK - $msg");
}

// get the list of pictures for an album as well as what thumbs are currently available.
function getPicListForAlbum($album){
  global $ALBUM_ROOT_DIR;
  $pics = array();
  if(!($d = opendir($ALBUM_ROOT_DIR.$album."/masters"))){
    nok("Cannot open album: ".$ALBUM_ROOT_DIR.$album."/masters");
  }
  while (false !== ($entry = readdir($d))) {
    if ($entry != "." && $entry != ".." && !preg_match("/\.(partial|.err)$/",$entry)) {
      $pic = array();
      $pic['exif'] = (file_exists($ALBUM_ROOT_DIR."$album/thumb_exif/$entry"))? true : false;
      $pic['th100'] = (file_exists($ALBUM_ROOT_DIR."$album/thumb_100/$entry"))? true : false;
      $pic['th256'] = (file_exists($ALBUM_ROOT_DIR."$album/thumb_256/$entry"))? true : false;
      $pic['th1024'] = (file_exists($ALBUM_ROOT_DIR."$album/thumb_1024/$entry"))? true : false;
      $pic['th1920'] = (file_exists($ALBUM_ROOT_DIR."$album/thumb_1920/$entry"))? true : false;
      $pics[$entry] = $pic;
    }
  }
  closedir($d);
  return $pics;
}
