<?php // -*-PHP-*-

include_once "config.inc.php";
include_once "gen-config-menu-from-uportal.inc.php";
require_once "common.inc.php";

if ($_SERVER['REMOTE_ADDR'] !== $ip_allowed_to_run_gen_config_menu_from_uportal)
  exit("your IP (" . $_SERVER['REMOTE_ADDR'] . ") is not allowed");

function rm_rf($dir) {
  system("rm -rf " . $dir);
}

function wait_previous_call_terminate($tmp_dir) {
  $timeout = 0;
  while (is_dir($tmp_dir)) {
    echo "waiting for previous call to end\n";
    if ($timeout-- == 0) {
      // remove dead(?) dir
      rm_rf($tmp_dir);
      break;
    }
    sleep(1);
  }
  return mkdir($tmp_dir);
}

$tmp_dir = sys_get_temp_dir() . "/tmp-uportal-conf";
//echo "working in $tmp_dir\n";
if (!wait_previous_call_terminate($tmp_dir)) {
  exit(ini_get('safe_mode') ? 'only way to make it work is to disable safe_mode!' :
       (is_dir($tmp_dir) ? "race detected, failing" : "failed to create $tmp_dir"));
}

$tmp_tar_file = "$tmp_dir/t.tar";
file_put_contents($tmp_tar_file, file_get_contents('php://input'));
system("tar xC $tmp_dir -f $tmp_tar_file");

$PAGSGroupStoreConfig = "$tmp_dir/esup-package/custom/uPortal/uportal-impl/src/main/resources/properties/groups/PAGSGroupStoreConfig.xml";
$db_export_dir = "$tmp_dir/db-export";
if (!is_file($PAGSGroupStoreConfig)) {
  $err = "missing PAGSGroupStoreConfig $PAGSGroupStoreConfig";
} else if (!is_dir($db_export_dir)) {
  $err = "missing db-export $db_export_dir";
} else {
  $config_menu_content = gen($PAGSGroupStoreConfig, $db_export_dir);
}
rm_rf($tmp_dir);
if (@$err) exit($err);

$config_menu_file = dirname(__FILE__) . '/config-menu-from-uportal/config-menu.inc.php';
atomic_file_put_contents($config_menu_file, $config_menu_content);

echo "success\n";

?>
