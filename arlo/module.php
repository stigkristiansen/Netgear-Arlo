<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		$this->RegisterPropertyString("email", "");
		$this->RegisterPropertyString("password", "");
    }
   
    public function ApplyChanges(){
        parent::ApplyChanges();
		
		//$this->UpdateAllDevices();
				
    }
	
	public function TakeSnapshot (string $CameraName) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				return false;
			}
			if($arlo->StartStream($CameraName)===false) {
				$arlo->Logout();
				return false;
			}
			if($arlo->TakeSnapshot($CameraName)===false) {
				$arlo->Logout();
				return false;
			}
			
			$arlo->StopStream($CameraName);
			$arlo->Logout();
			
			return true;
		} else
			return false;
	}
	
	public function GetDevices() {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				return false;
			}
			
			$devices = $arlo->GetAllDevices();
			$arlo->Logout();
			
			return $devices;
		} else
			return false;
	}
	
		
	public function UpdateAllDevices() {
		$result = $this->GetDevices();
		
		if($result===false)
			return;
		
		// Cleanup old devices
		$cameras = IPS_GetInstanceListByModuleID("{2B472806-C471-4104-9B61-EA2F17588A33}");
		$basestations = IPS_GetInstanceListByModuleID("{4DBB8C7E-FE5F-40DE-B9CB-DB7B54EBCDAA}");
		
		for($x=0;$x<count($cameras);$x++) {
			$this->DeleteObject($cameras[$x]);
		}
		
		for($x=0;$x<count($basestations);$x++) {
			$this->DeleteObject($basestations[$x]);
		}
		
		$cameras = $result['cameras'];
		$basestations = $result['basestations'];
				
		for($x=0;$x<count($basestations);$x++) {
			$basestationInsId = IPS_CreateInstance("{4DBB8C7E-FE5F-40DE-B9CB-DB7B54EBCDAA}");
			IPS_SetName($basestationInsId, $basestations[$x]->deviceName); 
			IPS_SetParent($basestationInsId, $this->InstanceID);
			IPS_ApplyChanges($basestationInsId); 
			
			for($y=0;$y<count($cameras);$y++) {
				if($basestations[$x]->deviceId==$cameras[$y]->parentId) {
					$cameraInsId = IPS_CreateInstance("{2B472806-C471-4104-9B61-EA2F17588A33}");
					IPS_SetName($cameraInsId, $cameras[$y]->deviceName); 
					IPS_SetParent($cameraInsId, $basestationInsId);	
					IPS_ApplyChanges($cameraInsId);
				}
			}
		}
	}
	
	public function GetLibrary (string $FromYYYYMMDD, string $ToYYYYMMDD) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				return false;
			}
			$library = $arlo->GetLibrary($FromYYYYMMDD, $ToYYYYMMDD);
			$arlo->Logout();
			
			return $library;
		} else
			return false;
	}
	
	public function DownloadURL(string $Url, string $Filename) {
		$arlo = new Arlo();
		return $arlo->DownloadURL($Url, $Filename);
	}

	public function Arm(string $BasestationName) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				return false;
			}		
			$result = $arlo->Arm($BasestationName);
			$arlo->Logout();
			
			return $result;
		}
	}  			
	public function Disarm(string $BasestationName) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				return false;
			}
			$result = $arlo->Disarm($BasestationName);
			$arlo->Logout();
			
			return $result;
		}
	} 

	function DeleteSingleObject($ObjectId) {
		$object = IPS_GetObject($ObjectId);
		
		switch $object['ObjectType'] {
			case 0: 
				IPS_DeleteCategory($ObjectId);
				break;
			case 1:
				IPS_DeleteInstance($ObjectId);
				break;
			case 2:
				IPS_DeleteVariable($ObjectId);
				break;
			case 3:
				IPS_DeleteScript($ObjectId, true);
				break;
			case 4:
				IPS_DeleteEvent($ObjectId);
				break;
			case 5:
				IPS_DeleteMedia($ObjectId, true);
				break;
			case 6:
				IPS_DeleteLink($ObjectId);
				break;
		}
	}
	
	function DeleteObject($ObjectId) {
		$childrenIds = IPS_GetChildrenIDs($ObjectId);
		for($x=0;$x<count($childrenIds);$x++) {
			$object = IPS_GetObject($childrenIds[$x]);
			if($object['HasChildren'])
				$this->DeleteObject($childrenIds[$x]);
			else {
				$this->DeleteSingleObject($childrenIds[$x])
			}
		}
		$this->DeleteSingleObject($ObjectId);
	}
	
}

?>
