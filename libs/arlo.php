<?
require_once(__DIR__ . "/../libs/logging.php");

class Arlo {
	
	// ***********************	
	// ** Constructor       **
	// ***********************
	
	function __construct($EnableLogging) {
		$this->log = $EnableLogging;
	}
	
	// ************************	
	// ** Private properties **
	// ************************
	
	private $cameras = NULL;
	private $basestations = NULL;
	private $authentication = NULL;
	private $log = false;

	
	// ***********************	
	// ** Public functions **
	// ***********************
			
	public function Init ($Email, $Password) {
		$result = $this->Authenticate($Email, $Password);
		if($result===false)
			return false;
		
		$this->authentication = $result;
		
		$result = $this->GetDevices($this->authentication);
		if($result===false)
			return false;
		
		$this->cameras = $this->GetDeviceType($result, "camera");
		$this->basestations = $this->GetDeviceType($result, "basestation");
	
		if($this->cameras==NULL || $this->basestations == NULL)
			return false;
			
		return true;;
	}
	
	public function GetAuthentication(){
		if($this->authentication == NULL)
			return false;
		
		return $this->authentication;
	}
	
	public function Logout() {
		$url = "https://arlo.netgear.com/hmsweb/logout";
		$headers = array('Content-Type: application/json;charset=UTF-8', 'User-Agent: Symcon', 'Authorization: '.$this->authentication->token);
		
		$result = $this->HttpRequest("put", $url , $headers, NULL, false);
		
		$this->authentication = NULL;
		$this->cameras = NULL;
		$this->basestations = NULL;
		
		return true;
	}
	
	public function GetAllDevices() {
		if($this->authentication==NULL)
			return false;
		
		return Array("cameras" => $this->cameras, "basestations" => $this->basestations);
	}
	
	public function GetLibrary($FromYYYYMMDD, $ToYYYYMMDD) {
		if($this->authentication==NULL)
			return false;		
		
		$url = "https://arlo.netgear.com/hmsweb/users/library";
		$data = '{"dateFrom": "'.$FromYYYYMMDD.'","dateTo": "'.$ToYYYYMMDD.'"}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token);
		
		return $this->HttpRequest("post", $url , $headers, $data, true);
	}
	
	public function Arm($BasestationName) {
		return $this->Arming($BasestationName, true);
	}  
			
	public function Disarm($BasestationName) {
		return $this->Arming($BasestationName, false);
	} 
	
	public function TakeSnapshot ($CameraName) {
		if($this->authentication==NULL)
			return false;	
		
		$camera = $this->GetCamera($CameraName);	
		if($camera===false)
			return false;
		
		$url = "https://arlo.netgear.com/hmsweb/users/devices/takeSnapshot";
		$data = '{"xcloudId":"'.$camera->xCloudId.'","parentId":"'.$camera->parentId.'","deviceId":"'.$camera->deviceId.'","olsonTimeZone":"'.$camera->properties->olsonTimeZone.'"}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudid: '.$camera->xCloudId, 'User-Agent: Symcon');
		
