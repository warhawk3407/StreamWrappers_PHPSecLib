<?php

/**
 * PHP 5.4.0
 *
 * This class implements a read/write SFTP stream wrapper based on 'phpseclib'
 *
 * Requirement:	phpseclib - PHP Secure Communications Library
 *
 * Filename:	SFTP_StreamWrapper.php
 * Classname:	SFTP_StreamWrapper
 *
 * ###################################################################
 * # Protocol									ssh2.sftp
 * ###################################################################
 * # Restricted by allow_url_fopen				Yes
 * # Allows Reading								Yes
 * # Allows Writing								Yes
 * # Allows Appending							Yes
 * # Allows Simultaneous Reading and Writing	No
 * # Supports stat()							Yes
 * # Supports unlink()							Yes
 * # Supports rename()							Yes
 * # Supports mkdir()							Yes
 * # Supports rmdir()							Yes
 * ###################################################################
 *
 * @category	Net
 * @package		Net_SFTP_StreamWrapper
 * @author		Warhawk3407 a.k.a Nikita ROUSSEAU <warhawk3407@gmail.com>
 * @copyright	2013 Nikita Rousseau
 * @license		http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version		Release: @1.0.0@
 * @date		March 2013
 */

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

/**
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *		include('Net/SFTP_StreamWrapper.php');
 *
 *		$host = 'www.domain.tld';
 *		$port = '22';
 *		$user = 'user';
 *		$pass = 'secret';
 *		$path = '/home/user/file';
 *
 *		$url = "ssh2.sftp://".$user.':'.$pass.'@'.$host.':'.$port.$path;
 *
 *		print_r(stat($url));
 *
 *		echo "\r\n<hr>\r\n";
 *
 *		$handle = fopen($path, "r");
 *		$contents = '';
 *		while (!feof($handle)) {
 *			$contents .= fread($handle, 8192);
 *		}
 *		fclose($handle);
 *		echo $contents;
 * ?>
 * </code>
 */



/**
 * Include Net_SFTP
 */
if (!class_exists('Net_SFTP')) {
    require_once('Net/SFTP.php');
}

/**
 * Check PHP_VERSION
 */
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



/**
 * Pure-PHP implementations of Net_SFTP as a stream wrapper class
 *
 * @author	Nikita ROUSSEAU <warhawk3407@gmail.com>
 * @version	1.0.0
 * @access	public
 * @package	Net_SFTP_StreamWrapper
 * @link	http://www.php.net/manual/en/class.streamwrapper.php
 */
class SFTP_StreamWrapper{

	/**
	 * SFTP VARS
	 *
	 * @var String
	 * @see SFTP_StreamWrapper::stream_open()
	 * @access private
	 */
	var $host;
	var $port;
	var $user;
	var $pass;

	/**
	 * SFTP Object
	 *
	 * @var Net_SFTP
	 * @access private
	 */
	var $ressource;

	/**
	 * SFTP Path
	 *
	 * @var String
	 * @access private
	 */
	var $path;

	/**
	 * Pointer Offset
	 *
	 * @var Integer
	 * @access private
	 */
	var $position;

	/**
	 * This method is called in response to closedir()
	 *
	 * Closes a directory handle
	 *
	 * Alias of stream_close()
	 *
	 * @return bool
	 * @access public
	 */
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

	/**
	 * This method is called in response to opendir()
	 *
	 * Opens a directory handle
	 *
	 * @param String $path
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
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

	/**
	 * This method is called in response to readdir()
	 *
	 * Reads entry from directory
	 *
	 * NOTE: In this method, Pointer Offset is an index
	 * of the array returned by Net_SFTP::nlist()
	 *
	 * @return string
	 * @access public
	 */
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

	/**
	 * This method is called in response to rewinddir()
	 *
	 * Resets the directory pointer to the beginning of the directory
	 *
	 * @return bool
	 * @access public
	 */
	function dir_rewinddir()
	{
		$this->position = 0;

		return TRUE;
	}

	/**
	 * Attempts to create the directory specified by the path
	 *
	 * Makes a directory
	 *
	 * @param String $path
	 * @param Integer $mode
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
	function mkdir($path, $mode, $options)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		if ( $options === STREAM_MKDIR_RECURSIVE ) {
			$mkdir = $this->ressource->mkdir($this->path, $mode, true);
		}
		else {
			$mkdir = $this->ressource->mkdir($this->path, $mode, false);
		}

		$this->stream_close();

		if( $mkdir == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Attempts to rename path_from to path_to
	 *
	 * Renames a file or directory
	 *
	 * @param String $path_from
	 * @param String $path_to
	 * @return bool
	 * @access public
	 */
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

	/**
	 * Attempts to remove the directory named by the path
	 *
	 * Removes a directory
	 *
	 * @param String $path
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
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

	/**
	 * This method is called in response to stream_select()
	 *
	 * Not implemented
	 *
	 * @param Integer $cast_as
	 * @return ressource
	 * @access public
	 */
	function stream_cast($cast_as)
	{
		return FALSE;
	}

	/**
	 * This method is called in response to fclose()
	 *
	 * Closes SFTP connection
	 *
	 * @return void
	 * @access public
	 */
	function stream_close()
	{
		$this->ressource->disconnect();

		$this->position = 0;
	}

