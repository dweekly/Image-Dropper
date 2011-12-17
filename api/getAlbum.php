<?
require_once('lib.inc');

$album = $_GET['album'];

if(!$album){ nok("No Album"); }
if(!preg_match("/^[a-z0-9]+$/",$album)){ nok("Invalid Album Chars"); }
if(!file_exists("/var/www/albums/$album")){ nok("Album Does Not Exist"); }
if(!file_exists("/var/www/albums/$album/masters")){ nok("Album Has No Masters Dir"); }

$pics = getPicListForAlbum($album);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');
echo json_encode(array("album" => array("id" => $album, "pics" => $pics)));
