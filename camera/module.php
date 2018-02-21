<?

require_once(__DIR__ . "/../libs/arlo.php");

class ArloCameraModule extends IPSModule {

    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", true);
	}
   
    public function ApplyChanges(){
        parent::ApplyChanges();
    }

}

?>
