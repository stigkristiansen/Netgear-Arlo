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
		$InstancdeId = $this->ReadPropertyInteger("ArloModuleInstanceId");
		$cameraName = = $this->ReadPropertyString("ArloCameraName");
		$cameraDeviceId = = $this->ReadPropertyString("ArloCameraDeviceId");
		
		$now = microtime(true)*1000;
		NA_TakeSnapshot($InstancdeId, $cameraName);
		$library = NA_GetLibrary(InstancdeId);
		
		$url = "";
		for($x=0;$x<Count($library);$x++) {
			if($library[$x]->deviceId==$cameraDeviceId && $library[$x]->lastModified > $now) {
				$url = $library[$x]->presignedContentUrl; 
				break;
			}
		}
		
		if(strlen($url)>0) {
			$filename = __DIR__ . "/../../../media/".$cameraName.".jpg"
			NA_DownloadURL($InstancdeId, $url, $filename);
		}
	}

}

?>
