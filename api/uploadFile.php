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
if(!file_exists($ALBUM_ROOT_DIR.$album)){ nok("Album Does Not Exist"); }
$escFname = str_replace(" ","\\ ",$fname);

// TODO - ensure only authorized people can upload to this album!

// TODO - check for incoming filenames, create a mapping between filename & image on disk? (use filename as "caption"?)

// TODO - check to see if uploaded file is JPEG; only do JPEG manipulations on JPEG files!

// read in the input file name 32KB at a time and write to temp file.
$file_in = fopen("php://input",'r');
$file_out = fopen($ALBUM_ROOT_DIR.$album."/masters/$fname.partial",'w');
$recsize = 0;
$recsize += fwrite($file_out,fread($file_in,32*1024));

// cool hack: with at least 32KB read it, that should be enough to pull out the rotation & thumbnail.
fflush($file_out);
$rot = system("/usr/bin/jpegexiforient -n ".$ALBUM_ROOT_DIR.$album."/masters/$fname.partial");

// generate a thumbnail from the partial upload...
exec("/usr/bin/jhead -st ".$ALBUM_ROOT_DIR.$album."/thumb_exif/$fname ".$ALBUM_ROOT_DIR.$album."/masters/$fname.partial");

// and rotate it appropriately.
if($rot == 3) { exec("/usr/bin/jpegtran -copy none -rotate 180 ".$ALBUM_ROOT_DIR.$album."/thumb_exif/$escFname"); }
if($rot == 6) { exec("/usr/bin/jpegtran -copy none -rotate 90  ".$ALBUM_ROOT_DIR.$album."/thumb_exif/$escFname"); }
if($rot == 8) { exec("/usr/bin/jpegtran -copy none -rotate 270 ".$ALBUM_ROOT_DIR.$album."/thumb_exif/$escFname"); }

// now finish reading in the rest of the file being uploaded...!
while (!feof($file_in)) $recsize += fwrite($file_out,fread($file_in,32*1024));
ignore_user_abort(true);
fclose($file_in);
fclose($file_out);

// if the file size isn't as expected, rename the master to an error file.
if($recsize != $fsize) {
  rename($ALBUM_ROOT_DIR.$album."/masters/$fname.partial",
	 $ALBUM_ROOT_DIR.$album."/masters/$fname.err");
  nok("Partial upload ($recsize of $fsize bytes received)");
}

// TODO: check the integrity of the image - does it look like a picture?

// Okay, let the user know we have the file - and don't let an interrupt or timeout keep us from making thumbs!
rename($ALBUM_ROOT_DIR.$album."/masters/$fname.partial",
       $ALBUM_ROOT_DIR.$album."/masters/$fname");
print("OK");
flush();

// HACK: touch a file in the work-queue folder to tell our thumbnail generation task that that picture needs to be re-thumbed.
touch($ALBUM_ROOT_DIR."../work-queue/$album.$fname") || die("can't create work unit");
