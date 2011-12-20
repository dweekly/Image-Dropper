<?

chdir("/var/www/albums");

// This script's job is to provide thumbnail conversions for albums.
// This script should only be run once (continuously) per server.
// A cron job should periodically tickle (attempt to rerun) this script, in case it has crashed.
// Overall, we'll look for albums without a status.json file OR that have had activity since last we checked.
// We'll loop through each of these albums looking for missing thumbnails.

set_time_limit(0);
ignore_user_abort(true);

if(file_exists("convert.pid")) {
  $existing_pid = file_get_contents("convert.pid");
  if(file_exists("/proc/$existing_pid")) {
    die("Conversion script is already running with pid $existing_pid. Bailing.\n");
  }
  print("Prior conversion script (pid $existing_pid) died. Proceeding!\n");
}
file_put_contents("convert.pid",getmypid());
print("Kicked off conversion process (".getmypid().")\n");

// Run this forever, basically.
$startedTime = gettimeofday(true);
while(1){
  // initial, hacky algorithm:
  // look at the work-queue folder for files named ALBUM.FILE & (re)create thumbnails as needed.
  if(!($d = opendir("/var/www/work-queue"))){
    die("Couldn't open work queue!");
  }
  while (false !== ($entry = readdir($d))) {
    if ($entry != "." && $entry != "..") {
      preg_match("/^([^\.]+)\.(.*)$/",$entry,$work);
      $album = $work[1];
      $file = $work[2];
      if(file_exists("/var/www/albums/$album/masters/$file")){
	unlink("/var/www/work-queue/".$entry) || die("failure to remove work queue item $entry"); // okay, we're doing the work, kill the queue entry.
	genThumb($album, $file);
      } else {
	die("asked to thumb non-existent $file from album $album");
      }
    }
  }
  closedir($d);
  sleep(1); // don't busywait.
}


// given an album and file, create the appropriate set of thumbnails.
function genThumb($album,$fname) {
  print("Generating thumbnail for $album :: $fname...\r\n");

  // rotate master (losslessly!) to correct orientation, if needed.
  system("/usr/bin/exifautotran ../albums/$album/masters/$fname");

  // Run ImageMagick serially (yes, this will block until it's done, that's what we want!)
  // NEW: use Memory Program Registers to only read the file in ONCE. (and strips, orients once)
  // Create four levels of "thumbnail", interlacing all but the smallest (since <10KB JPGs aren't helped by being interlaced anyhow).
  system("/usr/bin/nice /usr/bin/convert ../albums/$album/masters/$fname -strip -write mpr:orig +delete ".
       "mpr:orig -quality 80 -resize 100x100                    -write '../albums/$album/thumb_100/$fname'  +delete ".
       "mpr:orig -quality 90 -resize 256x256   -interlace Plane -write '../albums/$album/thumb_256/$fname'  +delete ".
       "mpr:orig -quality 95 -resize 1024x1024 -interlace Plane -write '../albums/$album/thumb_1024/$fname' +delete ".
       "mpr:orig -quality 95 -resize 1920x1280 -interlace Plane        '../albums/$album/thumb_1920/$fname' ");

}