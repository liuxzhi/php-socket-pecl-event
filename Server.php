<?php
include_once(__DIR__.'/Events.php');
include_once(__DIR__.'/Socket.php');
$connect = 'tcp://0.0.0.0:8088';
$socket  = new Socket($connect);
$socket->start();

