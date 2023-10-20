<?
// Klassendefinition
class MELCloudACDevice extends IPSModule {

	private $insId = 0;

	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);
		// Selbsterstellter Code
		$this->insId = $InstanceID;
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		// Diese Zeile nicht löschen.
		parent::Create();

		//We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

		$this->ConnectParent("{A0D2B115-36FD-ED99-7D09-85DB842A968B}");

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("DeviceId", 0);
		$this->RegisterPropertyInteger("BuildingId", 0);

		$workModeProfile = "MELCLOUDAC.".$this->insId.".WorkMode";
		if(!IPS_VariableProfileExists($workModeProfile)) {
			IPS_CreateVariableProfile($workModeProfile, 1);
			IPS_SetVariableProfileValues($workModeProfile, 0, 8, 0);
			IPS_SetVariableProfileIcon($workModeProfile, "Information");
			IPS_SetVariableProfileAssociation($workModeProfile, 7, $this->Translate("fanOnly"), "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($workModeProfile, 1, $this->Translate("heating"), "", 0xFF0000);
			IPS_SetVariableProfileAssociation($workModeProfile, 3, $this->Translate("cooling"), "", 0x0000FF);
			IPS_SetVariableProfileAssociation($workModeProfile, 2, $this->Translate("drying"), "", 0xCCCCCC);
			IPS_SetVariableProfileAssociation($workModeProfile, 8, $this->Translate("auto"), "", 0x00FF00);
		}

		$fanSpeedProfile = "MELCLOUDAC.".$this->insId.".FanSpeed";
		if(!IPS_VariableProfileExists($fanSpeedProfile)) {
			IPS_CreateVariableProfile($fanSpeedProfile, 1);
			IPS_SetVariableProfileValues($fanSpeedProfile, 0, 5, 0);
			IPS_SetVariableProfileIcon($fanSpeedProfile, "Ventilation");
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 0, $this->Translate("auto"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 1, "1", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 2, "2", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 3, "3", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 4, "4", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 5, "5", "", 0xC0C0C0);
		}

		$vaneHProfile = "MELCLOUDAC.".$this->insId.".VaneHorizontal";
		if(!IPS_VariableProfileExists($vaneHProfile)) {
			IPS_CreateVariableProfile($vaneHProfile, 1);
			IPS_SetVariableProfileValues($vaneHProfile, 0, 12, 0);
			IPS_SetVariableProfileIcon($vaneHProfile, "Ventilation");
			IPS_SetVariableProfileAssociation($vaneHProfile, 0, $this->Translate("auto"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation($vaneHProfile, 1, "1", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneHProfile, 2, "2", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneHProfile, 3, "3", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneHProfile, 4, "4", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneHProfile, 5, "5", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneHProfile, 12, $this->Translate("swing"), "", 0xC0C0C0);
		}

		$vaneVProfile = "MELCLOUDAC.".$this->insId.".VaneVertical";
		if(!IPS_VariableProfileExists($vaneVProfile)) {
			IPS_CreateVariableProfile($vaneVProfile, 1);
			IPS_SetVariableProfileValues($vaneVProfile, 0, 7, 0);
			IPS_SetVariableProfileIcon($vaneVProfile, "Ventilation");
			IPS_SetVariableProfileAssociation($vaneVProfile, 0, $this->Translate("auto"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation($vaneVProfile, 1, "1", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneVProfile, 2, "2", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneVProfile, 3, "3", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneVProfile, 4, "4", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneVProfile, 5, "5", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($vaneVProfile, 7, $this->Translate("swing"), "", 0xC0C0C0);
		}

		$this->RegisterVariableFloat("RoomTemperature", $this->Translate("f_temp_in"), "~Temperature.Room", 30);
		$this->RegisterVariableFloat("Temperature", $this->Translate("t_temp"), "~Temperature.Room", 20);
		$this->RegisterVariableBoolean("Power", $this->Translate("t_power"), "~Switch", 10);
		$this->RegisterVariableInteger("OperationMode", $this->Translate("t_work_mode"), $workModeProfile, 15);
		$this->RegisterVariableInteger("FanSpeed", $this->Translate("t_fan_speed"), $fanSpeedProfile, 40);
		$this->RegisterVariableInteger("VaneVertical", $this->Translate("VaneVertical"), $vaneVProfile, 50);
		$this->RegisterVariableInteger("VaneHorizontal", $this->Translate("VaneHorizontal"), $vaneHProfile, 60);

		$this->RegisterAttributeInteger("LastSet", 0);

		//Timer
		$this->RegisterTimer("UpdateTimer", 0, 'MELCLOUDAC_Update($_IPS[\'TARGET\']);');

		$this->EnableAction("Power");
		$this->EnableAction("OperationMode");
		$this->EnableAction("Temperature");
		$this->EnableAction("FanSpeed");
		$this->EnableAction("VaneVertical");
		$this->EnableAction("VaneHorizontal");

		$splitter = $this->GetSplitter();
		$this->RegisterMessage($splitter, IM_CHANGESTATUS);
		$ins = IPS_GetInstance($splitter);
		if($ins['InstanceStatus'] == 102){
			$this->SendDebug("Create", "Update on create", 0);
			$this->SetTimerInterval("UpdateTimer", 5000);
		}
	}

	/**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return parent::Destroy();
        }
        if (!IPS_InstanceExists($this->insId)) {
            //Profile löschen
			$this->UnregisterProfile("MELCLOUDAC.".$this->insId.".WorkMode");
			$this->UnregisterProfile("MELCLOUDAC.".$this->insId.".FanSpeed");
			$this->UnregisterProfile("MELCLOUDAC.".$this->insId.".VaneHorizontal");
			$this->UnregisterProfile("MELCLOUDAC.".$this->insId.".VaneVertical");
        }
        parent::Destroy();
    }

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();

		//Only call this in READY state. On startup the WebHook instance might not be available yet
        //if (IPS_GetKernelRunlevel() == KR_READY) {
        //    $this->RegisterHook();
        //}
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data){
		//Never delete this line!
		parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
		
		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
			$this->RegisterHook();
			return;
        }

		switch($SenderID){
			case $this->GetSplitter():
				if($Message != IM_CHANGESTATUS) break;
				$ins = IPS_GetInstance($SenderID);
				if($ins['InstanceStatus'] == 102){
					$this->SetTimerInterval("UpdateTimer", 1000);
				}else{
					$this->SetTimerInterval("UpdateTimer", 10000);
				}
				break;

			default:
				$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true),0);
		}
	}

	private function GetJSONBuffer($name)
	{
		$raw = $this->GetBuffer($name);
		$data = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
		if($data->binary){
			return base64_decode($data->data);
		}
		return $data->data;
	}

	private function SetJSONBuffer($name, $value, $binary = false)
	{
		if($binary) $value = base64_encode($value);
		$data = [
			'data' 	=> $value,
			'binary'=> $binary
		];
		$json = json_encode($data, JSON_THROW_ON_ERROR);
		$this->SetBuffer($name, $json);
	}


	public function RequestAction($Ident, $Value, $Automatic = false) {
		$splitter = $this->GetSplitter();
		$ins = IPS_GetInstance($splitter);
		if($ins['InstanceStatus'] <> 102){
			$this->LogMessage("Could not send command because cloud not connected", KL_ERROR);
			throw new Exception($this->Translate("Cloud not connected"));
		}

		$data = array(
			"DataID" => "{3E9CB080-0C40-FBD3-3D32-7A23917984A4}",
			"command" => "SetDevice",
			"DeviceId" => $this->ReadPropertyInteger("DeviceId"),
			$Ident => $Value
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);

		$this->WriteAttributeInteger("LastSet", time());

		$this->SetValue($Ident, $Value);
	}

	public function GetConfigurationForm(){
		$form = '{
			"elements":
			[
				
			]
		}';
		return $form;
	}

	/**
	 * Update AC -> If offline also request values for all properties
	 */
	public function Update(){
		$dev = $this->GetDevice();

		$lastComm = new DateTime($dev->LastCommunication, new DateTimeZone("Etc/UTC"));
		if($lastComm->getTimestamp() > $this->ReadAttributeInteger("LastSet")){
			$this->SetValue("Power", $dev->Power);
			$this->SetValue("RoomTemperature", $dev->RoomTemperature);
			$this->SetValue("Temperature", $dev->SetTemperature);
			$this->SetValue("OperationMode", $dev->OperationMode);
			$this->SetValue("FanSpeed", $dev->SetFanSpeed);
			$this->SetValue("VaneVertical", $dev->VaneVertical);
			$this->SetValue("VaneHorizontal", $dev->VaneHorizontal);
		}else{
			$this->SendDebug("Update", "Ignoring update because last communiction ".$lastComm->format('Y-m-d H:i:s')." was earlier than last set at ".$this->ReadAttributeInteger("LastSet"), 0);
		}

		$nextComm = new DateTime($dev->NextCommunication, new DateTimeZone("Etc/UTC"));
		$secondsTillNextComm = $nextComm->getTimestamp()-time();

		$this->SendDebug("Update", "Next communication at ".$nextComm->format('Y-m-d H:i:s')."; Seconds till ".$secondsTillNextComm, 0);
		$this->SetTimerInterval("UpdateTimer", $secondsTillNextComm < 60 ? 60000 : (($secondsTillNextComm + 10) * 1000));
	}

	/**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @access private
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetSplitter()
    {
        $SplitterID = IPS_GetInstance($this->insId)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
	}

	private function GetDevice(){
		$data = array(
			"DataID" => "{3E9CB080-0C40-FBD3-3D32-7A23917984A4}",
			"command" => "GetDevice",
			"DeviceId" => $this->ReadPropertyInteger("DeviceId"),
			"BuildingId" => $this->ReadPropertyInteger("BuildingId")
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);

		$jsonData = json_decode($result);

		$this->SendDebug("GetDevice", "Result: ".$result, 0);

		return $jsonData;
    }

	/**
     * Löscht ein Variablenprofile, sofern es nicht außerhalb dieser Instanz noch verwendet wird.
     *
     * @param string $Name Name des zu löschenden Profils.
     */
    protected function UnregisterProfile(string $Name)
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Name);
	}
}