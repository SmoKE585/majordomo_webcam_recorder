<?php
class webcam_recorder extends module {
	function __construct() {
		$this->name="webcam_recorder";
		$this->title="WEBCam Recorder";
		$this->module_category="<#LANG_SECTION_APPLICATIONS#>";
		$this->version = '2.9';
		$this->checkInstalled();
	}

	function saveParams($data=1) {
		$p=array();
		if (IsSet($this->id)) {
			$p["id"]=$this->id;
		}
		if (IsSet($this->view_mode)) {
			$p["view_mode"]=$this->view_mode;
		}
		if (IsSet($this->edit_mode)) {
			$p["edit_mode"]=$this->edit_mode;
		}
		if (IsSet($this->tab)) {
			$p["tab"]=$this->tab;
		}
		return parent::saveParams($p);
	}

	function getParams() {
		global $id;
		global $mode;
		global $view_mode;
		global $edit_mode;
		global $tab;
		if (isset($id)) {
			$this->id=$id;
		}
		if (isset($mode)) {
			$this->mode=$mode;
		}
		if (isset($view_mode)) {
			$this->view_mode=$view_mode;
		}
		if (isset($edit_mode)) {
			$this->edit_mode=$edit_mode;
		}
		if (isset($tab)) {
			$this->tab=$tab;
		}
	}

	function run() {
		global $session;
		$out=array();
		if ($this->action=='admin') {
			$this->admin($out);
		} else {
			$this->usual($out);
		}
		if (IsSet($this->owner->action)) {
			$out['PARENT_ACTION']=$this->owner->action;
		}
		if (IsSet($this->owner->name)) {
			$out['PARENT_NAME']=$this->owner->name;
		}
		$out['VIEW_MODE']=$this->view_mode;
		$out['EDIT_MODE']=$this->edit_mode;
		$out['MODE']=$this->mode;
		$out['ACTION']=$this->action;
		$this->data=$out;
		$p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
		$this->result=$p->result;
	}

	function admin(&$out) {
		$this->getConfig();
		
		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			//Если винда блочим и выкидываем синий экран
			$out['WIN_USER'] = 1;
			die();
		}
		
		if($this->mode == 'recording' && !empty($this->view_mode)) {
			//Имитация движения
			$this->recording($this->view_mode);
			$this->redirect("?");
		}
		
		if($this->mode == 'fixdberror') {
			SQLExec("ALTER TABLE `webcam_recorder` ADD `CAMTYPE` varchar(10) NOT NULL DEFAULT ''");
			$this->redirect("?");
		}
		
		if($this->mode == 'fixErrorFiles') {
			shell_exec('sudo systemctl stop ffserver');
			//$this->redirect("?");
		}
		
		if($this->mode == 'delete_camera' && !empty($this->view_mode)) {
			//Удаляем файлы
			$data = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE ID = '".dbSafe($this->view_mode)."' ORDER BY ID");
			$this->rmRec($data['PATH'].'/');
			//Удаление камеры
			SQLExec("DELETE FROM `webcam_recorder` WHERE ID = '".dbSafe($this->view_mode)."' LIMIT 1");
			$this->redirect("?");
		}
		
		if($this->mode == 'delete_files' && !empty($this->view_mode)) {
			//Удаляем файлы
			$data = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE ID = '".dbSafe($this->view_mode)."' ORDER BY ID");
			$this->rmRec($data['PATH'].'/');
			$this->redirect("?");
		}
		
		if($this->view_mode == 'add_camera' || $this->mode == 'edit_camera') {
			$out['FFMPEG_VIDEO_DEV'] = $this->generateNoty();
		}
		
		if($this->mode == 'edit_camera' && !empty($this->view_mode)) {
			$dataInDB = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE ID = '".dbSafe($this->view_mode)."' ORDER BY ID");
			$out['PROPERTIES_UPDATE'] = $dataInDB;
		}
		
