<?
// Klassendefinition
class MELCloudACSplitter extends IPSModule {

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

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyString("Username", "");
		$this->RegisterPropertyString("Password", "");
	
		$this->RegisterAttributeString("AuthToken", "");
		$this->RegisterAttributeInteger("TokenExpire", 0);
		$this->RegisterAttributeInteger("LastSignIn", 0);

		//Timer
		//$this->RegisterTimer("RefreshTokenTimer", 0, 'MELCLOUDACSPLIT_RefreshToken($_IPS[\'TARGET\']);');
	}

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();

		if($this->ReadPropertyString("Username") !== '' && $this->ReadPropertyString("Password") !== ''){
			$this->SignIn();
		}

		//if($this->ReadPropertyBoolean("AutomaticUpdate")){
		//	$this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateInterval") * 60000);
		//}else{
		//	$this->SetTimerInterval("UpdateTimer", 0);
		//}
	}

	public function RequestAction($Ident, $Value) {
		switch($Ident) {				
			default:
				throw new Exception($this->Translate("Invalid Ident"));
		}
	}

	public function GetConfigurationForm(){
		return '{
			"elements":
			[
				{ "type": "ValidationTextBox", "name": "Username", "caption": "Username" },
				{ "type": "ValidationTextBox", "name": "Password", "caption": "Password" }
			],
			"status":
			[
				{ "code": 102, "icon": "active", "caption": "Signed in" },
				{ "code": 201, "icon": "error", "caption": "Authentication failed" },
				{ "code": 202, "icon": "error", "caption": "Account is locked" },
				{ "code": 203, "icon": "error", "caption": "Unknown error" }
			]
		}';
	}

	private function SignIn() {
		$ch = curl_init("https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = array(
			"Email" => $this->ReadPropertyString("Username"),
			"Password" => $this->ReadPropertyString("Password"),
			"Language" => 4,
			"AppVersion" => "1.28.1.0",
			"Persist" => true
			);
		$data_string = json_encode($data);

		$this->SendDebug("SignIn", "Request: ".$data_string, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		$cInfo = curl_getinfo($ch);
		curl_close($ch);

		$this->SendDebug("SignIn", "Result: ".$result, 0);

		$resData = json_decode($result);

		if(property_exists($resData, 'ErrorId')){
			switch($resData->ErrorId){
				case 'Invalid email or password':
					$this->SetStatus(201);
					break;
				case 'Your account is locked.':
					$this->SetStatus(202);
					break;
				default:
					$this->SetStatus(203);
			}
			$this->WriteAttributeString("AuthToken", "");
			$this->WriteAttributeInteger("TokenExpire", 0);
			$this->WriteAttributeInteger("LastSignIn", 0);
			//$this->SetTimerInterval("RefreshTokenTimer", 0);
			return;
		}

		$this->WriteAttributeString("AuthToken", $resData->LoginData->ContextKey);
		$this->WriteAttributeInteger("TokenExpire", strtotime($resData->LoginData->Expiry));
		$this->WriteAttributeInteger("LastSignIn", time());

		$this->SendDebug("SignIn", "Token: ".$this->ReadAttributeString("AuthToken"), 0);

		$this->SetStatus(102);
		//$refresh = round($resData->expires_in * 0.9);
		//$this->LogMessage("SignIn successful - next refresh in $refresh sec", KL_MESSAGE);
		//$this->SetTimerInterval("RefreshTokenTimer", $refresh * 1000);
	}

	private function GetDevices(){
		$expire = $this->ReadAttributeInteger("TokenExpire");
		if($expire == 0 || time() > $expire){
			SignIn();
		}

		if($this->GetStatus() <> 102){
			return;
		}

		$ch = curl_init("https://app.melcloud.com/Mitsubishi.Wifi.Client/User/Listdevices");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'X-MitsContextKey: '.$this->ReadAttributeString("AuthToken")
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	private function GetDevice($DeviceId, $BuildingId){
		$expire = $this->ReadAttributeInteger("TokenExpire");
		if($expire == 0 || time() > $expire){
			SignIn();
		}

		if($this->GetStatus() <> 102){
			return;
		}

		$ch = curl_init("https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=$DeviceId&buildingID=$BuildingId");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'X-MitsContextKey: '.$this->ReadAttributeString("AuthToken")
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	private function SetDevice($DeviceId, 
								$power = null, 
								$operationMode = null, 
								$temperature = null,
								$fanSpeed = null,
								$vaneVertical = null,
								$vaneHorizontal = null){
		$expire = $this->ReadAttributeInteger("TokenExpire");
		if($expire == 0 || time() > $expire){
			SignIn();
		}

		if($this->GetStatus() <> 102){
			return;
		}

		$effective = 0x0;
		if(is_null($power)){ $effective |= 0x1; }
		if(is_null($operationMode)){ $effective |= 0x2; }
		if(is_null($temperature)){ $effective |= 0x4; }
		if(is_null($fanSpeed)){ $effective |= 0x8; }
		if(is_null($vaneVertical)){ $effective |= 0x10; }
		if(is_null($vaneHorizontal)){ $effective |= 0x100; }

		$ch = curl_init("https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = array(
			"DeviceId" => $DeviceId,
			"Power" => $power,
			"OperationMode" => $operationMode,
			"SetTemperature" => $temperature,
			"SetFanSpeed" => $fanSpeed,
			"VaneVertical" => $vaneVertical,
			"VaneHorizontal" => $vaneHorizontal,
			"EffectiveFlags" => $effective
		);
		$data_string = json_encode($data);

		$this->SendDebug("SetDevice", "Request: ".$data_string, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string),
			'X-MitsContextKey: '.$this->ReadAttributeString("AuthToken")
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	public function ForwardData($JSONString) {
		$json = json_decode($JSONString);
		switch($json->DataID){
			case '{3E126AF2-7CFE-0527-1DD7-13730E1706D4}': //Configurator
				switch($json->command){
					case 'GetDevices':
						return $this->GetDevices();
				}
				break;

			case '{3E9CB080-0C40-FBD3-3D32-7A23917984A4}': //Device
				switch($json->command){
					case 'GetDevice':
						return $this->GetDevice($json->DeviceId, $json->BuildingId);

					case 'SetDevice':
						return $this->SetDevice(
							$json->DeviceId, 
							isset($json->Power) = $json->Power : null,
							isset($json->OperationMode) = $json->OperationMode : null,
							isset($json->Temperature) = $json->Temperature : null,
							isset($json->FanSpeed) = $json->FanSpeed : null,
							isset($json->VaneVertical) = $json->VaneVertical : null,
							isset($json->VaneHorizontal) = $json->VaneHorizontal : null);

					case 'GetDevices':
						return $this->GetDevices();			
				}
				break;
		}

		$response = array();

		return json_encode($response);
	}
}