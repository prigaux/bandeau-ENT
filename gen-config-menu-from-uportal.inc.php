<?

function simplexml_get_string_array($iterator) {
  $r = array();
  foreach ($iterator as $v)
    $r[] = (string) $v;
  return $r;
}

function uportalGetGroupMembership($file) {
  $xml = simplexml_load_file($file); 

  $name = (string) $xml->name;

  $groups = simplexml_get_string_array($xml->children->group);
  $users = simplexml_get_string_array($xml->children->literal);
  $groupsAndUsers = array("groups" => $groups, "users" => $users);

  return array($name, $groupsAndUsers);
}

function uportalGetGroupsMembership($files) {
  $r = array();
  foreach ($files as $file) {
    list ($name, $groupsAndUsers) = uportalGetGroupMembership($file);
    $r[$name] = $groupsAndUsers;
  }
  return $r;
}

function pagsGetAndTest($andTest, $groupKey) {
  $r = array();
  foreach ($andTest->test as $test) {
    $attr = (string) $test->{'attribute-name'};

    if ($attr == '' && $test->{'tester-class'} == 'org.jasig.portal.groups.pags.testers.AlwaysTrueTester') 
      continue;

    if (!isset($r[$attr]))
      $r[$attr] = array();

    switch($test->{'tester-class'}) {
    case 'org.jasig.portal.groups.pags.testers.StringEqualsIgnoreCaseTester':
      $r[$attr][] = "^" . preg_quote($test->{'test-value'}) . "$";
      break;
    case 'org.jasig.portal.groups.pags.testers.RegexTester':
      $r[$attr][] = "" . $test->{'test-value'};
      break;
    default:
      exit("too complex <test>s for $groupKey: <tester-class> " . $test->{'tester-class'} . " not handled");
    }
  }
  return $r;
}

function array_unique_($a) {
  sort($a);
  $r = array();
  $prev = null;
  foreach ($a as $e)
    if ($prev === null || $prev !== $e) {
      $r[] = $e;
      $prev = $e;
    }
  return $r;
}

function pagsPrepareAndTests($andTests, $groupKey, $lax) {
  $attrs = array();
  foreach ($andTests as $andTest)
    $attrs = array_merge($attrs, array_keys($andTest));

  $r = array();
  foreach (array_unique($attrs) as $attr) {
    $regexes = array();

    foreach ($andTests as $andTest)
      if (isset($andTest[$attr])) {
	$regex = $andTest[$attr];
	$regexes[] = count($regex) > 1 ? $regex : $regex[0];
      } else if (!$lax)
	exit("too complex <test-group>s for $groupKey: different <attribute-name>s");

    $r[$attr] = $regexes;
  }
  return $r;
}

function pagsOrMergeAndTests($attr2regexes, $groupKey, $lax) {
  $r = array();
  $differentAttr = '';
  foreach ($attr2regexes as $attr => $regexes) {
    $regexes = array_unique_($regexes);
    if (count($regexes) == 1) {
      $r[$attr] = $regexes[0];
    } else if ($lax || $differentAttr === '') {
      foreach ($regexes as $regex)
	if (is_array($regex))
	  exit("too complex <test-group>s for $groupKey: different <tester-value> for same <attribute-name> ($attr)");
      $r[$attr] = implode('|',$regexes);
      $differentAttr = $attr;
    } else {
      exit("too complex <test-group>s for $groupKey: different <tester-value> for different <attribute-name> ($attr and $differentAttr)");
    }
  }
  return $r;
}

function pagsGetGroupTests($g, $groupKey) {
  $tests = array();
  if (!$g->{'selection-test'}) return $tests;

  $andTests = array();
  foreach ($g->{'selection-test'}->{'test-group'} as $andTest) {
    $andTests[] = pagsGetAndTest($andTest, $groupKey);
  }

  $lax = in_array($groupKey, array('services'));

  $attr2regexes = pagsPrepareAndTests($andTests, $groupKey, $lax);

  $tests = pagsOrMergeAndTests($attr2regexes, $groupKey, $lax);

  // here we know the tests is not empty
  // may restrict to users having $supannEtablissementForGroups
  global $supannEtablissementForGroups;
  if (@$supannEtablissementForGroups)
    $tests["supannEtablissement"] = $supannEtablissementForGroups;

  return $tests;
}

