<?

chdir("/var/www/albums");

// This script's job is to provide thumbnail conversions for albums.
// This script should only be run once (continuously) per server.
// A cron job should periodically tickle (attempt to rerun) this script, in case it has crashed.
// Overall, we'll look for albums without a status.json file OR that have had activity since last we checked.
// We'll loop through each of these albums looking for missing thumbnails.
set_time_limit(0);

if(file_exists("convert.pid")) {
  $existing_pid = file_get_contents("convert.pid");
  if(file_exists("/proc/$existing_pid")) {
    die("Conversion script is already running with pid $existing_pid. Bailing.\n");
  }
  print("Prior conversion script (pid $existing_pid) died. Proceeding!\n");
}
file_put_contents("convert.pid",getmypid());
print("Kicked off conversion process (".getmypid()."\n");

while(1){
  

}

// rotate master (losslessly!) to correct orientation, if needed.
exec("/usr/bin/exifautotran ../albums/$album/masters/$fname");

// Fire off the ImageMagick thumbnail creation process to run in the background.
// NEW: use Memory Program Registers to only read the file in ONCE. (and strips, orients once)
// Create four levels of "thumbnail", interlacing all but the smallest (since <10KB JPGs aren't helped by being interlaced anyhow).
exec("/usr/bin/nice /usr/bin/convert ../albums/$album/masters/$fname -strip -write mpr:orig +delete ".
     "mpr:orig -quality 80 -resize 100x100                    -write '../albums/$album/thumb_100/$fname'  +delete ".
     "mpr:orig -quality 90 -resize 256x256   -interlace Plane -write '../albums/$album/thumb_256/$fname'  +delete ".
     "mpr:orig -quality 95 -resize 1024x1024 -interlace Plane -write '../albums/$album/thumb_1024/$fname' +delete ".
     "mpr:orig -quality 95 -resize 1920x1280 -interlace Plane        '../albums/$album/thumb_1920/$fname' ".
     " > /dev/null 2>&1 &");