		if($this->view_mode == 'add_camera_DB' || $this->view_mode == 'edit_camera_DB') {
			global $cameraName;
			global $deviceAddr;
			global $howSec;
			global $codec;
			global $folderPath;
			global $takePhoto;
			global $resol;
			global $kadr;
			global $linked_object1;
			global $linked_property1;
			global $linked_object2;
			global $linked_property2;
			global $reacktOn;
			global $camType;
			global $telegram;
			
			$rand = rand(1000, 9999);
			
			//Простые проверки на дурака
			if($cameraName == '') $cameraName = 'cam'.$rand;
			if($howSec == '') $howSec = 10;
			if($codec == '') $codec = 'libx264';
			if($folderPath == '') $folderPath = $_SERVER['DOCUMENT_ROOT'].'/cms/cached/webcam_recorder/cam'.$rand;
			if($takePhoto == '') $takePhoto = 1;
			if($resol == '') $resol = '640x480';
			if($kadr == '') $kadr = '15';
			
			//Подготовим массив для записи
			$array['CAM_NAME'] = $cameraName;
			$array['DEVICE_ID'] = $deviceAddr;
			$array['SECOND'] = $howSec;
			$array['CODEC'] = $codec;
			$array['PATH'] = $folderPath;
			$array['PHOTO'] = $takePhoto;
			$array['RESOLUTION'] = $resol;
			$array['BITRATE'] = $kadr;
			$array['LINKED_OBJECT1'] = $linked_object1;
			$array['LINKED_PROPERTY1'] = $linked_property1;
			if($linked_object1 != $linked_object2) $array['LINKED_OBJECT2'] = $linked_object2;
			if($linked_property1 != $linked_property2) $array['LINKED_PROPERTY2'] = $linked_property2;
			$array['ADDTIME'] = date('d.m.Y H:i:s');
			$array['REAKTON'] = $reacktOn;
			$array['CAMTYPE'] = $camType;
			$array['TELEGRAMM'] = $telegram;
			
			if ($array['LINKED_OBJECT1'] && $array['LINKED_PROPERTY1']) {
				addLinkedProperty($array['LINKED_OBJECT1'], $array['LINKED_PROPERTY1'], $this->name);
			}
			if ($array['LINKED_OBJECT2'] && $array['LINKED_PROPERTY2'] && $array['LINKED_OBJECT1'].'.'.$array['LINKED_PROPERTY1'] != $array['LINKED_OBJECT2'].'.'.$array['LINKED_PROPERTY2']) {
				addLinkedProperty($array['LINKED_OBJECT2'], $array['LINKED_PROPERTY2'], $this->name);
			}
			
			if($this->view_mode == 'add_camera_DB') SQLInsert('webcam_recorder', $array);
			if($this->view_mode == 'edit_camera_DB') {
				global $camID;
				$dataInDB = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE ID = '".dbSafe($camID)."' ORDER BY ID");
				
				//Если юзвер поменял свойство, то нужно перепривязать
				if($dataInDB['LINKED_OBJECT1'] != $array['LINKED_OBJECT1'] || $dataInDB['LINKED_PROPERTY1'] != $array['LINKED_PROPERTY1']) {
					removeLinkedProperty($dataInDB['LINKED_OBJECT1'], $dataInDB['LINKED_PROPERTY1'], $this->name);
				}
				if($dataInDB['LINKED_OBJECT2'] != $array['LINKED_OBJECT2'] || $dataInDB['LINKED_PROPERTY2'] != $array['LINKED_PROPERTY2']) {
					removeLinkedProperty($dataInDB['LINKED_OBJECT2'], $dataInDB['LINKED_PROPERTY2'], $this->name);
				}
				
				if ($array['LINKED_PROPERTY1'] == NULL) {
					$array['LINKED_PROPERTY1'] = "";
				}
				
				$array['ID'] = $dataInDB['ID'];
				SQLUpdate('webcam_recorder', $array);
			}
			
			$this->createFolder($folderPath.'/');
			
			$this->config['EMPTY_CAMS'] = 1;
			$this->saveConfig();
			
			$this->redirect("?");
		}
		
