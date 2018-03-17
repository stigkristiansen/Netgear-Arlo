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
		
		$this->ConnectParent("{10113AE2-5247-439C-B386-B65B0DC32B12}");
    }
}

?>
