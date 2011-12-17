<?
require_once('lib.inc');

$headers = apache_request_headers();
$fname = $headers['X-File-Name'];
$fsize = $headers['X-Size'];
$album = $headers['X-Album'];

if(!$album){ nok("No Album"); }
if(!$fname){ nok("No Filename"); }
if(!$fsize){ nok("No Filesize"); }

if(!preg_match("/^[a-z0-9]+$/",$album)){ nok("Invalid Album Chars"); }
if(!preg_match("/^[a-zA-Z_ 0-9\.\-]+$/",$fname)){ nok("Invalid File Chars"); }
if(!file_exists("../albums/$album")){ nok("Album Does Not Exist"); }
// TODO - ensure only authorized people can upload to this album!

// TODO - check for incoming filenames, create a mapping between filename & image on disk? (use filename as "caption"?)

// read in the input file name 32KB at a time and write to temp file.
$file_in = fopen("php://input",'r');
$file_out = fopen("../albums/$album/masters/$fname.partial",'w');
$recsize = 0;
while (!feof($file_in)) $recsize += fwrite($file_out,fread($file_in,32*1024));
fclose($file_in);
fclose($file_out);

// if the file size is as expected, rename the master.
if($recsize == $fsize) {
  rename("../albums/$album/masters/$fname.partial","../albums/$album/masters/$fname");
  print("OK");
} else {
  rename("../albums/$album/masters/$fname.partial","../albums/$album/masters/$fname.err");
  print("NOK - partial upload ($recsize of $fsize bytes received)");
}
// Okay, let the user know we have the file - and don't let an interrupt or timeout keep us from making thumbs!
flush();
ignore_user_abort(true);

// Let's pronto squirt out the EXIF thumbnail into the thumb_100 folder (we'll overwrite it later with a proper conversion)
exec("/usr/bin/jhead -st ../albums/$album/thumb_100/$fname ../albums/$album/masters/$fname");

// TODO: asynchronously enqueue the conversion job here. Right now, we'll just do it by hand.
