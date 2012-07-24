<?php
include 'PunyPNG.php';

$pp = new PunyPNG();
$pp->apiKey = 'replace-with-your-api-key';
$pp->savePath = 'compressed/';

$images = array(
    'files/img1.png',
    'files/img2.jpg',
    'files/img3.gif'
);

$pp->compress($images);

echo $pp->printInfo();
echo $pp->printErrors();
?>