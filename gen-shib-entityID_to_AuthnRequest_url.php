<?

require_once 'common.inc.php';

function curl_get_contents($url) {
  $ch=curl_init($url);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  return curl_exec($ch);
}

function get_contents_and_parse($url, $namespace) {
  $xml = new SimpleXMLElement(curl_get_contents($url));
  if ($namespace) { 
    $ns = $xml->getNameSpaces();
    if (isset($ns[$namespace])) {
      $xml->registerXPathNamespace($namespace, $ns[$namespace]);
      $xml = $xml->children($ns[$namespace]);
    }
  }
  return $xml;
}

function get_entityID_and_AuthnRequest_url($e, &$r) {
  $entityID = (string) $e->attributes()->entityID;

  $idp = $e->IDPSSODescriptor;
  if (!$idp) return;

  $AuthnRequest_url = null;
  foreach ($idp->SingleSignOnService as $service) {
    if ($service->attributes()->Binding == "urn:mace:shibboleth:1.0:profiles:AuthnRequest") {
      $AuthnRequest_url = (string) $service->attributes()->Location;
      $r[$entityID] = $AuthnRequest_url;
    }
  }
}

function get_entityID_to_AuthnRequest_url($url_all, $url_cru) {
  $r = array();

  $xml_all = get_contents_and_parse($url_all, 'md');

  foreach ($xml_all->EntityDescriptor as $e) {
    get_entityID_and_AuthnRequest_url($e, $r);
  }
  get_entityID_and_AuthnRequest_url(get_contents_and_parse($url_cru, null), $r); 
  return $r;
}

function gen_raw($l) {
  $r = '';
  $r .= "<?php\n\n// Generated\n\n";

  foreach ($l as $name => $v) {
    $r .= 'global $' . $name . ";\n";
    $r .= '$' . $name . ' = ';
    $r .= var_export($v, true);
    $r .= ";\n\n";
  }
  $r .= "?>\n";
  return $r;
}

$entityID_to_AuthnRequest_url = get_entityID_to_AuthnRequest_url($federation_metadata_url, $federation_metadata_cru_url);


$content = gen_raw(array('entityID_to_AuthnRequest_url' => $entityID_to_AuthnRequest_url));

$conf = dirname(__FILE__) . '/federation-renater/entityID_to_AuthnRequest_url.inc.php';
atomic_file_put_contents($conf, $content);

echo "success\n";

?>
