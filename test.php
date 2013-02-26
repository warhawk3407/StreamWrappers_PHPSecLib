<?php


include('phpseclib/SFTP.php');
include('sftp_stream_wrapper.php');


$host = '192.168.1.100';
$port = '22';
$user = 'warhawk';
$pass = 'bhui';
$path = '/home/warhawk/ping.sh';


$path = "ssh2.sftp://".$user.':'.$pass.'@'.$host.':'.$port.$path;


$handle = fopen($path, "r");

// gather statistics
$fstat = fstat($handle);

// print only the associative part
print_r($fstat);

fclose($handle);

?>