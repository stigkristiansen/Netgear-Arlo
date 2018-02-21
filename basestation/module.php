<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloBasestationModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
    }

}

?>
