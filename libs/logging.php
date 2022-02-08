<?php

class Logging {
  private $logEnabled;
  private $sender;

  function __construct($EnableLogging, $Sender) {
    $this->logEnabled = $EnableLogging;
    $this->sender = $Sender;
  }

  public function EnableLogging() {
    $this->logEnabled = true;
  }

  public function DisableLogging() {
    $this->logEnabled = false;
  }

  public function LogMessage($Message) {
    if($this->logEnabled) {
      IPS_LogMessage($this->sender, $Message);
	 }
  }
  
    public function LogMessageError($Message) {
      IPS_LogMessage($this->sender, $Message);
    }
}

