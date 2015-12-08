<?php
//Class to handle uploading files and store it using hashes without extensions for security.

class upload{
	var $mode = '';
	var $exts = array();
	var $trans = array();
	var $folder = '';
	var $num_files = 0;
	var $maxUps = 0;
	var $av_space = 0;
	var $done = array();
	var $notDone = array();
	var $report = array();
	var $fake_exts = array();
	
	function upload($frm){
		$this->frm = $frm;
		$this->mode = @is_array($_FILES[$frm]['tmp_name'])?'multiple':'single';
	}
	function run(){
		$i = 0;
		$new_space = 0;
		if($this->mode == 'multiple'){
			foreach($_FILES[$this->frm]['tmp_name'] as $k=>$v){
				if(! $v) continue;

				if($i >= $this->maxUps) break;
				$new_space += $_FILES[$this->frm]['size'][$k];
				if($this->av_space != -1 && ($new_space > $this->av_space) ){
					$this->notDone[] = array('file'=>$_FILES[$this->frm]['name'][$k] , 'reason'=>$this->trans['OVER_SPACE']);
					 break;
				 }
				
				$k = (int) $k;				
				$this->num_files++;

				$file_info = array('tmp_name'=> $_FILES[$this->frm]['tmp_name'][$k] , 'name'=> $_FILES[$this->frm]['name'][$k] , 'size'=> $_FILES[$this->frm]['size'][$k] , 'type'=> $_FILES[$this->frm]['type'][$k] , 'key'=>$k);
				$this->doUpload($file_info);
				$i++;
			}
			
		}
		else{
			$file_info = $_FILES[$this->frm];
			$this->doUpload($file_info);
		}

	}
	function check($fi){
		$ext = $fi['ext'];
		if(! $ext ){
			return $this->trans['INVALID_EXT'];
		}
		if(! array_key_exists($ext , $this->exts) ){
			return $this->trans['INVALID_EXT'];
		}
		if(in_array($ext , $this->fake_exts) ){
			require_once(SP.'/lib/class.gd.php');
			$gd = new GD;
			if(!$gd->is_img($fi['tmp_name'] , $ext) || !$this->checkMime($fi) ){
				return $this->trans['FAKED_IMG'];
			}	
				
		}
		if($fi['size'] > ($this->exts[$ext]*1000) ){
			return $this->trans['INVALID_SIZE'];
		}
		return 'ok';
	}
	function doUpload($fi){
		$check_result = '';
		$fi['ext'] = substr(strrchr( strtolower($fi['name']) , '.') , 1);
		if( ($check_result = $this->check($fi)) == 'ok' ){
			$new_name = @uniqid(rand( 20,5000));
			if(move_uploaded_file($fi['tmp_name'] , $this->folder.'/'.$new_name) ){
				$fi['new_name'] = $new_name;
				$this->onUpload($fi);
			}
			else{
				$this->notDone[] = array('file'=>$fi['name'] , 'reason'=>$this->trans['INTERNAL_ERROR']);
			}
		}
		else{
			$this->notDone[] = array('file'=>$fi['name'] , 'reason'=>$check_result);
			
		}
	}
	function report(){
		$report = array('num'=>$this->num_files , 
					   	'done'=>$this->done,
						'notDone'=>$this->notDone
						);
		return $report;
	}
	function onUpload($file){

	}
	
	function checkMime($file){
		$mimes = array('image/gif', 'image/pjpeg', 'image/x-png', 'image/jpeg', 'image/png');
		$ftype = $file['type'];

		if (in_array($ftype, $mimes)){
			return true;
		}else{
			return false;
		}
	}


}
?>