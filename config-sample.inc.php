<?

$ldap_server = 'ldap.univ.fr ldap2.univ.fr';
$ldap_bind_dn = 'cn=bandeau-ent,ou=admin,dc=univ,dc=fr';
$ldap_bind_password = 'xxx';

$ou_people="ou=people,dc=univ,dc=fr";
$ou_externalPeople="ou=externalPeople,dc=univ,dc=fr";

$minimal_attrs = array('displayName', 'uid', 'mail', 'supannAliasLogin');

$admins = array('xxx');

include_once "config-menu.inc.php";

$CA_certificate_file = '/usr/local/etc/ssl/certs/ca.crt';
$cas_host = 'cas.univ.fr';
$cas_context = '/cas';
$ent_base_url = "https://esup.univ.fr";
$ent_base_url_guest = "https://ent.univ.fr";
$bandeau_ENT_url = "https://bandeau-ENT.univ.fr";
$eppnDomainRegexForUid = '@.*univ.fr';
$currentIdpId = 'https://idp.univ.fr';
$shib_proxy_keys = array();
$ip_allowed_to_run_gen_config_menu_from_uportal = '';

$federation_metadata_url = 'https://services-federation.renater.fr/metadata/renater-metadata.xml';
$federation_metadata_cru_url = 'https://federation.renater.fr/idp/profile/Metadata/SAML';


foreach (array_values($LAYOUT_ALL) as $i => $l) {
  foreach ($l as $appId)
    $APPS[$appId]["uportalActiveTab"] = $i+1;
}
foreach (array_values($LAYOUT_GUEST) as $i => $l) {
  foreach ($l as $appId)
    $APPS[$appId]["uportalActiveTabGuest"] = $i+1;
}

$appIds_bandeau_compatible = array('caccueil-pers', 'caccueil-etu', 'caccueil-guest');

foreach ($appIds_bandeau_compatible as $appId) {
  $APPS[$appId]["url_bandeau_compatible"] = true;
}

$appIds_bandeau_direct = $appIds_bandeau_compatible;

foreach ($appIds_bandeau_direct as $appId) {
  $APPS[$appId]["url_bandeau_direct"] = true;
}

$test = true;
if ($test) {
  $disableLocalStorage = true;
}


$idp2url = array("urn:mace:cru.fr:federation:univ.fr" => "$bandeau_ENT_url/bandeau-ENT-js.php");


?>
