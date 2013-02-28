<?php
###################################################################
# sftp.php
# This class implements a basic read/write SFTP stream wrapper
# based on 'phpseclib'
#
# protocol: ssh2.sftp
# classname: sftp_stream_wrapper
# requirement: PHP 5.4.0
#
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

if (version_compare(PHP_VERSION, '5.4.0') == -1) {
	exit('PHP 5.4.0 is required!');
}



/**
 * Stream Metadata: Engine part
 *
 * The options supported currently are:
 */
define('PHP_STREAM_META_TOUCH',			1);
define('PHP_STREAM_META_OWNER_NAME',	2);
define('PHP_STREAM_META_OWNER',			3);
define('PHP_STREAM_META_GROUP_NAME',	4);
define('PHP_STREAM_META_GROUP',			5);
define('PHP_STREAM_META_ACCESS',		6);



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

		if ($options == STREAM_USE_PATH) {
			$opened_path = $this->ressource->pwd();
		}

		return TRUE;
	}

	function stream_close()
	{
		$this->ressource->disconnect();

		$this->position = 0;
	}

	function dir_opendir($path, $options)
	{
		$this->stream_open($path, NULL, $options, $opened_path);

		$chdir = $this->ressource->chdir($this->path);

		if( $chdir == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function dir_closedir()
	{
		//$chdir = $this->ressource->chdir('..');

		$this->stream_close();

		/*
		if( $chdir == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
		*/

		return TRUE;
	}

	function dir_readdir()
	{
		$nlist = $this->ressource->nlist($this->path);

		if ( array_key_exists($this->position, $nlist) ) {
			$filename = $nlist[$this->position];

			$this->position += 1;

			return $filename;
		}
		else {
			return FALSE;
		}
	}

	function dir_rewinddir()
	{
		$this->position = 0;

		return TRUE;
	}

	function mkdir($path, $mode, $options)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		$mkdir = $this->ressource->mkdir($this->path, $mode, $options);

		$this->stream_close();

		if( $mkdir == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function rmdir($path, $options)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		$rmdir = $this->ressource->rmdir($this->path);

		$this->stream_close();

		if( $rmdir == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function rename($path_from, $path_to)
	{
		$this->stream_open($path_from, NULL, NULL, $opened_path);

		$path_to_url = parse_url($path_to);

		$rename = $this->ressource->rename($this->path, $path_to_url['path']);

		$this->stream_close();

		if( $rename == 1) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function stream_cast($cast_as)
	{
		return FALSE; // Not implemented
	}

	function stream_flush()
	{
		return FALSE; // Not implemented
	}

	function stream_metadata($path, $option, $var)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		switch ($option) {
			case PHP_STREAM_META_TOUCH:
				$touch = $this->ressource->touch($this->path, $var[1], $var[0]);

				$this->stream_close();

				if ($touch == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_OWNER_NAME:
				$this->stream_close();

				return FALSE;
				break;

			case PHP_STREAM_META_OWNER:
				$chown = $this->ressource->chown($this->path, $var);

				$this->stream_close();

				if ($chown == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_GROUP_NAME:
				$this->stream_close();

				return FALSE;
				break;

			case PHP_STREAM_META_GROUP:
				$chgrp = $this->ressource->chgrp($this->path, $var);

				$this->stream_close();

				if ($chgrp == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			case PHP_STREAM_META_ACCESS:
				$chmod = $this->ressource->chmod($var, $this->path);

				$this->stream_close();

				if ($chmod == 1) {
					return TRUE;
				} else {
					return FALSE;
				}
				break;

			default:
				$this->stream_close();
				return false;
		}
	}

	function stream_read($count)
	{
		$chunk = $this->ressource->get( $this->path, FALSE, $this->position, $count );

		$this->position += strlen($chunk);

		return $chunk;
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

	function stream_seek($offset, $whence)
	{
		$stat = $this->ressource->stat($this->path);
		$filesize = $stat['size'];

		switch ($whence) {
			case SEEK_SET:
				$newPosition = $offset;
				break;

			case SEEK_CUR:
				$newPosition += $offset;
				break;

			case SEEK_END:
				$newPosition = $filesize + $offset;
				break;

			default:
				return false;
		}

		if ($newPosition >= 0) {
			$this->position = $newPosition;
			return true;
		} else {
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

		if( !empty($stat) ) {
			return $stat;
		} else {
			return array();
		}
	}

	function stream_tell()
	{
		return $this->position;
	}


	function stream_truncate($new_size)
	{
		$data = $this->ressource->get( $this->path, FALSE, 0, $new_size );

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
		$this->stream_open($path, NULL, NULL, $opened_path);

		$del = $this->ressource->delete($this->path);

		$this->stream_close();

		if( $del == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function url_stat($path, $flags)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		$stat = $this->ressource->stat($this->path);

		$this->stream_close();

		if( !empty($stat) ) {
			return $stat;
		} else {
			return array();
		}
	}

}

###################################################################
# Register 'ssh2.sftp' protocol
###################################################################

stream_wrapper_register('ssh2.sftp', 'sftp_stream_wrapper')
	or die ('Failed to register protocol');

?>