<?

// common library of functions

// If the request was NOT OK, die.
function nok($msg){
  header("HTTP/1.1 400 Bad Request ($msg)");
  die("NOK - $msg");
}

function getPicListForAlbum($album){
  $pics = array();
  if(!($d = opendir("/var/www/albums/$album/masters"))){
    nok("Cannot open album");
  }
  while (false !== ($entry = readdir($d))) {
    if ($entry != "." && $entry != "..") {
      $pics[] = $entry;
    }
  }
  closedir($d);
  return $pics;
}
