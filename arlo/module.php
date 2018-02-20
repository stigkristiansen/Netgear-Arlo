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
    }
	
	public function TakeSnapshot (string $CameraName) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if ($arlo->Init($email,$password)===false)
				return false;
			if($arlo->StartStream($CameraName)===false)
				return false;;
			if($arlo->TakeSnapshot($CameraName)===false)
				return false;
			
			$arlo->StopStream($CameraName);
			$arlo->Logout();
			
			return true;
		}	
	}
	
	public function GetLibrary ($FromYYYYMMDD, $ToYYYYMMDD) {
		$email = $this->ReadPropertyString("email");
		$password = $this->ReadPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)>0) {
			$arlo = new Arlo();
			if (!$arlo->Init($email,$password))
				return false;
			
			$library = $arlo->GetLibrary($FromYYYYMMDD, $ToYYYYMMDD);
			$arlo->Logout();
			
			if($library === false)
				return false;
					
			return $library;
		}	
	}
	
		
}

?>
