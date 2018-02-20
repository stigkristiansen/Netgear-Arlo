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
		$email = $this->GetPropertyString("email");
		$password = $this->GetPropertyString("password");
		
		if(strlen($password)>0 && strlen($email)) {
			$arlo = new Arlo();
			if (!$arlo->Init($email,$password))
				return false;
			if(!$arlo->StartStream($CameraName))
				return false;;
			if(!$arlo->TakeSnapshot($CameraName))
				return false;;
			
			$arlo->StopStream($CameraName);
			$arlo->Logout();
			
			return true;
		}	
	}
	
		
}

?>
