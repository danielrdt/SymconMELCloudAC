<?
// Klassendefinition
class MELCloudACConfigurator extends IPSModule {

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

		$this->ConnectParent("{A0D2B115-36FD-ED99-7D09-85DB842A968B}");
	}

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}

	public function GetConfigurationForm(){
		$SplitterID = $this->GetSplitter();
        if ($SplitterID === false) {
            $Form['actions'][] = [
                "type"  => "PopupAlert",
                "popup" => [
                    "items" => [[
                    "type"    => "Label",
                    "caption" => "Not connected to Splitter."
                        ]]
                ]
            ];
		}
		
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetInstance($SplitterID)['InstanceStatus'] != IS_ACTIVE) {
            $Form['actions'][] = [
                "type"  => "PopupAlert",
                "popup" => [
                    "items" => [[
                    "type"    => "Label",
                    "caption" => "Instance has no active parent."
                        ]]
                ]
            ];
		}

		$data = $this->GetDevices();
		
        foreach ($data as $location) {
            foreach ($location->Structure->Devices as $device){
                $Value = [
				    'name'  => $location->Name." ".$device->DeviceName,
				    'mac'	=> $device->MacAddress,
                    'create' => [
                        'moduleID'      => '{4A8FB964-3062-7CD8-A472-0B3549A73E1C}',
                        'configuration' => ['DeviceId' => $device->DeviceID, 'BuildingId' => $location->ID]
                    ]
                ];
                $InstanzID = $this->SearchDeviceInstance($SplitterID, '{4A8FB964-3062-7CD8-A472-0B3549A73E1C}', $device->DeviceID);
                if ($InstanzID == false) {
                    $Value['location'] = '';
                } else {
                    $Value['name'] = IPS_GetName($InstanzID);
                    $Value['location'] = stristr(IPS_GetLocation($InstanzID), IPS_GetName($InstanzID), true);
                    $Value['instanceID'] = $InstanzID;
                }
                $Values[] = $Value;
            }
        }
        
		$Form['actions'][0]['values'] = $Values;
		
        return json_encode($Form);
	}

	private function GetDevices(){
		$data = array(
			"DataID" => "{3E126AF2-7CFE-0527-1DD7-13730E1706D4}",
			"command" => "GetDevices"
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);

		$jsonData = json_decode($result);

		return $jsonData;
    }

	/**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @access private
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetSplitter()
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
	}
	
    private function SearchDeviceInstance(int $SplitterID, string $ModuleID, int $DeviceKey)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID($ModuleID);
        foreach ($InstanceIDs as $InstanceID) {
            if (IPS_GetInstance($InstanceID)['ConnectionID'] == $SplitterID) {
                if (IPS_GetProperty($InstanceID, 'DeviceId') == $DeviceId) {
                    return $InstanceID;
                }
            }
        }
        return false;
    }
}