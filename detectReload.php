<?php

session_cache_limiter('');
$now = time();
$five_days = 432000;
header("Expires: " . gmdate("D, d M Y H:i:s", $now + $five_days) . " GMT");
header('Content-type: application/javascript; charset=utf8');
echo "window.bandeau_ENT_detectReload(" . $now . ");";

?>
