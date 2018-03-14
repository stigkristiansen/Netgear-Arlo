<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloBasestationModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		
		$this->RegisterPropertyInteger ("ArloModuleInstanceId", 0);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
		
		ConnectParent("{10113AE2-5247-439C-B386-B65B0DC32B12}");
    }
	
	public function ForwardData($JSONString) {
		$data = json_decode($JSONString);
		IPS_LogMessage("ForwardData", utf8_decode($data->Buffer));
	 
		//$resultat = $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $data->Buffer)));
	 
		return true;
	}

}

?>
