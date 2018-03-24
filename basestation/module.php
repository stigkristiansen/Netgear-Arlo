<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloBasestationModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		
		$this->RegisterPropertyInteger ("ArloBasestationName", 0);
		$this->RegisterPropertyInteger ("ArloBasestationDeviceId",0);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
		
		$this->ConnectParent("{10113AE2-5247-439C-B386-B65B0DC32B12}");
    }
	
	public function Arm(){
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$parentInstanceId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		$basestationName = $this->ReadPropertyString("ArloBasestationName");
		$basestationDeviceId = $this->ReadPropertyString("ArloBasestationDeviceId");
		
		$log->LogMessage("Preparing to arm the system \"".$basestationName."\""); 
		
		if($parentInstanceId>0) {
			if($this->SendCommandToParent("Arm",array("BasestationName"=>$basestationName))) {
				$log->LogMessage("The system was armed!");
			} else
				$log->LogMessage("Arming the system failed!");
		} else
			$log->LogMessage("This basestation instance is not connected to a parent instance!");
	}
	
	public function Disarm(){
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
				
		$parentInstanceId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		$basestationName = $this->ReadPropertyString("ArloBasestationName");
		$basestationDeviceId = $this->ReadPropertyString("ArloBasestationDeviceId");
		
		$log->LogMessage("Preparing to arm the system \"".$basestationName."\""); 
		
		if($parentInstanceId>0) {
			if($this->SendCommandToParent("Disarm",array("BasestationName"=>$basestationName))) {
				$log->LogMessage("The system was disarmed!");
			} else
				$log->LogMessage("Disarming the system failed!");
		} else
			$log->LogMessage("This basestation instance is not connected to a parent instance!");
	}
	
	private function SendCommandToParent($Command, $Parameters){
		$data = array("Instruction"=>"CloudCommand", "Command"=>$Command, "Parameters"=>$Parameters);
		$result = json_decode($this->SendDataToParent(json_encode(Array("DataID" => "{0F113ADC-F4F1-47F7-A0B2-B95D6AE0A77A}", "Buffer" => $data))));
		
		if(isset($result->Data))
			return $result->Data;
		else
			return $result->Success;
	}
}

?>
