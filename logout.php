<?

// remove cookie to drop phpCAS cache
setcookie("PHPSESSID", "", 1, "/");

header('Location: '. urldecode($_GET["service"]));


?>