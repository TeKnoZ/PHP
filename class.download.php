<?
//a Class allows downloading files into limited bandwith by buffering, optimal for files-hosting solutions.
class httpdownload {

	var $data = null;
	var $data_len = 0;
	var $data_mod = 0;
	var $data_type = 0;
	var $data_section = 0;
	var $handler = array('auth' => null);
	var $use_resume = true;
	var $use_autoexit = false;
	var $use_auth = false;
	var $filename = null;
	var $mime = null;
	var $bufsize = 2048;
	var $seek_start = 0;
	var $seek_end = -1;

	var $bandwidth = 0;

	var $speed = 0;

	function initialize() {
		global $HTTP_SERVER_VARS;
		
		if ($this->use_auth)
		{
			if (!$this->_auth())
			{
				header('WWW-Authenticate: Basic realm="Please enter your username and password"');
    		header('HTTP/1.0 401 Unauthorized');
    		header('status: 401 Unauthorized');
    		if ($this->use_autoexit) exit();
				return false;
			}
		}
		if ($this->mime == null) $this->mime = "application/octet-stream";
		
		if (isset($_SERVER['HTTP_RANGE']) || isset($HTTP_SERVER_VARS['HTTP_RANGE']))
		{
			
			if (isset($HTTP_SERVER_VARS['HTTP_RANGE'])) $seek_range = substr($HTTP_SERVER_VARS['HTTP_RANGE'] , strlen('bytes='));
			else $seek_range = substr($_SERVER['HTTP_RANGE'] , strlen('bytes='));
			
			$range = explode('-',$seek_range);
			
			if ($range[0] > 0)
			{
				$this->seek_start = intval($range[0]);
			}
			
			if ($range[1] > 0) $this->seek_end = intval($range[1]);
			else $this->seek_end = -1;
			
			if (!$this->use_resume)
			{
				$this->seek_start = 0;

			}
			else
			{
				$this->data_section = 1;
			}
			
		}
		else
		{
			$this->seek_start = 0;
			$this->seek_end = -1;
		}
		
		return true;
	}
	function header($size,$seek_start=null,$seek_end=null) {
		header('Content-type: ' . $this->mime);
		header('Content-Disposition: attachment; filename="' . $this->filename . '"');
		header('Last-Modified: ' . date('D, d M Y H:i:s \G\M\T' , $this->data_mod));
		
		if ($this->data_section && $this->use_resume)
		{
			header("HTTP/1.0 206 Partial Content");
			header("Status: 206 Partial Content");
			header('Accept-Ranges: bytes');
			header("Content-Range: bytes $seek_start-$seek_end/$size");
			header("Content-Length: " . ($seek_end - $seek_start + 1));
		}
		else
		{
			header("Content-Length: $size");
		}
	}
	
	function download_ex($size)
	{
		if (!$this->initialize()) return false;
		ignore_user_abort(true);

		if ($this->seek_start > ($size - 1)) $this->seek_start = 0;
		if ($this->seek_end <= 0) $this->seek_end = $size - 1;
		$this->header($size,$seek,$this->seek_end);
		$this->data_mod = time();
		return true;
	}
	

	function download() {
		if (!$this->initialize()) return false;
		
		$seek = $this->seek_start;
		$speed = $this->speed;
		$bufsize = $this->bufsize;
		$packet = 1;
		

		@ob_end_clean();
		$old_status = ignore_user_abort(true);
		@set_time_limit(0);
		$this->bandwidth = 0;
		
		$size = $this->data_len;
		
		if ($this->data_type == 0) 
		{
			
			$size = filesize($this->data);
			if ($seek > ($size - 1)) $seek = 0;
			if ($this->filename == null) $this->filename = basename($this->data);
			
			$res = fopen($this->data,'rb');
			if ($seek) fseek($res , $seek);
			if ($this->seek_end < $seek) $this->seek_end = $size - 1;
			
			$this->header($size,$seek,$this->seek_end); 
			$size = $this->seek_end - $seek + 1;
			
			while (!(connection_aborted() || connection_status() == 1) && $size > 0)
			{
				if ($size < $bufsize)
				{
					echo fread($res , $size);
					$this->bandwidth += $size;
				}
				else
				{
					echo fread($res , $bufsize);
					$this->bandwidth += $bufsize;
				}
				
				$size -= $bufsize;
				flush();
				
				if ($speed > 0 && ($this->bandwidth > $speed*$packet*1024))
				{
					sleep(1);
					$packet++;
				}
			}
			fclose($res);
			
		}
		
		elseif ($this->data_type == 1)
		{
			if ($seek > ($size - 1)) $seek = 0;
			if ($this->seek_end < $seek) $this->seek_end = $this->data_len - 1;
			$this->data = substr($this->data , $seek , $this->seek_end - $seek + 1);
			if ($this->filename == null) $this->filename = time();
			$size = strlen($this->data);
			$this->header($this->data_len,$seek,$this->seek_end);
			while (!connection_aborted() && $size > 0) {
				if ($size < $bufsize)
				{
					$this->bandwidth += $size;
				}
				else
				{
					$this->bandwidth += $bufsize;
				}
				
				echo substr($this->data , 0 , $bufsize);
				$this->data = substr($this->data , $bufsize);
				
				$size -= $bufsize;
				flush();
				
				if ($speed > 0 && ($this->bandwidth > $speed*$packet*1024))
				{
					sleep(1);
					$packet++;
				}
			}
		} else if ($this->data_type == 2) {
			header('location: ' . $this->data);
		}
		
		if ($this->use_autoexit) exit();
		
		ignore_user_abort($old_status);
		set_time_limit(ini_get("max_execution_time"));
		
		return true;
	}
	
	function set_byfile($dir) {
		if (is_readable($dir) && is_file($dir)) {
			$this->data_len = 0;
			$this->data = $dir;
			$this->data_type = 0;
			$this->data_mod = filemtime($dir);
			return true;
		} else return false;
	}
	
	function set_bydata($data) {
		if ($data == '') return false;
		$this->data = $data;
		$this->data_len = strlen($data);
		$this->data_type = 1;
		$this->data_mod = time();
		return true;
	}
	
	function set_byurl($data) {
		$this->data = $data;
		$this->data_len = 0;
		$this->data_type = 2;
		return true;
	}
	
	function set_lastmodtime($time) {
		$time = intval($time);
		if ($time <= 0) $time = time();
		$this->data_mod = $time;
	}
	

	function _auth() {
		if (!isset($_SERVER['PHP_AUTH_USER'])) return false;
		if (isset($this->handler['auth']) && function_exists($this->handler['auth']))
		{
			return $this->handler['auth']('auth' , $_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);
		}
		else return true;
	}
	
}

?>