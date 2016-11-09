<?php

$data = json_decode( file_get_contents('composer.json') , true);
// Various vendor packages go into web/vendor.
$data['config']['vendor-dir'] = "web/vendor";
// Location of drupal core, contrib modules, themes etc is determined
// by 'installer-paths' in composer.json.
// Replace /docroot with /web in such installer-paths.
if (isset($data['extra']['installer-paths'])) {
  foreach ($data['extra']['installer-paths'] as $key => $value) {
      $new_key = str_replace("docroot/","web/",$key);
      $data['extra']['installer-paths'][$new_key] = $value;
      unset($data['extra']['installer-paths'][$key]);
  }
}
// Rewrite composer.json with your changes.
file_put_contents('composer.json', json_encode($data, JSON_PRETTY_PRINT) );
