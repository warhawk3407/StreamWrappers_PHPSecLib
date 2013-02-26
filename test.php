<?php


include('phpseclib/SFTP.php');
include('sftp_stream_wrapper.php');


$host = '192.168.1.100';
$port = '22';
$user = 'warhawk';
$pass = 'bhui';
$path = '/home/warhawk/screenlog.0';


$path = "ssh2.sftp://".$user.':'.$pass.'@'.$host.':'.$port.$path;


$handle = fopen($path, "r");

ftruncate($handle, 256);

fclose($handle);

?>