function pagsGetGroups($PAGSGroupStoreConfig) {
  $xml = simplexml_load_file($PAGSGroupStoreConfig);

  $groups = array();
  $groupNameToKey = array();
  foreach ($xml->group as $g) {
    $groupKey = (string) $g->{'group-key'};
    $groupName = (string) $g->{'group-name'};

    $groups[$groupKey] = pagsGetGroupTests($g, $groupKey);
    $groupNameToKey[$groupName] = $groupKey;
  }
  return array($groups, $groupNameToKey);
}

function uportalGetLayout($fragment_layout) {
  $xml = simplexml_load_file($fragment_layout);
  if (!$xml) exit("invalid $fragment_layout");

  $layout = array();
  foreach ($xml->xpath("//folder[@type='regular']") as $folder) {
    $folder_name = (string) $folder->attributes()->name;
    if ($folder_name == 'Column') continue;

    $fnames = array();
    foreach ($folder->xpath(".//channel") as $channel) {
      $fnames[] = (string) $channel->attributes()->fname;
    }
    $layout[$folder_name] = $fnames;
  }
  return $layout;
}

function uportalGetPagsKeysAndUsers($groupNames, $groupNameToPagsKeysAndUsers) {
  $users = array();
  $groups = array();
  foreach ($groupNames as $groupName) {
    $groupKeysAndUsers = $groupNameToPagsKeysAndUsers[$groupName];
    if (!$groupKeysAndUsers) {
      error_log("$channelFile: unknown group $groupName");
      return null;
    }
    $groups = array_merge($groups, $groupKeysAndUsers["groups"]);
    $users = array_merge($users, $groupKeysAndUsers["users"]);
  }
  return array($users, $groups);
}

function uportalAbsolutateUrl($url) {
  return startsWith($url, "/") ? "https://esup.univ-paris1.fr" . $url : $url;
}
function uportalGetChannel($channelFile, $groupNameToPagsKeysAndUsers) {
  $xml = simplexml_load_file($channelFile);

  $users = simplexml_get_string_array($xml->users->user);
  $groupNames = simplexml_get_string_array($xml->groups->group);
  list ($subUsers, $groups) = uportalGetPagsKeysAndUsers($groupNames, $groupNameToPagsKeysAndUsers);

  $fname = (string) $xml->fname;
  $channel = array();
  $channel["text"] = (string) $xml->name;
  $channel["title"] = (string) $xml->title;
  $channel["description"] = (string) $xml->desc;
  $channel["users"] = array_merge($users, $subUsers);
  $channel["groups"] = $groups;
  if ($xml->hashelp == 'Y') $channel["hashelp"] = true;
  foreach ($xml->portletPreferences->portletPreference as $pref) {
    if ($pref->name == 'url') {
      $url = (string) $pref->values->value;
      $service = removePrefixOrNULL($url, "/ExternalURLStats?fname=$fname&service=");
      if ($service) {
	$url = urldecode($service);
	$channel["useExternalURLStats"] = true;
      } else {
	error_log( "$fname: no ExternalURLStats in $url" );
      }
      $channel["url"] = uportalAbsolutateUrl($url);
    }
  }
  foreach ($xml->parameters->parameter as $param) {
    if ($param->name == 'hideFromMobile')
      $channel['hideFromMobile'] = $param->value == 'true';
  }
  return array($fname, $channel);
}

