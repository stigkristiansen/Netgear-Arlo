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
    }

}

?>
