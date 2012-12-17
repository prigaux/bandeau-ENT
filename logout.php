<?

// remove cookie to drop phpCAS cache
setcookie("PHPSESSID", "", 1, "/");

if (isset($_GET["callback"])) {
  header('Content-type: application/json; charset=UTF-8');
  echo $_GET["callback"] . "();";
} else {
  header('Location: '. urldecode($_GET["service"]));
}

?>