<?	

require_once(__DIR__ . "/../libs/arlo.php");

class ArloCameraModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		$this->RegisterPropertyInteger ("ArloModuleInstanceId", 0);
		$this->RegisterPropertyString ("ArloCameraName", "");
		$this->RegisterPropertyString ("ArloCameraDeviceId", "");		
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
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
			if($library[$x]->deviceId==$cameraDeviceId && $lastModified > $now && $lastModified < $now+10000) {
				$url = $library[$x]->presignedContentUrl; 
				break;
			}
		}
		
		if(strlen($url)>0) {
			$filename = __DIR__ . "/../../../media/".$cameraName.".jpg";
			if(NA_DownloadURL($InstanceId, $url, $filename)) {
				$imgId = IPS_GetObjectIDByIdent($cameraName."Snapshot", $this->InstanceID);
				if($imgId!==false)
					IPS_SetMediaFile($imgId, $filename, false);
			}
		}
	}
	
}

?>