		return $this->HttpRequest("post", $url , $headers, $data, false);
	}
	
	public function DeleteLibraryItem($LibraryItem) {
		if($this->authentication==NULL)
			return false;

		$url = "https://arlo.netgear.com/hmsweb/users/library/recycle";
		$data = '{"data":[{"createdDate":"'.$LibraryItem->createdDate.'", "deviceId":"'.$LibraryItem->deviceId.'", "utcCreatedDate":'.$LibraryItem->utcCreatedDate.'}]}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'User-Agent: Symcon');
		
		return $this->HttpRequest("post", $url , $headers, $data, false);
	}
	
	public function StartStream($CameraName) {
		return $this->StartStreaming($CameraName, true);
	}
	
	public function StopStream($CameraName) {
		return $this->StartStreaming($CameraName, false);
	}
	
	public function DownloadURL($Url, $Filename) {
	 	$fp = fopen($Filename, 'w+');
		 
		if($fp === false){
		    return false;
		}
		
		$ch = curl_init($Url);
		 
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_TIMEOUT, 45);
		 
		curl_exec($ch);
		 
		if(curl_errno($ch)) {
		    return false;
			//throw new Exception(curl_error($ch));
		}
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		 
		curl_close($ch);
		 
		if($statusCode == 200)
		    return true;
		else
		    return false;
	}
	    
		
	
	// ***********************	
	// ** Private functions **
	// ***********************
	
	function StartStreaming ($CameraName, $State) {
		if($this->authentication==NULL)
			return false;	
		
		$camera = $this->GetCamera($CameraName);	
		if($camera===false)
			return false;
			
		if($State)
			$activityState = "startUserStream";  
		else
			$activityState = "stopUserStream";  
		
		$url = "https://arlo.netgear.com/hmsweb/users/devices/startStream";
		$data = '{"to":"'.$camera->deviceId.'","from":"'.$this->authentication->userId.'_web","resource":"cameras/'.$camera->deviceId.'","action":"set","publishResponse":true,"transId":"web!8e3a372f.8adff!1509302776732","properties":{"activityState":"'.$activityState.'","cameraId":"'.$camera->deviceId.'"}}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudId: '.$camera->xCloudId, 'User-Agent: Symcon');
		
		return $this->HttpRequest("post", $url , $headers, $data, false);
	}
	
	function Arming ($BasestationName, $Armed) {
		if($this->authentication==NULL)
			return false;	
		
		$basestation = $this->GetBasestation($BasestationName);	
		
		if($basestation===false)
			return false;
			
		if($Armed)
			$mode = "mode1";  //Arming
		else
			$mode = "mode0";  //Disarming
		
		$url = "https://arlo.netgear.com/hmsweb/users/devices/notify/".$basestation->deviceId;
		$data =  '{"from":"'.$this->authentication->userId.'_web","to":"'.$basestation->deviceId.'","action":"set","resource":"modes","transId":"web!bvghopiy.asdfqweriopuzxcvbghn","publishResponse":true,"properties":{"active":"'.$mode.'"}}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudid: '.$basestation->xCloudId);
		
		return $this->HttpRequest("post", $url , $header, $data, false);
	}
	
	function Authenticate($Email, $Password) {
		$url = "https://arlo.netgear.com/hmsweb/login/v2";
		$data = "{\"email\":\"".$Email."\",\"password\":\"".$Password."\"}"; 
		$header = array('Content-Type: application/json;charset=UTF-8', 'User-Agent: Symcon');
		
		return $this->HttpRequest("post", $url , $header, $data, true);
	}
	
	function GetDevices ($Authentication) {
		$url="https://arlo.netgear.com/hmsweb/users/devices";
		$header = array('Authorization: '.$Authentication->token);
		$data = NULL;
		
		return $this->HttpRequest("get", $url , $header, $data, true);
	}
			
	function GetDeviceType($Devices, $DeviceType) {
		$DeviceType = strtoupper($DeviceType);
		
		$returnedData = NULL;
		for($x=0;$x<count($Devices);$x++) {
			if(strtoupper($Devices[$x]->deviceType)==$DeviceType)
				$returnedData[] = $Devices[$x];
		}
		
		return $returnedData;
	}
	
	private function GetCamera($CameraName) {
		$CameraName = strtoupper($CameraName);
		
		if($this->cameras==NULL)
			return false;
						
		for($x=0;$x<count($this->cameras);$x++) {
			if(strtoupper($this->cameras[$x]->deviceName)==$CameraName)
				return $this->cameras[$x];
		}
		
		return false;
	}
	
	private function GetBasestation($BasestationName) {
		$BasestationName = strtoupper($BasestationName);
	
		if($this->basestations==NULL)
				return false;
			
			
		for($x=0;$x<count($this->basestations);$x++) {
			if(strtoupper($this->basestations[$x]->deviceName)===$BasestationName)
				return $this->basestations[$x];
		}
		
		return false;
	}
	
	private function HttpRequest($Type, $Url, $Headers, $Data=NULL, $ReturnData=True) {
		$log = new Logging($this->log, "Arlo Class");
		
		$ch = curl_init();
		
		switch(strtolower($Type)) {
			case "put":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
				break;
			case "post":
				curl_setopt($ch, CURLOPT_POST, 1 );
				break;
			case "get":
				// Get is default for cURL
				break;
		}
		
		curl_setopt($ch, CURLOPT_URL, $Url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_HTTPHEADER, $Headers);

		if($Data!=NULL)
			curl_setopt($ch, CURLOPT_POSTFIELDS, $Data); 
		
		$result=curl_exec($ch);
		
		$log->LogMessage("HttpRequest:  Returned data was ".$result);
		
		if($result!==false){
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success) {
				if($ReturnData)
					return $result->data;
				return true;
			} else if(isset($result->success) && !$result->success)
				$log->LogMessage("HttpRequest: ".$result->data->message);
			else
				$log->LogMessage("HttpRequest: Unkonwn JSON returned: ".$originalResult);
		} else
			$log->LogMessage("HttpRequest: The http ".$Type." request failed");
		
		return false;
	}
}



?>