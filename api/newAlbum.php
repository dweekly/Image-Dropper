<?
require_once('lib.inc');

// create a new, random album ID.
do {
  $album = "";
  $alnum = array_merge(range('0','9'),range('a','z'));
  for ($i=0; $i<6; $i++) { 
    $album .= $alnum[rand(0,35)];
  }
} while(file_exists("../albums/$album"));

mkdir("../albums/$album") || nok("Could not create $album");
mkdir("../albums/$album/masters");
mkdir("../albums/$album/thumb_100");
mkdir("../albums/$album/thumb_256");
mkdir("../albums/$album/thumb_1024");
mkdir("../albums/$album/thumb_1920");

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');
echo json_encode(array("album" => $album));
