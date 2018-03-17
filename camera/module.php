<?	

require_once(__DIR__ . "/../libs/arlo.php");
require_once(__DIR__ . "/../libs/logging.php");

class ArloCameraModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean("Log", true);
		$this->RegisterPropertyBoolean("DeleteImage", true);
		$this->RegisterPropertyInteger("ArloModuleInstanceId", 0);
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
	
	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		IPS_LogMessage("ReceiveData", utf8_decode($data->Buffer));
	 
		// SetValue($this->GetIDForIdent("Value"), $data->Buffer);
	}
	
	public function TakeSnapshot() {
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$InstanceId = $this->ReadPropertyInteger("ArloModuleInstanceId");
		$cameraName = $this->ReadPropertyString("ArloCameraName");
		$cameraDeviceId = $this->ReadPropertyString("ArloCameraDeviceId");
		
		$log->LogMessage("Preparing a snapshot using camera \"".$cameraName."\""); 
		
		$now = microtime(true);
		$toDayDate = Date('Ymd', $now);
		$now*=1000;
		NA_TakeSnapshot($InstanceId, $cameraName);
		
		$log->LogMessage("Fetching the library from the Arlo cloud and searching for the last snapshot...");
		$library = NA_GetLibrary($InstanceId, $toDayDate, $toDayDate);
						
		for($x=0;$x<Count($library);$x++) {
			$lastModified = $library[$x]->lastModified;
			if($library[$x]->deviceId==$cameraDeviceId && $lastModified > $now) {
				$item = $library[$x];
				break;
			}
		}
		
		if(isset($item)) {
			$log->LogMessage("The snapshot was found in the library");
			$filename = __DIR__ . "/../../../media/".$cameraName.".jpg";
			
			if(NA_DownloadURL($InstanceId, $item->presignedContentUrl, $filename)) {
				$imgId = IPS_GetObjectIDByIdent($cameraName."Snapshot", $this->InstanceID);
				if($imgId!==false)
					IPS_SetMediaFile($imgId, $filename, false);
			}
			
			if($this->ReadPropertyBoolean("DeleteImage"))
				NA_DeleteLibraryItem($InstanceId, $item);
		} else
			$log->LogMessage("The snapshot was NOT found in the library");
	}
	
}

?>
