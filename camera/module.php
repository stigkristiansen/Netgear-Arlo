<?	

require_once(__DIR__ . "/../libs/arlo.php");
require_once(__DIR__ . "/../libs/logging.php");

class ArloCameraModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean("Log", true);
		$this->RegisterPropertyBoolean("DeleteImage", true);
		$this->RegisterPropertyString("ArloCameraName", "");
		$this->RegisterPropertyString("ArloCameraDeviceId", "");
		$this->RegisterPropertyBoolean("ScheduleSnapshot", false);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
		
		$this->ConnectParent("{10113AE2-5247-439C-B386-B65B0DC32B12}");
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$log->LogMessage("Checking if the snapshot script exists..."); 
		$scriptId = @IPS_GetObjectIDByIdent("scriptsnapshot", $this->InstanceID);
		if($scriptId===false) {
			$log->LogMessage("The script did not exist. Creating the script..."); 
			$script = "<? NAC_TakeSnapshot(".$this->InstanceID."); ?>";
			$scriptId = $this->RegisterScript("scriptsnapshot", "_Snapshot", $script, 0);
		} else
			$log->LogMessage("The script existed"); 
			
		$log->LogMessage("Checking if the scripts scheduled event exists..."); 
		$eventId = @IPS_GetObjectIDByIdent("eventsnapshot", $scriptId);
		if($eventId===false) {
			$log->LogMessage("The event did not exist. Creating the event..."); 
			$eventId = IPS_CreateEvent(1);
			IPS_SetParent($eventId, $scriptId);
			IPS_SetIdent($eventId, "eventsnapshot");
			IPS_SetEventCyclicTimeFrom($eventId , 12 , 0, 0) ;
		} else
			$log->LogMessage("The event existed"); 
		
		$log->LogMessage("Setting the events active state to configured value"); 
		IPS_SetEventActive($eventId,$this->ReadPropertyBoolean("ScheduleSnapshot")); 
    }
	
	Public function RefreshDeviceName() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Preparing a refresh the Arlo Camera Name..."); 
		
		$parentInstanceId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		
		if($parentInstanceId>0) {
			$deviceName = NA_GetDeviceNameById($parentInstanceId, $this->ReadPropertyString("ArloCameraDeviceId"));
			if($deviceName!==false) {
				IPS_SetProperty($this->InstanceID, "ArloCameraName", $deviceName);		
				IPS_ApplyChanges($this->InstanceID);
				$log->LogMessage("The name has been updated");
			} else
				$log->LogMessage("Did not find the Arlo camera with the id ". $this->ReadPropertyString("ArloCameraDeviceId"));
		} else
			$log->LogMessage("This camera instance is not connected to a parent instance!");
	}
	
	private function SendCommandToParent($Command, $Parameters){
		$data = array("Instruction"=>"Cloud", "Command"=>$Command, "Parameters"=>$Parameters);
		$result = json_decode($this->SendDataToParent(json_encode(Array("DataID" => "{0F113ADC-F4F1-47F7-A0B2-B95D6AE0A77A}", "Buffer" => $data))),true);
		
		if(isset($result['Data']))
			return $result['Data'];
		else
			return $result('Success');
	}
	
	public function TakeSnapshotNew() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$parentInstanceId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		$cameraName = $this->ReadPropertyString("ArloCameraName");
		$cameraDeviceId = $this->ReadPropertyString("ArloCameraDeviceId");
		
		$log->LogMessage("Preparing a snapshot using camera \"".$cameraName."\""); 
		
		if($parentInstanceId>0) {
			$now = microtime(true);
			$toDayDate = Date('Ymd', $now);
			$now*=1000;
						
			$this->SendCommandToParent("TakeSnapshot",array("CameraName"=>$cameraName));
			
			$log->LogMessage("Fetching the library from the Arlo cloud and searching for the last snapshot...");
			
			$library = $this->SendCommandToParent("GetLibrary",array("FromDate"=>$toDayDate, "ToDate"=>$toDayDate));
						
			//$library = NA_GetLibrary($parentInstanceId, $toDayDate, $toDayDate);
							
			for($x=0;$x<Count($library);$x++) {
				$lastModified = $library[$x]->lastModified;
				if($library[$x]->deviceId==$cameraDeviceId && $lastModified > $now) {
					$item = $library[$x];
					break;
				}
			}
			
			if(isset($item)) {
				$log->LogMessage("The snapshot was found in the library. Downloading...");
				$filename = __DIR__ . "/../../../media/".$cameraName.".jpg";
				
				if(NA_DownloadURL($parentInstanceId, $item->presignedContentUrl, $filename)) {
					$imgId = IPS_GetObjectIDByIdent($cameraDeviceId."Snapshot", $this->InstanceID);
					if($imgId!==false)
						IPS_SetMediaFile($imgId, $filename, false);
				} else
					$log->LogMessage("Failed to download the image!");
				
				if($this->ReadPropertyBoolean("DeleteImage"))
					NA_DeleteLibraryItem($parentInstanceId, $item);
			} else
				$log->LogMessage("The snapshot was NOT found in the library");
		} else
			$log->LogMessage("This camera instance is not connected to a parent instance!");
	}
	
	public function TakeSnapshot() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$parentInstanceId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		$cameraName = $this->ReadPropertyString("ArloCameraName");
		$cameraDeviceId = $this->ReadPropertyString("ArloCameraDeviceId");
		
		$log->LogMessage("Preparing a snapshot using camera \"".$cameraName."\""); 
		
		if($parentInstanceId>0) {
			$now = microtime(true);
			$toDayDate = Date('Ymd', $now);
			$now*=1000;
			NA_TakeSnapshot($parentInstanceId, $cameraName);
			
			$log->LogMessage("Fetching the library from the Arlo cloud and searching for the last snapshot...");
			$library = NA_GetLibrary($parentInstanceId, $toDayDate, $toDayDate);
							
			for($x=0;$x<Count($library);$x++) {
				$lastModified = $library[$x]->lastModified;
				if($library[$x]->deviceId==$cameraDeviceId && $lastModified > $now) {
					$item = $library[$x];
					break;
				}
			}
			
			if(isset($item)) {
				$log->LogMessage("The snapshot was found in the library. Downloading...");
				$filename = __DIR__ . "/../../../media/".$cameraName.".jpg";
				
				if(NA_DownloadURL($parentInstanceId, $item->presignedContentUrl, $filename)) {
					$imgId = IPS_GetObjectIDByIdent($cameraDeviceId."Snapshot", $this->InstanceID);
					if($imgId!==false)
						IPS_SetMediaFile($imgId, $filename, false);
				} else
					$log->LogMessage("Failed to download the image!");
				
				if($this->ReadPropertyBoolean("DeleteImage"))
					NA_DeleteLibraryItem($parentInstanceId, $item);
			} else
				$log->LogMessage("The snapshot was NOT found in the library");
		} else
			$log->LogMessage("This camera instance is not connected to a parent instance!");
	}
	
}

?>
