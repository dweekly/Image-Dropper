<?

// we only preserve EXIF data in the masters (we strip all previews/thumbnails)
$f = "../albums/upe7tr/masters/IMG_0446.JPG";
$f = "upnorth.jpg";

$exif = exif_read_data($f, 0, true);
echo "$f:\n";
print_r($exif);