		//Выгружаем массив камер
		$dataInDB = SQLSelect("SELECT * FROM `webcam_recorder` ORDER BY ID");
		$out['PROPERTIES'] = $dataInDB;
		foreach($dataInDB as $key => $value) {
			$scan = scandir($dataInDB[$key]['PATH'], 1);
			$out['PROPERTIES'][$key]['TOTAL_VIDEO'] = count($scan)-3;
			$out['PROPERTIES'][$key]['LAST_VIDEO'] = $scan[2];
			$out['PROPERTIES'][$key]['PATH_PREVIEW'] = substr($dataInDB[$key]['PATH'], mb_strlen($_SERVER['DOCUMENT_ROOT']));
			
			switch($dataInDB[$key]["SECOND"]) {
				case '5':
					$durationRecord = '00:00:05';
					break;
				case '10':
					$durationRecord = '00:00:10';
					break;
				case '15':
					$durationRecord = '00:00:15';
					break;
				case '25':
					$durationRecord = '00:00:25';
					break;
				case '40':
					$durationRecord = '00:00:40';
					break;
				case '60':
					$durationRecord = '00:01:00';
					break;
				case '120':
					$durationRecord = '00:02:00';
					break;
				case '600':
					$durationRecord = '00:05:00';
					break;
			}
			
			if($dataInDB[$key]['CAMTYPE'] == 'rtsp') {
				$camType = ' ';
			} else {
				$camType = ' -f video4linux2 ';
			}
			
			$out['PROPERTIES'][$key]['FFMPEG_STRING_GENERATE'] = 'sudo timeout -s INT 60s ffmpeg -y'.$camType.'-i '.$dataInDB[$key]["DEVICE_ID"].' -t '.$durationRecord.' -f mp4 -r '.$dataInDB[$key]['BITRATE'].' -s '.$dataInDB[$key]["RESOLUTION"].' -c:v '.$dataInDB[$key]['CODEC'].' -pix_fmt yuv420p '.$dataInDB[$key]['PATH'].'/'.date('dmY_His', time()).'_'.rand(1000, 9999).'/video.mp4';
			
			//Узнаем размер папки
			$out['PROPERTIES'][$key]['PATH_SIZE'] = round($this->getFilesSize($dataInDB[$key]['PATH'])/1000000, 2);
			$out['PROPERTIES'][$key]['CAMTYPE'] = $dataInDB[$key]['CAMTYPE'];
		}