function uportalGetChannels($channelFiles, $groupNameToPagsKeysAndUsers) {
  $r = array();
  foreach ($channelFiles as $channelFile) {
    list($fname, $channel) = uportalGetChannel($channelFile, $groupNameToPagsKeysAndUsers);
    $r[$fname] = $channel;
  }
  return $r;
}


function computeOneGroupNameToKeys($groupName, &$r, $groupNameToNamesAndUsers, &$computeOneGroupNameToKeys) {
    if (!isset($r[$groupName])) {
      $keys = array();
      $users = array();

      if (isset($groupNameToNamesAndUsers[$groupName])) {

	$users = $groupNameToNamesAndUsers[$groupName]["users"];

	foreach ($groupNameToNamesAndUsers[$groupName]["groups"] as $subName) {
	  $sub = computeOneGroupNameToKeys($subName, $r, $groupNameToNamesAndUsers, $computeOneGroupNameToKeys);
	  $keys = array_merge($keys, $sub["groups"]);
	  $users = array_merge($users, $sub["users"]);
	}
      } else {
	error_log("unknown group $groupName");
      }

      $r[$groupName] = array("groups" => $keys, "users" => $users);
    }
    return $r[$groupName];
}

function uportalComputeGroupNameToKeysAndUsers($groupNameToKey, $groupNameToNamesAndUsers) {
  $r = array();
  foreach ($groupNameToKey as $name => $key)
    $r[$name] = array("groups" => array($key), "users" => array());

  foreach (array_keys($groupNameToNamesAndUsers) as $name)
    computeOneGroupNameToKeys($name, $r, $groupNameToNamesAndUsers, $computeOneGroupNameToKeys);

  return $r;
}

function keepOnlyUsedChannels($channels, $layout) {
  $r = array();
  foreach ($layout as $fnames) {
    foreach ($fnames as $fname)
      if (!isset($r[$fname]))
	$r[$fname] = $channels[$fname];
  }

  foreach (array_keys($channels) as $fname) {
    if (!isset($r[$fname]))
      ;// error_log("unused channel $fname");
  }

  return $r;
}

function keepOnlyUsedPagsGroups($groups, $channels) {
  $r = array();
  foreach ($channels as $channel) {
    foreach ($channel["groups"] as $key)
      if (!isset($r[$key]))
	$r[$key] = $groups[$key];
  }
  return $r;
}

function gen_raw($l) {
  $r = '';
  $r .= "<?php\n\n// Generated\n\n";

  foreach ($l as $name => $v) {
    $r .= '$' . $name . ' = ';
    $r .= var_export($v, true);
    $r .= ";\n\n";
  }
  $r .= "?>\n"; 
  return $r;
}

function gen($PAGSGroupStoreConfig, $db_export_dir) {
  $groupNameToNamesAndUsers = uportalGetGroupsMembership(glob("$db_export_dir/group_membership/*.group_membership"));

  list($pagsGroups, $groupNameToPagsKey) = pagsGetGroups($PAGSGroupStoreConfig);

  $groupNameToPagsKeysAndUsers = uportalComputeGroupNameToKeysAndUsers($groupNameToPagsKey, $groupNameToNamesAndUsers);

  $channels = uportalGetChannels(glob("$db_export_dir/channel/*.channel"), $groupNameToPagsKeysAndUsers);

  $layout_all = uportalGetLayout("$db_export_dir/fragment-layout/all-lo.fragment-layout");
  $layout_guest = uportalGetLayout("$db_export_dir/fragment-layout/guest-lo.fragment-layout");
  unset($layout_guest["Hidden"]);
  $usedChannels = keepOnlyUsedChannels($channels, array_merge(array_values($layout_all), array_values($layout_guest)));
  $usedPagsGroups = keepOnlyUsedPagsGroups($pagsGroups, $channels);

  return gen_raw(array('LAYOUT_ALL' => $layout_all,
		       'LAYOUT_GUEST' => $layout_guest,
		       'APPS' => $usedChannels,
		       'GROUPS' => $usedPagsGroups));
}

?>
