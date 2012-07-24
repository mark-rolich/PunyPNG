<?php
include 'PunyPNG.php';

$pp = new PunyPNG();
$pp->apiKey = 'replace-with-your-api-key';
$pp->batchMode = true;

$images = array(
    // this will fail, filesize exceeds 500kb limit
    'http://upload.wikimedia.org/wikipedia/commons/a/a1/Lanzarote_3_Luc_Viatour.jpg',

    'files/img1.png',
    'files/img2.jpg',
    'files/img3.gif'
);

$pp->compress($images);

echo $pp->printInfo();
echo $pp->printErrors();
?>