<?

require_once(__DIR__ . "/../libs/arlo.php");
require_once(__DIR__ . "/../libs/logging.php");

class ArloModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		$this->RegisterPropertyString("email", "");
		$this->RegisterPropertyString("password", "");
		$this->RegisterPropertyInteger("RootCategoryId", 0);
    }
   
    public function ApplyChanges(){
        parent::ApplyChanges();
    }
	
	public function ForwardData($JSONString){
		$receivedData = json_decode($JSONString)->Buffer;
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		//$log->LogMessage("Received data from child: ".$JSONString); 
		
		switch(strtolower($receivedData->Instruction)) {
			case "cloudcommand":
				return $this->ExecuteCloudCommand($receivedData->Command, $receivedData->Parameters);
				break;
		}
	}
	
	public function GetDevices() {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to retrieve all devices from the Arlo cloud..."); 
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to log on to the Arlo cloud"); 
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			$devices = $arlo->GetAllDevices();
			if($devices===false)
				$log->LogMessage("Failed to retrieve all devices"); 
			else
				$log->LogMessage("Successfully retrieved all devices"); 
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $devices;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
			
		return false;
	}
		
	public function UpdateAllDevices() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_GetName($this->InstanceID));
		$log->LogMessage("Preparing to recreate all registered devices in Symcon..."); 
		
		$rootCategoryId = $this->ReadPropertyInteger("RootCategoryId");
		if($rootCategoryId==0) {
			$log->LogMessage("Root category is not set in the configuration. Aborting...");
			return;
		}
				
		$result = $this->GetDevices();
		if($result===false) {
			$log->LogMessage("Failed to retrieve all devices from the Arlo cloud. Aborting...");
			return;
		}
		
		// Clean up old devices
		$log->LogMessage("Deleting existing instances in Symcon"); 
		$cameras = IPS_GetInstanceListByModuleID("{2B472806-C471-4104-9B61-EA2F17588A33}");
		$basestations = IPS_GetInstanceListByModuleID("{4DBB8C7E-FE5F-40DE-B9CB-DB7B54EBCDAA}");
		
		$log->LogMessage("Deleting cameras...");
		for($x=0;$x<count($cameras);$x++) {
			$this->DeleteObject($cameras[$x]);
		}
		
		$log->LogMessage("Deleting basestations...");
		for($x=0;$x<count($basestations);$x++) {
			$this->DeleteObject($basestations[$x]);
		}
		
		$cameras = $result['cameras'];
		$basestations = $result['basestations'];
				
		$log->LogMessage("Recreating all basestations and cameras");
		
		for($x=0;$x<count($basestations);$x++) {
			$log->LogMessage("Creating basestation ".$basestations[$x]->deviceName);
			$basestationInsId = IPS_CreateInstance("{4DBB8C7E-FE5F-40DE-B9CB-DB7B54EBCDAA}");
			if($basestationInsId>0) {
				IPS_SetName($basestationInsId, $basestations[$x]->deviceName); 
				IPS_SetParent($basestationInsId, $rootCategoryId);
				IPS_SetProperty($basestationInsId, "ArloBasestationName", $basestations[$x]->deviceName);
				IPS_SetProperty($basestationInsId, "ArloBasestationDeviceId", $basestations[$x]->deviceId);
								
				IPS_ApplyChanges($basestationInsId); 
				
				for($y=0;$y<count($cameras);$y++) {
					if($basestations[$x]->deviceId==$cameras[$y]->parentId) {
						$log->LogMessage("Creating camera ".$cameras[$y]->deviceName." for basestation ".$basestations[$x]->deviceName);
						$cameraInsId = IPS_CreateInstance("{2B472806-C471-4104-9B61-EA2F17588A33}");
						if($cameraInsId>0){
							IPS_SetName($cameraInsId, $cameras[$y]->deviceName); 
							IPS_SetParent($cameraInsId, $basestationInsId);	
							IPS_SetProperty($cameraInsId, "ArloCameraName", $cameras[$y]->deviceName);
							IPS_SetProperty($cameraInsId, "ArloCameraDeviceId", $cameras[$y]->deviceId);
							
							IPS_ApplyChanges($cameraInsId);
							
							$log->LogMessage("Creating image for camera ".$cameras[$y]->deviceName);
							$imgId = $this->CreateMediaByName($cameraInsId, "Snapshot", 1, $cameras[$y]->deviceId);
							$filename = __DIR__ . "/../../../media/".$cameras[$y]->deviceName.".jpg";
							if($imgId>0 && $this->DownloadURL($cameras[$y]->presignedLastImageUrl, $filename)) {
								IPS_SetMediaFile($imgId, $filename, false);
							} else
								$log->LogMessage("Failed to create the image instance or download the image");
						} else
							$log->LogMessage("Failed to create ".$cameras[$y]->deviceName);
					}
				}
			} else
				$log->LogMessage("Failed to create ".$basestations[$x]->deviceName);
		}
	}

	private function ExecuteCloudCommand($Command, $Parameters) {
		$data = null;
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		//$log->LogMessage("ExecuteCloudCommands Parameters ".print_r($Parameters, true)); 
		
		$returnedResult = array('Success'=>false);
		
		switch(strtolower($Command)) {
			case "takesnapshot":
				$returnedResult = array('Success'=>$this->TakeSnapshot($Parameters->CameraName));
				break;
			case "getlibrary":
				$result = $this->GetLibrary($Parameters->FromDate, $Parameters->ToDate);
				if($result!==false)
					$returnedResult = array('Success'=>true, 'Data'=>$result);
				else
					$returnedResult = array('Success'=>false, 'Data'=>array());
				break;
			case "deletelibraryitem":
				$returnedResult = array('Success'=>$this->DeleteLibraryItem($Parameters));
				break;
			case "downloadurl":
				$returnedResult = array('Success'=>$this->DownloadURL($Parameters->Url, $Parameters->Filename));
				break;
			case "arm":
				$returnedResult = array('Success'=>$this->Arm($Parameters->BasestationName));
				break;
			case "disarm":
				$returnedResult = array('Success'=>$this->Disarm($Parameters->BasestationName));
				break;
			case "getdevicenamebyid":
				$result = $this->GetDeviceNameById($Parameters->DeviceId);
				if($result!==false)
					$returnedResult = array('Success'=>true, 'Data'=>$result);
				else
					$returnedResult = array('Success'=>false);
				break;
		}
		
		return json_encode($returnedResult);
	}
	
	private function GetDeviceIdByName(string $DeviceName){
		$result = $this->GetDevices();
		if($result!==false) {
			$cameras = $result['cameras'];
			$basestations = $result['basestations'];
			
			for($x=0;$x<sizeof($cameras);$x++) {
				if($cameras[$x]->deviceName == $DeviceName)
					return $cameras[$x]->deviceId;
			}
			
			for($x=0;$x<sizeof($basestations);$x++) {
				if($basestations[$x]->deviceName == $DeviceName)
					return $basestations[$x]->deviceId;
			}
		}
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_GetName($this->InstanceID));
		$log->LogMessage("Unable to find the device with the name ".$DeviceName); 
		return false;
	}
	
	private function GetDeviceNameById(int $DeviceId){
		$result = $this->GetDevices();
		if($result!==false) {
			$cameras = $result['cameras'];
			$basestations = $result['basestations'];
			
			for($x=0;$x<sizeof($cameras);$x++) {
				if($cameras[$x]->deviceId == $DeviceId)
					return $cameras[$x]->deviceName;
			}
			
			for($x=0;$x<sizeof($basestations);$x++) {
				if($basestations[$x]->deviceId == $DeviceId)
					return $basestations[$x]->deviceName;
			}
		}
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_GetName($this->InstanceID));
		$log->LogMessage("Unable to find the device with the id ".$DeviceId); 
		return false;
	}

	
	private function TakeSnapshot (string $CameraName) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to take a snapshot..."); 
				
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to log on to the Arlo cloud"); 
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			if($arlo->StartStream($CameraName)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to start the cameras recording"); 
				return false;
			} else
				$log->LogMessage("Started the cameras recording"); 
			
			if($arlo->TakeSnapshot($CameraName)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to take a snapshot"); 
				return false;
			} else
				$log->LogMessage("The snapshot was taken successfully. Stopping the recording..."); 
			
			$arlo->StopStream($CameraName);
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
					
			return true;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
		
		return false;
	}
	
		
	private function GetLibrary(string $FromYYYYMMDD, string $ToYYYYMMDD) {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to retrieve the library from the Arlo cloud..."); 
		
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to log on to the Arlo cloud");
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			$library = $arlo->GetLibrary($FromYYYYMMDD, $ToYYYYMMDD);
			if($library===false)
				$log->LogMessage("Failed to retrieve the library from the Arlo cloud");
			else
				$log->LogMessage("Successfully retrieved the library from the Arlo cloud");
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $library;
		} else 
			$log->LogMessage("Email address and/or password is not set"); 
		return false;
	}
	
	private function DeleteLibraryItem($LibraryItem) {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to delete an item the Arlo cloud library..."); 
		
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$arlo->Logout();
				$log->LogMessage("Failed to log on to the Arlo cloud");
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			$result = $arlo->DeleteLibraryItem($LibraryItem);
			if($result===false)
				$log->LogMessage("Failed to delete the item");
			else
				$log->LogMessage("Successfully deleted the item from the Arlo cloud");
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $result;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
		
		return false;
	}
	
	private function DownloadURL(string $Url, string $Filename) {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to downloaded an image from the Arlo cloud");
		
		$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
		
		$result = $arlo->DownloadURL($Url, $Filename);
		if($result)
			$log->LogMessage("Successfully downloaded the image");
		else
			$log->LogMessage("Failed to download the image");
		
		return $result;
	}

	private function Arm(string $BasestationName) {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to arm the basestation ".$BasestationName);
		
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$log->LogMessage("Failed to log on to the Arlo cloud"); 
				$arlo->Logout();
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			$result = $arlo->Arm($BasestationName);
			if($result)
				$log->LogMessage("Successfully armed the basestation"); 
			else
				$log->LogMessage("Failed to arm the basestation"); 
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $result;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
		
		return false;
	}  			
	
	private function Disarm(string $BasestationName) {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to disarm the basestation ".$BasestationName);

		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo($this->ReadPropertyBoolean("Log"));
			if ($arlo->Init($email,$password)===false) {
				$log->LogMessage("Failed to log on to the Arlo cloud"); 
				$arlo->Logout();
				return false;
			} else
				$log->LogMessage("Logged on to the Arlo cloud"); 
			
			$result = $arlo->Disarm($BasestationName);
			if($result)
				$log->LogMessage("Successfully disarmed the basestation"); 
			else
				$log->LogMessage("Failed to disarm the basestation"); 
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $result;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
		
		return false;
	} 
		
	private function DeleteSingleObject($ObjectId) {
		$object = IPS_GetObject($ObjectId);
		
		switch($object['ObjectType']){
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
	
	private function DeleteObject($ObjectId) {
		$childrenIds = IPS_GetChildrenIDs($ObjectId);
		for($x=0;$x<count($childrenIds);$x++) {
			$object = IPS_GetObject($childrenIds[$x]);
			if($object['HasChildren'])
				$this->DeleteObject($childrenIds[$x]);
			else {
				$this->DeleteSingleObject($childrenIds[$x]);
			}
		}
		$this->DeleteSingleObject($ObjectId);
	}

	private function CreateMediaByName($Id, $MediaName, $Type, $CameraId){
		$mId = @IPS_GetMediaIDByName($Name, $Id);
		if($mId === false) {
		  $mId = IPS_CreateMedia($Type);
		  IPS_SetParent($mId, $Id);
		  IPS_SetName($mId, $MediaName);
		  IPS_SetInfo($mId, "This media object was created by the Arlo module");
		  IPS_SetIdent($mId, $CameraId."Snapshot");
		}
		return $mId;
	}	
}

?>
