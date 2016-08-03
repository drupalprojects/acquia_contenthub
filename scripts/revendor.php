<?php

$data = json_decode( file_get_contents('composer.json') , true);
$data['config']['vendor-dir'] = "web/vendor";
file_put_contents('composer.json', json_encode($data, JSON_PRETTY_PRINT) );