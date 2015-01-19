<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Box_Ftp
{
	protected $link;
	protected $timeout = 5;
	protected $options = array();

	protected $bb_root = NULL; // initial dir
	protected $permission = NULL;
	protected $method;

	public function __construct($opt=array())
    {
		//Check if possible to use ftp functions.
		if ( ! extension_loaded('ftp')) {
            throw new \Box_Exception('FTP extension is not enabled in PHP. You will not be able to use automatic updates and other automation.');
		}
        
		$this->method = 'ftpext';

		//Check if possible to use ftp functions.
		if (!extension_loaded('ftp')) {
			throw new \Box_Exception('The ftp PHP extension is not available');
		}

		// Set defaults:
		if ( empty($opt['port']) )
			$this->options['port'] = 21;
		else
			$this->options['port'] = $opt['port'];

		if ( empty($opt['hostname']) )
            throw new \Box_Exception('FTP hostname is required');
		else
			$this->options['hostname'] = $opt['hostname'];

		if ( isset($opt['root']) && ! empty($opt['root']) )
			$this->bb_root = $opt['root'];

		// Check if the options provided are OK.
		if ( empty($opt['username']) )
            throw new \Box_Exception('FTP username is required');
		else
			$this->options['username'] = $opt['username'];

		if ( empty($opt['password']) )
            throw new \Box_Exception('FTP password is required');
		else
			$this->options['password'] = $opt['password'];

		$this->options['ssl'] = false;
		if ( isset($opt['connection_type']) && 'ftps' == $opt['connection_type'] )
			$this->options['ssl'] = true;
	}

	public function connect()
    {
		if ( isset($this->options['ssl']) && $this->options['ssl'] && function_exists('ftp_ssl_connect') )
			$this->link = @ftp_ssl_connect($this->options['hostname'], $this->options['port'], $this->timeout);
		else
			$this->link = @ftp_connect($this->options['hostname'], $this->options['port'], $this->timeout);

		if ( ! $this->link ) {
			throw new \Box_Exception('Failed to connect to FTP Server :host : :port',array(':host'=>$this->options['hostname'], ':port'=>$this->options['port']));
		}

		if ( ! @ftp_login($this->link,$this->options['username'], $this->options['password']) ) {
            throw new \Box_Exception('FTP Username/Password incorrect for :usernmae', array(':username'=>$this->options['username']));
		}

		//Set the Connection to use Passive FTP
		@ftp_pasv( $this->link, true );

        if($this->bb_root) {
            $this->chdir($this->bb_root);
        }
        
		return true;
	}

	public function setDefaultPermissions($perm)
    {
		$this->permission = $perm;
        return $this;
	}

	public function get_contents($file, $type = '', $resumepos = 0 )
    {
		if( empty($type) )
			$type = FTP_BINARY;

		$temp = tmpfile();
		if ( ! $temp )
			return false;

		if( ! @ftp_fget($this->link, $temp, $file, $type, $resumepos) )
			return false;

		fseek($temp, 0); //Skip back to the start of the file being written to
		$contents = '';

		while ( ! feof($temp) )
			$contents .= fread($temp, 8192);

		fclose($temp);
		return $contents;
	}

	public function get_contents_array($file)
    {
		return explode("\n", $this->get_contents($file));
	}

	/**
	 * @param string $contents
	 */
	public function put_contents($file, $contents, $type = '' )
    {
		if( empty($type) )
			$type = $this->is_binary($contents) ? FTP_BINARY : FTP_ASCII;

		$temp = tmpfile();
		if ( ! $temp )
			return false;

		fwrite($temp, $contents);
		fseek($temp, 0); //Skip back to the start of the file being written to

		$ret = @ftp_fput($this->link, $file, $temp, $type);

		fclose($temp);
		return $ret;
	}

    /**
     * Put directory to ftp
     * @param string $src_dir
     * @param string $dst_dir 
     */
    public function put_dir($src_dir, $dst_dir = null)
    {
        if ($dst_dir == null) {
            $dst_dir = $this->cwd();
        }
        $src_dir = rtrim($src_dir, '/');

        $conn_id = $this->link;
        $d = dir($src_dir);
        while($file = $d->read()) { // do this for each file in the directory
            if ($file != "." && $file != "..") { // to prevent an infinite loop
                if (is_dir($src_dir."/".$file)) { // do the following if it is a directory
                    if (!@ftp_chdir($conn_id, $dst_dir."/".$file)) {
                        ftp_mkdir($conn_id, $dst_dir."/".$file); // create directories that do not yet exist
                    }
                    $this->put_dir($src_dir."/".$file, $dst_dir."/".$file); // recursive part
                } else {
                    @ftp_delete($conn_id, $dst_dir."/".$file);
                    $upload = ftp_put($conn_id, $dst_dir."/".$file, $src_dir."/".$file, FTP_BINARY); // put the files
                }
            }
        }
        $d->close();
        return true;
    }
    
	public function cwd()
    {
		$cwd = ftp_pwd($this->link);
		if( $cwd ) {
            $cwd = $this->trailingslashit($cwd);
        }
		return $cwd;
	}

	public function chdir($dir)
    {
		return @ftp_chdir($this->link, $dir);
	}

	/**
	 * @param boolean $group
	 */
	private function chgrp($file, $group, $recursive = false )
    {
		return false;
	}

	public function chmod($file, $mode = false, $recursive = false)
    {
		if( ! $mode )
			$mode = $this->permission;
		if( ! $mode )
			return false;
		if ( ! $this->exists($file) && ! $this->is_dir($file) )
			return false;
		if ( ! $recursive || ! $this->is_dir($file) ) {
			if ( ! function_exists('ftp_chmod') )
				return @ftp_site($this->link, sprintf('CHMOD %o %s', $mode, $file));
			return @ftp_chmod($this->link, $mode, $file);
		}
		//Is a directory, and we want recursive
		$filelist = $this->dirlist($file);
		foreach($filelist as $filename){
			$this->chmod($file . '/' . $filename, $mode, $recursive);
		}
		return true;
	}
    
	/**
	 * @param boolean $owner
	 */
	private function chown($file, $owner, $recursive = false )
    {
		return false;
	}

	public function owner($file)
    {
		$dir = $this->dirlist($file);
		return $dir[$file]['owner'];
	}

	public function getchmod($file)
    {
		$dir = $this->dirlist($file);
		return $dir[$file]['permsn'];
	}

	public function group($file)
    {
		$dir = $this->dirlist($file);
		return $dir[$file]['group'];
	}

    /**
     * Performs recursive directory copy with content
     * @todo check for unlimited recurse if source folder is hghr than destination
     * 
     * @param string $source
     * @param string $destination
     * @param bool $overwrite
     * @return bool
     */
    public function copyDir($source, $destination, $overwrite = false )
    {
		if( ! $overwrite && !$this->is_dir($destination) )
			return false;

		if( $overwrite && !$this->is_dir($destination) ) {
            if(!$this->mkdir($destination)) {
				return false;
            }
        }

        if (!$this->is_dir($source) || !$this->is_dir($destination)) {
            return false;
        }
        
        $res = false;
        $this->chdir($source);
        $dir_contents = $this->dirlist($source, false, true);
        if (empty($dir_contents)) { return true; }
        
        foreach($dir_contents as $name=>$params) {
            if ($params['isdir']) {
                $res = $this->copyDir($source."/".$name, $destination."/".$name, $overwrite);
            } else {
                $res = $this->copy($source."/".$name, $destination."/".$name, $overwrite);
            }
        }
        
        return $res;
    }

	/**
	 * @param string $source
	 * @param string $destination
	 */
	public function copy($source, $destination, $overwrite = false )
    {
		if( ! $overwrite && $this->exists($destination) )
			return false;
		$content = $this->get_contents($source);
		if( false === $content)
			return false;
		return $this->put_contents($destination, $content);
	}

	public function move($source, $destination, $overwrite = false)
    {
		return ftp_rename($this->link, $source, $destination);
	}

	public function delete($file, $recursive = false )
    {
		if ( empty($file) )
			return false;
		if ( $this->is_file($file) )
			return @ftp_delete($this->link, $file);
		if ( !$recursive )
			return @ftp_rmdir($this->link, $file);

		$filelist = $this->dirlist( $this->trailingslashit($file) );
		if ( !empty($filelist) )
			foreach ( $filelist as $delete_file )
				$this->delete( $this->trailingslashit($file) . $delete_file['name'], $recursive);
		return @ftp_rmdir($this->link, $file);
	}

	public function exists($file)
    {
		$list = ftp_rawlist($this->link, $file, false);
		return !empty($list); //empty list = no file, so invert.
	}

	public function is_file($file) {
		return $this->exists($file) && !$this->is_dir($file);
	}

	public function is_dir($path)
    {
		$cwd = $this->cwd();
		$result = @ftp_chdir($this->link, $this->trailingslashit($path) );
		if( $result && $path == $this->cwd() || $this->cwd() != $cwd ) {
			ftp_chdir($this->link, $cwd);
			return true;
		}
		return false;
	}

	private function is_readable($file)
    {
		//Get dir list, Check if the file is readable by the current user??
		return true;
	}

	private function is_writable($file)
    {
		//Get dir list, Check if the file is writable by the current user??
		return true;
	}

	private function atime($file)
    {
		return false;
	}

	public function mtime($file)
    {
		return ftp_mdtm($this->link, $file);
	}

	public function size($file)
    {
		return ftp_size($this->link, $file);
	}

	private function touch($file, $time = 0, $atime = 0)
    {
		return false;
	}

	/**
	 * @param string $path
	 */
	public function mkdir($path, $chmod = false, $chown = false, $chgrp = false)
    {
		if( !ftp_mkdir($this->link, $path) )
			return false;
		if( $chmod )
			$this->chmod($path, $chmod);
		if( $chown )
			$this->chown($path, $chown);
		if( $chgrp )
			$this->chgrp($path, $chgrp);
		return true;
	}
	
    public function rmdir($path, $recursive = false)
    {
		return $this->delete($path, $recursive);
	}

	private function parselisting($line)
    {
		static $is_windows;
		$b = array();
		
		if ( is_null($is_windows) )
			$is_windows = strpos( strtolower(ftp_systype($this->link)), 'win') !== false;

		if ($is_windows && preg_match("/([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)/", $line, $lucifer)) {
			if ($lucifer[3]<70) { $lucifer[3] +=2000; } else { $lucifer[3]+=1900; } // 4digit year fix
			$b['isdir'] = ($lucifer[7]=="<DIR>");
			if ( $b['isdir'] )
				$b['type'] = 'd';
			else
				$b['type'] = 'f';
			$b['size'] = $lucifer[7];
			$b['month'] = $lucifer[1];
			$b['day'] = $lucifer[2];
			$b['year'] = $lucifer[3];
			$b['hour'] = $lucifer[4];
			$b['minute'] = $lucifer[5];
			$b['time'] = @mktime($lucifer[4]+(strcasecmp($lucifer[6],"PM")==0?12:0),$lucifer[5],0,$lucifer[1],$lucifer[2],$lucifer[3]);
			$b['am/pm'] = $lucifer[6];
			$b['name'] = $lucifer[8];
		} else if (!$is_windows && $lucifer=preg_split("/[ ]/",$line,9,PREG_SPLIT_NO_EMPTY)) {
			//echo $line."\n";
			$lcount=count($lucifer);
			if ($lcount<8) return '';
			$b = array();
			$b['isdir'] = $lucifer[0]{0} === "d";
			$b['islink'] = $lucifer[0]{0} === "l";
			if ( $b['isdir'] )
				$b['type'] = 'd';
			elseif ( $b['islink'] )
				$b['type'] = 'l';
			else
				$b['type'] = 'f';
			$b['perms'] = $lucifer[0];
			$b['number'] = $lucifer[1];
			$b['owner'] = $lucifer[2];
			$b['group'] = $lucifer[3];
			$b['size'] = $lucifer[4];
			if ($lcount==8) {
				sscanf($lucifer[5],"%d-%d-%d",$b['year'],$b['month'],$b['day']);
				sscanf($lucifer[6],"%d:%d",$b['hour'],$b['minute']);
				$b['time'] = @mktime($b['hour'],$b['minute'],0,$b['month'],$b['day'],$b['year']);
				$b['name'] = $lucifer[7];
			} else {
				$b['month'] = $lucifer[5];
				$b['day'] = $lucifer[6];
				if (preg_match("/([0-9]{2}):([0-9]{2})/",$lucifer[7],$l2)) {
					$b['year'] = date("Y");
					$b['hour'] = $l2[1];
					$b['minute'] = $l2[2];
				} else {
					$b['year'] = $lucifer[7];
					$b['hour'] = 0;
					$b['minute'] = 0;
				}
				$b['time'] = strtotime(sprintf("%d %s %d %02d:%02d",$b['day'],$b['month'],$b['year'],$b['hour'],$b['minute']));
				$b['name'] = $lucifer[8];
			}
		}

		return $b;
	}

	public function dirlist($path = '.', $incdot = false, $recursive = false)
    {
		if( $this->is_file($path) ) {
			$limitFile = basename($path);
			$path = dirname($path) . '/';
		} else {
			$limitFile = false;
		}
        
		$list = ftp_rawlist($this->link, '-a ' . $path, false);

		if ( $list === false )
			return false;

		$dirlist = array();
		foreach ( $list as $k => $v ) {
			$entry = $this->parselisting($v);
			if ( empty($entry) )
				continue;

			if ( '.' == $entry["name"] || '..' == $entry["name"] )
				continue;

			$dirlist[ $entry['name'] ] = $entry;
		}

		if ( ! $dirlist )
			return false;
		if ( empty($dirlist) )
			return array();

		$ret = array();
		foreach ( $dirlist as $struc ) {

			if ( 'd' == $struc['type'] ) {
				$struc['files'] = array();

				if ( $incdot ){
					//We're including the doted starts
					if( '.' != $struc['name'] && '..' != $struc['name'] ){ //Ok, It isnt a special folder
						if ($recursive)
							$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $incdot, $recursive);
					}
				} else { //No dots
					if ($recursive)
						$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $incdot, $recursive);
				}
			}
			//File
			$ret[$struc['name']] = $struc;
		}
		return $ret;
	}

    /**
     * Return current directory contents
     * @return array
     */
    public function dirContents()
    {
        $contents = ftp_nlist($this->link, ".");
        return $contents;
    }

    protected function trailingslashit($string)
    {
        $string = rtrim($string, '/');
        return $string . '/';
    }

	protected function is_binary( $text )
    {
		return (bool) preg_match('|[^\x20-\x7E]|', $text); //chr(32)..chr(127)
	}

    public function disconnect()
    {
		if( $this->link )
			ftp_close($this->link);
    }

	public function __destruct()
    {
        $this->disconnect();
	}

}