	/**
	 * This method is called in response to feof()
	 *
	 * Tests for end-of-file on a file pointer
	 *
	 * @return bool
	 * @access public
	 */
	function stream_eof()
	{
		$filesize = $this->ressource->size($this->path);

		if ($this->position >= $filesize) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * This method is called in response to fflush()
	 *
	 * Not implemented
	 *
	 * @return bool
	 * @access public
	 */
	function stream_flush()
	{
		return TRUE;
	}

	/**
	 * This method is called to set metadata on the stream. It is called when one of the following functions is called on a stream URL:
	 * - touch()
	 * - chmod()
	 * - chown()
	 * - chgrp()
	 *
	 * Changes stream options
	 *
	 * @param String $path
	 * @param Integer $option
	 * @param mixed $var
	 * @return bool
	 * @access public
	 */
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

	/**
	 * This method is called immediately after the wrapper is initialized
	 *
	 * Connects to an SFTP server
	 *
	 * NOTE: This method is not get called for the following functions:
	 * dir_opendir(), mkdir(), rename(), rmdir(), stream_metadata(), unlink() and url_stat()
	 *
	 * @param String $path
	 * @param String $mode
	 * @param Integer $options
	 * @param String &$opened_path
	 * @return bool
	 * @access public
	 */
	function stream_open($path, $mode, $options, &$opened_path)
	{
		$url = parse_url($path);

		$this->host = $url["host"];
		$this->port = $url["port"];
		$this->user = $url["user"];
		$this->pass = $url["pass"];
		$this->path = $url["path"];

		// Connection
		$this->ressource = new Net_SFTP($this->host, $this->port);
		if (!$this->ressource->login($this->user, $this->pass))
		{
			return FALSE;
		}

		$this->position = 0; // Pointer Initialisation

		if ($options == STREAM_USE_PATH) {
			$opened_path = $this->ressource->pwd();
		}

		return TRUE;
	}

	/**
	 * This method is called in response to fread() and fgets()
	 *
	 * Reads from stream
	 *
	 * @param Integer $count
	 * @return String
	 * @access public
	 */
	function stream_read($count)
	{
		$chunk = $this->ressource->get( $this->path, FALSE, $this->position, $count );

		$this->position += strlen($chunk);

		return $chunk;
	}

	/**
	 * This method is called in response to fseek()
	 *
	 * Seeks to specific location in a stream
	 *
	 * @param Integer $offset
	 * @param Integer $whence = SEEK_SET
	 * @return bool
	 * @access public
	 */
	function stream_seek($offset, $whence)
	{
		$filesize = $this->ressource->size($this->path);

		$newPosition = 0;

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

		if ( $newPosition >= 0 ) {
			$this->position = $newPosition;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This method is called to set options on the stream
	 *
	 * Not implemented
	 *
	 * @param Integer $option
	 * @param Integer $arg1
	 * @param Integer $arg2
	 * @return bool
	 * @access public
	 */
	function stream_set_option($option, $arg1, $arg2)
	{
		return FALSE;
	}

	/**
	 * This method is called in response to fstat()
	 *
	 * Retrieves information about a file resource
	 *
	 * @return Array
	 * @access public
	 */
	function stream_stat()
	{
		$stat = $this->ressource->stat($this->path);

		if( !empty($stat) ) {
			// mode fix
			$stat['mode'] = $stat['permissions'];
			unset($stat['permissions']);

			return $stat;
		} else {
			return array();
		}
	}

	/**
	 * This method is called in response to fseek() to determine the current position
	 *
	 * Retrieves the current position of a stream
	 *
	 * @return Integer
	 * @access public
	 */
	function stream_tell()
	{
		return $this->position;
	}

	/**
	 * Will respond to truncation, e.g., through ftruncate()
	 *
	 * Truncates a stream
	 *
	 * @param Integer $new_size
	 * @return bool
	 * @access public
	 */
	function stream_truncate($new_size)
	{
		$data = $this->ressource->get( $this->path, FALSE, 0, $new_size );

		$this->ressource->put($this->path, $data);

		return TRUE;
	}

	/**
	 * This method is called in response to fwrite()
	 *
	 * Writes to stream
	 *
	 * @param String $data
	 * @return Integer
	 * @access public
	 */
	function stream_write($data)
	{
		$this->ressource->put($this->path, $data, NET_SFTP_STRING, $this->position);

		$this->position += strlen($data);

		return strlen($data);
	}

	/**
	 * Deletes filename specified by the path
	 *
	 * Deletes a file
	 *
	 * @param String $path
	 * @return bool
	 * @access public
	 */
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

	/**
	 * This method is called in response to all stat() related functions
	 *
	 * Retrieves information about a file
	 *
	 * @see SFTP_StreamWrapper::stream_stat()
	 * @param String $path
	 * @param Integer $flags
	 * @return array
	 * @access public
	 */
	function url_stat($path, $flags)
	{
		$this->stream_open($path, NULL, NULL, $opened_path);

		if ( $flags === STREAM_URL_STAT_LINK ) {
			$stat = $this->ressource->lstat($this->path);
		}
		else {
			$stat = $this->ressource->stat($this->path);
		}

		$this->stream_close();

		if( !empty($stat) ) {
			// mode fix
			$stat['mode'] = $stat['permissions'];
			unset($stat['permissions']);

			return $stat;
		} else {
			return array();
		}
	}

}

###################################################################
# Register 'ssh2.sftp' protocol
###################################################################

stream_wrapper_register('ssh2.sftp', 'SFTP_StreamWrapper')
	or die ('Failed to register protocol');

?>