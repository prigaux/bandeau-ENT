<? 

include_once "config.inc.php";
include_once "common.inc.php";

if (isset($_GET["id"])) {
  $id = $_GET["id"];
  $app = $APPS[$id];
  if (!$app) exit("invalid app id " . $id);
  $url = get_url($app, $id, isset($_GET["guest"]));
  if ($url) 
    header("Location: $url");
  else
    exit("invalid app id $id");
} else {
  exit("missing 'id=xxx' parameter");
}

?>