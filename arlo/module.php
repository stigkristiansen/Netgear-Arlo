<?php

require_once(__DIR__ . "/../libs/arlo.php");
require_once(__DIR__ . "/../libs/logging.php");

class ArloModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		$this->RegisterPropertyString("email", "");
		$this->RegisterPropertyString("password", "");
    }
   
    public function ApplyChanges(){
        parent::ApplyChanges();
    }
	
	public function TakeSnapshot (string $CameraName) {
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
				$log->LogMessage("Successfully retrieved all devices "); 
			
			$arlo->Logout();
			$log->LogMessage("Logged out from the Arlo cloud");
			
			return $devices;
		} else
			$log->LogMessage("Email address and/or password is not set"); 
			
		return false;
	}
		
	public function UpdateAllDevices() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Preparing to recreate all registered devices in Symcon..."); 
		
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
			IPS_SetName($basestationInsId, $basestations[$x]->deviceName); 
			IPS_SetProperty($basestationInsId, "ArloModuleInstanceId", $this->InstanceID);
			IPS_SetParent($basestationInsId, $this->InstanceID);
			
			IPS_ApplyChanges($basestationInsId); 
			
			for($y=0;$y<count($cameras);$y++) {
				if($basestations[$x]->deviceId==$cameras[$y]->parentId) {
					$log->LogMessage("Creating camera ".$cameras[$y]->deviceName." for basestation ".$basestations[$x]->deviceName);
					$cameraInsId = IPS_CreateInstance("{2B472806-C471-4104-9B61-EA2F17588A33}");
					IPS_SetName($cameraInsId, $cameras[$y]->deviceName); 
					IPS_SetParent($cameraInsId, $basestationInsId);	
					IPS_SetProperty($cameraInsId, "ArloModuleInstanceId", $this->InstanceID);
					IPS_SetProperty($cameraInsId, "ArloCameraName", $cameras[$y]->deviceName);
					IPS_SetProperty($cameraInsId, "ArloCameraDeviceId", $cameras[$y]->deviceId);
					
					IPS_ApplyChanges($cameraInsId);
					
					$log->LogMessage("Creating image for camera ".$cameras[$y]->deviceName);
					$imgId = $this->CreateMediaByName($cameraInsId, "Snapshot", 1, $cameras[$y]->deviceName);
					$filename = __DIR__ . "/../../../media/".$cameras[$y]->deviceName.".jpg";
					if($this->DownloadURL($cameras[$y]->presignedLastImageUrl, $filename))
						IPS_SetMediaFile($imgId, $filename, false);
				}
			}
		}
	}
		
	public function GetLibrary(string $FromYYYYMMDD, string $ToYYYYMMDD) {
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
	
	public function DeleteLibraryItem($LibraryItem) {
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
	
	public function DownloadURL(string $Url, string $Filename) {
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

	public function Arm(string $BasestationName) {
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
	
	public function Disarm(string $BasestationName) {
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

	private function CreateMediaByName($Id, $MediaName, $Type, $CameraName){
		$mId = @IPS_GetMediaIDByName($Name, $Id);
		if($mId === false) {
		  $mId = IPS_CreateMedia($Type);
		  IPS_SetParent($mId, $Id);
		  IPS_SetName($mId, $MediaName);
		  IPS_SetInfo($mId, "This media object was created by the Arlo module");
		  IPS_SetIdent($mId, $CameraName."Snapshot");
		}
		return $mId;
	}	
}


