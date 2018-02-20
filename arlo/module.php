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

	
}

?>
