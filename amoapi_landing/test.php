<?php

use Amolib\AmoHelper;

$test = [
    'test1' => 'typo1',
    'test2'=> 'typo2',
    'test3' => 'OPA OPA OP',
];
echo $test['3'];
$json_model = json_encode($test);
file_put_contents('token_auth.json', $json_model);
$json_file = file_get_contents('token_auth.json');
$json_array = json_decode($json_file);
echo $json_array->test3;
?>