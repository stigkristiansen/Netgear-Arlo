<?	

require_once(__DIR__ . "/../libs/arlo.php");

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
		
		$scriptId = @IPS_GetObjectIDByIdent("scriptsnapshot", $this->InstanceID);
		if($scriptId===false) {
			$script = "<? NAC_TakeSnapshot(".$this->InstanceID."); ?>";
			$scriptId = $this->RegisterScript("scriptsnapshot", "_Snapshot", $script, 0);
		} 
			
		$eventId = @IPS_GetObjectIDByIdent("eventsnapshot", $scriptId);
		if($eventId===false) {
			$eventId = IPS_CreateEvent(1);
			IPS_SetParent($eventId, $scriptId);
			IPS_SetIdent($eventId, "eventsnapshot");
			IPS_SetEventCyclicTimeFrom($eventId , 12 , 0, 0) ;
		}
		
		IPS_SetEventActive($eventId,$this->ReadPropertyBoolean("ScheduleSnapshot")); 
    }
	
	public function TakeSnapshot() {
		$InstanceId = $this->ReadPropertyInteger("ArloModuleInstanceId");
		$cameraName = $this->ReadPropertyString("ArloCameraName");
		$cameraDeviceId = $this->ReadPropertyString("ArloCameraDeviceId");
		
		$now = microtime(true);
		$toDayDate = Date('Ymd', $now);
		$now*=1000;
		NA_TakeSnapshot($InstanceId, $cameraName);
		$library = NA_GetLibrary($InstanceId, $toDayDate, $toDayDate);
		
		$url = "";
		for($x=0;$x<Count($library);$x++) {
			$lastModified = $library[$x]->lastModified;
			if($library[$x]->deviceId==$cameraDeviceId && $lastModified > $now) {
				$item = $library[$x];
				break;
			}
		}
		
		if(isset($item)) {
			$filename = __DIR__ . "/../../../media/".$cameraName.".jpg";
			
			if(NA_DownloadURL($InstanceId, $item->presignedContentUrl, $filename)) {
				$imgId = IPS_GetObjectIDByIdent($cameraName."Snapshot", $this->InstanceID);
				if($imgId!==false)
					IPS_SetMediaFile($imgId, $filename, false);
			}
			
			if($this->ReadPropertyBoolean("DeleteImage"))
				NA_DeleteLibraryItem($InstanceId, $item);
		}
	}
	
}

?>