		//Флаг на то, есть ли камеры
		$out['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'];
		$out['EMPTY_CAMS'] = $this->config['EMPTY_CAMS'];
		$out['VERSION_MODULE'] = $this->version;
		$out['FFMPEG_STATUS'] = (shell_exec('ffmpeg -h')) ? 1 : 0;
	}
	
	function telegram($type, $src) {
		if(!file_exists(DIR_MODULES . 'telegram/telegram.class.php')) {
			return 'No install module TELEGRAM';
		} else {
			if($type == 'photo') {
				include_once(DIR_MODULES . 'telegram/telegram.class.php');
				$telegram = new telegram();
				$telegram->sendImageToAll($src);
			}
			if($type == 'video') {
				include_once(DIR_MODULES . 'telegram/telegram.class.php');
				$telegram = new telegram();
				$telegram->sendVideoToAll($src);
			}
		}
	}
	
	function generateNoty() {
		$arrayDevice = explode('crw-', shell_exec('ls -l /dev/ | grep video'));
			
		foreach($arrayDevice as $value) {
			if($value != '') $generateHint .= 'crw-'.$value.'<br>';
		}
		
		return $generateHint;
	}
	
	function rmRec($path) {
		if (is_file($path)) return unlink($path);
		if (is_dir($path)) {
			foreach(scandir($path) as $p) if (($p!='.') && ($p!='..'))
			$this->rmRec($path.DIRECTORY_SEPARATOR.$p);
			return rmdir($path); 
		}
		return false;
	}
		
	function propertySetHandle($object, $property, $value) {
		$this->getConfig();
		$properties = SQLSelect("SELECT * FROM `webcam_recorder` WHERE (LINKED_OBJECT1='" . DBSafe($object) . "' AND LINKED_PROPERTY1='" . DBSafe($property) . "') OR (LINKED_OBJECT2='" . DBSafe($object) . "' AND LINKED_PROPERTY2='" . DBSafe($property) . "')");
		$total = count($properties);
		
		if ($total) {
			for ($i = 0; $i < $total; $i++) {
				if($value == $properties[$i]['REAKTON'] || $properties[$i]['REAKTON'] == 2) $this->recording($properties[$i]['ID']);
			}
		}
	}
	
	function createFolder($path) {
		//Проверим есть ли папка с сегоднешней датой
		if(!is_dir($path)) {
			mkdir($path, 0755, true);
		} 
		return !is_dir($path.'/');
	}
	
	function getFilesSize($path) {
		$fileSize = 0;
		$dir = scandir($path);

		foreach($dir as $file)
		{
			if (($file!='.') && ($file!='..'))
				if(is_dir($path . '/' . $file))
					$fileSize += $this->getFilesSize($path.'/'.$file);
				else
					$fileSize += filesize($path . '/' . $file);
		}
		
		return $fileSize;
	}
	
	function recording($camID) {
		$data = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE ID = '".dbSafe($camID)."' ORDER BY ID");
		$dateTimeName = date('dmY_His', time()).'_'.rand(1000, 9999);
		
		$this->createFolder($data['PATH'].'/'.$dateTimeName.'/');
		
		switch($data["SECOND"]) {
			case '5':
				$durationRecord = '00:00:05';
				break;
			case '10':
				$durationRecord = '00:00:10';
				break;
			case '15':
				$durationRecord = '00:00:15';
				break;
			case '25':
				$durationRecord = '00:00:25';
				break;
			case '40':
				$durationRecord = '00:00:40';
				break;
			case '60':
				$durationRecord = '00:01:00';
				break;
			case '120':
				$durationRecord = '00:02:00';
				break;
			case '600':
				$durationRecord = '00:05:00';
				break;
		}
		
		//Генерируем команду *nix
		if($data["CAMTYPE"] == 'rtsp') {
			$camType = '-rtsp_transport tcp -i '.$data["DEVICE_ID"];
		} else {
			$camType = '-y -f video4linux2 -i '.$data["DEVICE_ID"];
		}
		
		$nixCommand_Video = 'sudo timeout -s INT 60s ffmpeg -hide_banner -loglevel panic '.$camType.' -t '.$durationRecord.' -f mp4 -r '.$data['BITRATE'].' -s '.$data["RESOLUTION"].' -c:v '.$data['CODEC'].' -pix_fmt yuv420p '.$data['PATH'].'/'.$dateTimeName.'/video.mp4';
		if($data["PHOTO"] == 1) {
			$nixCommand_Photo = ';sudo timeout -s INT 30s ffmpeg -hide_banner -loglevel panic -i '.$data['PATH'].'/'.$dateTimeName.'/video.mp4 -an -ss 00:00:02 -r 1 -vframes 1 -s '.$data["RESOLUTION"].' -y -f mjpeg '.$data['PATH'].'/'.$dateTimeName.'/photo.jpg';
			if(!is_dir($data['PATH'].'/last/')) {
				$this->createFolder($data['PATH'].'/last/');
			}
		}

		//Кидаем в шел
		shell_exec($nixCommand_Video.$nixCommand_Photo);
		//shell_exec('sudo timeout -s INT 180s systemctl stop ffserver');

		if($data["TELEGRAMM"] == 'photo') {
			$this->telegram('photo', substr($data['PATH'], mb_strlen($_SERVER['DOCUMENT_ROOT'])).'/'.$dateTimeName.'/photo.jpg');
		}
		
		if($data["TELEGRAMM"] == 'video') {
			$this->telegram('video', substr($data['PATH'], mb_strlen($_SERVER['DOCUMENT_ROOT'])).'/'.$dateTimeName.'/video.mp4');
		}
	}
	
	function usual(&$out) {
		$this->admin($out);
		
		if($this->mode == 'menu') {
			$out['TYPE_SHOW'] = 'menu';
		}
		
		if($this->mode = 'arhive') {
			$out['TYPE_SHOW'] = 'arhive';
			$out['SHOW_CONTROLS'] = '1';
			$out['SHOW_LAST'] = '0';
			
			foreach(explode(',', $this->view_mode) as $value) {
				if($value == 'hidecontrols') {
					$out['SHOW_CONTROLS'] = '0';
					$out['FEWKEW'] .= $value.' - 0';
				}
				if($value == 'showlast') {
					$out['SHOW_LAST'] = '1';
					$out['FEWKEW'] .= $value.' - 1';
				}
			}
			
			//Выгружаем массив камер
			$dataInDB = SQLSelect("SELECT * FROM `webcam_recorder` ORDER BY ID");
			$out['PROPERTIES'] = $dataInDB;
			
			global $type; 
			global $date; 
			global $camid; 
			global $showCol;
			global $iteration;
			global $arrayData;
			global $countArray;
			global $scanFiles;
			
			$this->type = strip_tags($_GET['type']);
			$this->date = strip_tags($_GET['date']);
			$this->camid = (int) strip_tags($_GET['camid']);
			$this->showCol = (int) strip_tags($_GET['showCol']);
			
			$this->date = explode(".", strip_tags($this->date));
			$this->date = $this->date[0].$this->date[1].$this->date[2];
			
			//Если запрашиваем скрипт массив данных в JSON
			if($type == 'json') {
				$data = SQLSelectOne("SELECT * FROM `webcam_recorder` WHERE `ID` = '".dbSafe($this->camid)."' ORDER BY ID");
				$this->scanFiles = scandir($data['PATH'], 1);
				//var_dump($data);
				$this->arrayData = [];
				$this->iteration = 0;
				$this->countArray = count($this->scanFiles);

				foreach($this->scanFiles as $key => $value) {
					$dateSrav = explode('_', $this->scanFiles[$key]);
					if($dateSrav[0] == $this->date) {
						array_push($this->arrayData, substr($data['PATH'], mb_strlen($_SERVER['DOCUMENT_ROOT'])).'/'.$this->scanFiles[$key].'/');
						//+ итерация цикла
						$this->iteration++;
					}
					//- счет, чтобы убрать папки "наверх"
					$this->countArray--;
					if($this->iteration >= $this->showCol) break;
					if($this->countArray <= 2) break;
				}

				echo json_encode($this->arrayData);
			}
		}
	}

	function install($data='') {
		parent::install();
	}
	
	function DeleteLinkedProperties() {
		$properties = SQLSelect("SELECT * FROM `webcam_recorder` WHERE (LINKED_OBJECT1 != '' AND LINKED_PROPERTY1 != '') OR (LINKED_OBJECT2 != '' AND LINKED_PROPERTY2 != '')");

		if (!empty($properties)) {
			foreach ($properties as $prop) {
				if($prop['LINKED_OBJECT1'] && $prop['LINKED_PROPERTY1']) removeLinkedProperty($prop['LINKED_OBJECT1'], $prop['LINKED_PROPERTY1'], $this->name);
				if($prop['LINKED_OBJECT2'] && $prop['LINKED_PROPERTY2']) removeLinkedProperty($prop['LINKED_OBJECT2'], $prop['LINKED_PROPERTY2'], $this->name);
			}
		}
	}
	
	function uninstall() {
		//Удалим записи
		$dataInDB = SQLSelect("SELECT * FROM `webcam_recorder` ORDER BY ID");
		
		foreach($dataInDB as $key => $value) {
			$this->rmRec($dataInDB[$key]['PATH']);
		}
		
		$this->DeleteLinkedProperties();
		
		SQLExec('DROP TABLE IF EXISTS webcam_recorder');
	}	
	
	function dbInstall($data = '') {
		$data = <<<EOD
webcam_recorder: ID int(10) unsigned NOT NULL auto_increment
webcam_recorder: CAM_NAME varchar(100) NOT NULL DEFAULT ''
webcam_recorder: DEVICE_ID varchar(100) NOT NULL DEFAULT ''
webcam_recorder: SECOND varchar(100) NOT NULL DEFAULT ''
webcam_recorder: CODEC varchar(100) NOT NULL DEFAULT ''
webcam_recorder: PATH varchar(100) NOT NULL DEFAULT ''
webcam_recorder: PHOTO varchar(100) NOT NULL DEFAULT ''
webcam_recorder: RESOLUTION varchar(100) NOT NULL DEFAULT ''
webcam_recorder: BITRATE varchar(100) NOT NULL DEFAULT ''
webcam_recorder: LINKED_OBJECT1 varchar(255) NOT NULL DEFAULT ''
webcam_recorder: LINKED_PROPERTY1 varchar(255) NOT NULL DEFAULT ''
webcam_recorder: LINKED_OBJECT2 varchar(255) NOT NULL DEFAULT ''
webcam_recorder: LINKED_PROPERTY2 varchar(255) NOT NULL DEFAULT ''
webcam_recorder: REAKTON varchar(255) NOT NULL DEFAULT ''
webcam_recorder: TELEGRAMM varchar(255) NOT NULL DEFAULT ''
webcam_recorder: CAMTYPE varchar(10) NOT NULL DEFAULT ''
webcam_recorder: ADDTIME varchar(255) NOT NULL DEFAULT ''
	
EOD;
		parent::dbInstall($data);
	}
}
