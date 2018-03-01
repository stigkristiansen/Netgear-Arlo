<?
require_once(__DIR__ . "/../libs/logging.php");

class Arlo {

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
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/logout" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json;charset=UTF-8', 'User-Agent: Symcon')); 
		
		curl_exec ($ch);
		
		$this->authentication = NULL;
		$this->cameras = NULL;
		$this->basestations = NULL;
		
		return true;
		
	}
	
	public function GetAllDevices() {
		if($this->authentication==NULL)
			return false;
		
		$devices = Array("cameras" => $this->cameras, "basestations" => $this->basestations);
		
		return $devices;
	}
	
	public function GetLibrary($FromYYYYMMDD, $ToYYYYMMDD) {
		if($this->authentication==NULL)
			return false;			
	
		$ch = curl_init();
		
		$data = '{"dateFrom": "'.$FromYYYYMMDD.'","dateTo": "'.$ToYYYYMMDD.'"}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token);
			
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/library" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $data); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers); 
		
		$result=json_decode(curl_exec ($ch));
		
		if(isset($result->success) && $result->success)
		 	return $result->data;
		else
			return false;

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

		$ch = curl_init();
		
		$data = '{"xcloudId":"'.$camera->xCloudId.'","parentId":"'.$camera->parentId.'","deviceId":"'.$camera->deviceId.'","olsonTimeZone":"'.$camera->properties->olsonTimeZone.'"}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudid: '.$camera->xCloudId, 'User-Agent: Symcon');
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/devices/takeSnapshot");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $data); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers); 
		
		$result=json_decode(curl_exec ($ch));
		
		if(isset($result->success) && $result->success)
		 	return true;
		else
			return false;
	}
	
	public function DeleteLibraryItem($LibraryItem) {
		if($this->authentication==NULL)
			return false;	
		
		$ch = curl_init();
						
		$data = '{"data":[{"createdDate":"'.$LibraryItem->createdDate.'", "deviceId":"'.$LibraryItem->deviceId.'", "utcCreatedDate":'.$LibraryItem->utcCreatedDate.'}]}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'User-Agent: Symcon');
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/library/recycle");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $data); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers); 
		
		$result=json_decode(curl_exec ($ch));
		
		if(isset($result->success) && $result->success)
		 	return true;
		else
			return false;
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
	// ** Private properties **
	// ***********************
	
	private $cameras = NULL;
	private $basestations = NULL;
	private $authentication = NULL;



	// ***********************	
	// ** Private functions **
	// ***********************
	
	function StartStreaming ($CameraName, $State) {
		$log = new Logging(false, "Arlo Class");
		
		if($this->authentication==NULL)
			return false;	
		
		$camera = $this->GetCamera($CameraName);	
		if($camera===false)
			return false;
			
		if($State)
			$activityState = "startUserStream";  
		else
			$activityState = "stopUserStream";  
		
		$ch = curl_init();
		
		$data = '{"to":"'.$camera->deviceId.'","from":"'.$this->authentication->userId.'_web","resource":"cameras/'.$camera->deviceId.'","action":"set","publishResponse":true,"transId":"web!8e3a372f.8adff!1509302776732","properties":{"activityState":"'.$activityState.'","cameraId":"'.$camera->deviceId.'"}}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudId: '.$camera->xCloudId, 'User-Agent: Symcon');
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/devices/startStream");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $data); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers); 
		
		$result=curl_exec($ch);
		
		if($result!==false){
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success)
				return true;
			else if(isset($result->success) && !$result->success)
				$log->LogMessageError("StartStreaming: ".$result->data->message);
			else
				$log->LogMessageError("StartStreaming: Unkonwn JSON returned: ".$originalResult);
		} else
			$log->LogMessageError("StartStreaming: The http post request failed");
		
		return false;
	}
	
	function Arming ($BasestationName, $Armed) {
		$log = new Logging(false, "Arlo Class");
		
		if($this->authentication==NULL)
			return false;	
		
		$basestation = $this->GetBasestation($BasestationName);	
		
		if($basestation===false)
			return false;
			
		if($Armed)
			$mode = "mode1";  //Arming
		else
			$mode = "mode0";  //Disarming
				
		$ch = curl_init();
		
		$data =  '{"from":"'.$this->authentication->userId.'_web","to":"'.$basestation->deviceId.'","action":"set","resource":"modes","transId":"web!bvghopiy.asdfqweriopuzxcvbghn","publishResponse":true,"properties":{"active":"'.$mode.'"}}';
		$headers = array('Content-Type: application/json;charset=UTF-8', 'Authorization: '.$this->authentication->token, 'xcloudid: '.$basestation->xCloudId);
			
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/devices/notify/".$basestation->deviceId);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $data); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers); 
		
		$result=curl_exec ($ch);
		
		if($result!==false){
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success)
				return true;
			else if(isset($result->success) && !$result->success)
				$log->LogMessageError("Arming: ".$result->data->message);
			else
				$log->LogMessageError("Arming: Unkonwn JSON returned: ".$originalResult);
		} else 
			$log->LogMessageError("Arming: The http post request failed");
				
		return false;
			
	}
	
	function Authenticate($Email, $Password) {
		$log = new Logging(false, "Arlo Class");
		
		$url = "https://arlo.netgear.com/hmsweb/login/v2";
		$data = "{\"email\":\"".$Email."\",\"password\":\"".$Password."\"}"; 
		$header = array('Content-Type: application/json;charset=UTF-8', 'User-Agent: Symcon');
		
		$result = $this->HttpRequest("post", $url , $header, $data);
						
		return $result;
		
		/*$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/login/v2" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_POST,           1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     "{\"email\":\"".$Email."\",\"password\":\"".$Password."\"}"); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json;charset=UTF-8', 'User-Agent: Symcon')); 
		
		$result=curl_exec($ch);
		
		if($result!==false) {
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success)
				return $result->data;
			else if(isset($result->success) && !$result->success)
				$log->LogMessageError("Authenticate: ".$result->data->message);
			else
				$log->LogMessageError("Authenticate: Unkonwn JSON returned: ".$originalResult);
		} else 
			$log->LogMessageError("Authenticate: The http post request failed");
		
		return false;
		*/
	}
	
	function GetDevices ($Authentication) {
		$log = new Logging(false, "Arlo Class");
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL,            "https://arlo.netgear.com/hmsweb/users/devices" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Authorization: '.$Authentication->token)); 
		
		$result=curl_exec($ch);
		
		if($result!==false) {
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success)
				return $result->data;
			else if(isset($result->success) && !$result->success)
				$log->LogMessageError("GetDevices: ".$result->data->message);
			else
				$log->LogMessageError("GetDevices: Unkonwn JSON returned: ".$originalResult);
		} else
			$log->LogMessageError("GetDevices: The http request failed");
		
		return false;
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
		$log = new Logging(false, "Arlo Class");
		
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
		
		$log->LogMessageError("HttpRequest: Returned data: ".$result);
		
		if($result!==false){
			$originalResult = $result;
			$result = json_decode($result);
			if(isset($result->success) && $result->success) {
				if($ReturnData)
					return $result->data;
				return true;
			} else if(isset($result->success) && !$result->success)
				$log->LogMessageError("HttpRequest: ".$result->data->message);
			else
				$log->LogMessageError("HttpRequest: Unkonwn JSON returned: ".$originalResult);
		} else
			$log->LogMessageError("HttpRequest: The http ".$Type." request failed");
		
		return false;
	}
}



?>