<?php
###################################################################
# sftp.php
# This class implements a basic read/write SFTP stream wrapper
# based on 'phpseclib'
#
# protocol: ssh2.sftp
# classname: sftp_stream_wrapper
#
# Date: February 2013
#
# Author:  Nikita ROUSSEAU <warhawk3407@gmail.com>
###################################################################

/**
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

if (!class_exists('Net_SFTP')) {
    exit('Fatal Error: Net_SFTP (PHPSecLib) is not defined!');
}

class sftp_stream_wrapper{

	/* SFTP VARS */
	var $host;
	var $port;
	var $user;
	var $pass;

	/* SFTP Object */
	var $ressource;

	/* Path */
	var $path;

	/* Pointer Offset */
	var $position;

	function stream_open($path, $mode, $options, &$opened_path)
	{
		$url = parse_url($path);

		$this->host = $url["host"];
		$this->port = $url["port"];
		$this->user = $url["user"];
		$this->pass = $url["pass"];
		$this->path = $url["path"];

		// Connection
		$this->ressource = new Net_SFTP($this->host.':'.$this->port);
		if (!$this->ressource->login($this->user, $this->pass))
		{
			return FALSE;
		}

		$this->position = 0;

		return TRUE;
	}

	function stream_close()
	{
		$this->ressource->disconnect();

		$this->position = 0;
	}

	function dir_closedir()
	{
		$chdir = $this->ressource->chdir('..');

		if( $chdir == 1 ){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function dir_opendir($path, $options)
	{
		$url = parse_url($path);

		$chdir = $this->ressource->chdir($url['path']);

		if( $chdir == 1 ){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function dir_readdir()
	{
		return 'FALSE'; // Not implemented
	}

	function dir_rewinddir()
	{
		return FALSE; // Not implemented
	}

	function mkdir($path, $mode, $options)
	{
		$url = parse_url($path);

		$mkdir = $this->ressource->mkdir($url['path']);

		if( $mkdir == 1 ){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function rmdir($path, $options)
	{
		$url = parse_url($path);

		$rmdir = $this->ressource->rmdir($url['path']);

		if($rmdir == 1){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function rename($path_from, $path_to)
	{
		$rename = $this->ressource->rename($path_from, $path_to);

		if($rename == 1){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function stream_cast($cast_as)
	{
		return FALSE; // Not implemented
	}

	function stream_eof()
	{
		$stat = $this->ressource->stat($this->path);
		$filesize = $stat['size'];

		if ($this->position >= $filesize) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function stream_flush()
	{
		return FALSE; // Not implemented
	}

	/**
	 * https://bugs.php.net/bug.php?id=64246
	 *
	 *
	 * Bug #64246 	stream_metadata constants not defined
	 * Submitted: 	2013-02-19 20:00 UTC 	Modified: 	-
	 * From: 	terrafrost@php.net 	Assigned:
	 * Status: 	Open 	Package: 	Streams related
	 * PHP Version: 	5.4.11 	OS: 	Windows 7
	 * Private report: 	No 	CVE-ID:
	 */
/*
	function stream_metadata($path, $option, $var)
	{
		$url = parse_url($path);

		switch ($option) {
			case PHP_STREAM_META_TOUCH:
				$touch = $this->ressource->touch($url['path'], $var[0], $var[1]);

				if ($touch == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_OWNER_NAME:
				return FALSE;
				break;

			case PHP_STREAM_META_OWNER:
				$chown = $this->ressource->chown($url['path'], $var);

				if ($chown == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_GROUP_NAME:
				return FALSE;
				break;

			case PHP_STREAM_META_GROUP:
				$chgrp = $this->ressource->chgrp($url['path'], $var);

				if ($chgrp == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_ACCESS:
				$chmod = $this->ressource->chmod($var, $url['path']);

				if ($chmod == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			default:
				return false;
		}
	}
*/

	function stream_read($count)
	{
		$ret = substr($this->ressource->get($this->path), $this->position, $count);

		$this->position += strlen($ret);
		return $ret;
	}

	function stream_seek($offset, $whence)
	{
		switch ($whence) {
			case SEEK_SET:
				$this->position = $offset;
				return true;
				break;

			case SEEK_CUR:
				if ($offset >= 0) {
					$this->position += $offset;
					return true;
				} else {
					return false;
				}
				break;

			case SEEK_END:
				$stat = $this->ressource->stat($this->path);
				$filesize = $stat['size'];

				if ( ($filesize + $offset) >= 0) {
					$this->position = $filesize + $offset;
					return true;
				} else {
					return false;
				}
				break;

			default:
				return false;
		}
	}

	function stream_set_option($option, $arg1, $arg2)
	{
		return FALSE; // Not implemented
	}

	function stream_stat()
	{
		$stat = $this->ressource->stat($this->path);

		if(!empty($stat)){
			return $stat;
		}else{
			return array();
		}
	}

	function stream_tell()
	{
		return $this->position;
	}

	function stream_truncate($new_size)
	{
		$data = substr($this->ressource->get($this->path), 0, $new_size);

		$this->ressource->put($this->path, $data);

		return TRUE;
	}

	function stream_write($data)
	{
		$this->ressource->put($this->path, $data, NET_SFTP_STRING, $this->position);

		$this->position += strlen($data);
		return strlen($data);
	}

	function unlink($path)
	{
		$url = parse_url($path);

		$del = $this->ressource->delete($url['path']);

		if($del == 1){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	function url_stat($path, $flags)
	{
		$url = parse_url($path);

		$stat = $this->ressource->stat($url['path']);

		if(!empty($stat)){
			return $stat;
		}else{
			return array();
		}
	}

}

###################################################################
# Register 'sftp' protocol
###################################################################

stream_wrapper_register('ssh2.sftp', 'sftp_stream_wrapper')
	or die ('Failed to register protocol');

?>