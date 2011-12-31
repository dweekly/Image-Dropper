<?
require_once('lib.inc');

// create a new, random album ID.
do {
  $album = "";
  $alnum = array_merge(range('0','9'),range('a','z'));
  for ($i=0; $i<6; $i++) { 
    $album .= $alnum[rand(0,35)];
  }
} while(file_exists($ALBUM_ROOT_DIR.$album));

mkdir($ALBUM_ROOT_DIR.$album) || nok("Could not create $album",500);
mkdir($ALBUM_ROOT_DIR.$album."/masters");
mkdir($ALBUM_ROOT_DIR.$album."/thumb_100");
mkdir($ALBUM_ROOT_DIR.$album."/thumb_256");
mkdir($ALBUM_ROOT_DIR.$album."/thumb_1024");
mkdir($ALBUM_ROOT_DIR.$album."/thumb_1920");

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode(array("album" => $album));
