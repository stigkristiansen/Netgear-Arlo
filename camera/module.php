<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloCameraModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
		
		$this->RegisterPropertyInteger ("ArloInstanceId", 0);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
    }

}

?>
