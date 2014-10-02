<?php

$LAYOUT_ALL = array(
  'Accueil' => array('caccueil-pers', 'caccueil-etu'),
);

$LAYOUT_GUEST = array(
  'Accueil' => array('caccueil-guest'),
);

$APPS = array(
  'caccueil-pers' => 
  array(
    'text' => 'Accueil personnel',
    'title' => 'Accueil',
    'description' => 'Page d\'accueil de l\'ENT pour le personnel',
    'users' => array(),
    'groups' => array('TousBiatos', 'TousCher', 'TousEme', 'TousEns', 'TousInvites'),
    'url' => 'https://www.univ.fr/fileadmin/ENT/accueil-ent-pers.html',
  ),
  'caccueil-etu' => 
  array(
    'text' => 'Accueil étudiant',
    'title' => 'Accueil',
    'description' => 'Page d\'accueil de l\'ENT pour les étudiants',
    'users' => array(),
    'groups' => array('TousEtudMembre'),
    'url' => 'https://www.univ.fr/fileadmin/ENT/accueil-ent-etu.html',
  ),
  'caccueil-default' => 
  array(
    'text' => 'Accueil défaut',
    'title' => 'Accueil',
    'description' => 'Page d\'accueil de l\'ENT pour personnes sans profile',
    'users' => array(),
    'groups' => array('not_paris1'),
    'url' => 'https://www.univ.fr/fileadmin/ENT/accueil-ent-default.html',
  ),
);

$GROUPS = array(
  'TousBiatos' => array('eduPersonAffiliation' => '^staff$'),
  'TousCher' => array('eduPersonAffiliation' => '^researcher$'),
  'TousEme' => array('eduPersonAffiliation' => '^emeritus$'),
  'TousEns' => array('eduPersonAffiliation' => '^teacher$'),
  'TousInvites' => array('eduPersonAffiliation' => '^affiliate$'),
  'TousAnciens' => array('eduPersonAffiliation' => '^alum$'),
  'TousRetraite' => array('eduPersonAffiliation' => '^retired$'),
  'diploma' => 
  array(
    'supannEtuEtape' => '.+',
    'uid' => '.+',
  ),
  'TousEtudMembre' => array('eduPersonAffiliation' => array('^student$', '^member$')),
  'Defaut' => array('uid' => '.+'),
  'not_paris1' => array('Shib-Identity-Provider' => '^(?!urn:mace:cru.fr:federation:univ-paris1.fr).+'),
);

?>
