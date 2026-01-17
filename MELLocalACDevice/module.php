<?php

require_once(__DIR__ . '/../libs/MitsubishiParser.php');

/**
 * MELLocalACDevice
 *
 * Device module for local Mitsubishi AC control
 * Controls a single AC unit via local network
 */
class MELLocalACDevice extends IPSModule {

    private $insId = 0;

    public function __construct($InstanceID) {
        parent::__construct($InstanceID);
        $this->insId = $InstanceID;
    }

    public function Create() {
        parent::Create();

        // Connect to parent splitter
        $this->ConnectParent("{B8E7A5D2-4F3C-8A91-2E6D-9C1B7F4E8A3D}");

        // Properties
        $this->RegisterPropertyString("DeviceHost", "");
        $this->RegisterPropertyInteger("DevicePort", 80);
        $this->RegisterPropertyInteger("UpdateInterval", 10);

        // Create variable profiles
        $this->CreateProfiles();

        // Register variables (compatible with MELCloudACDevice)
        $this->RegisterVariableBoolean("Power", $this->Translate("t_power"), "~Switch", 10);
        $this->RegisterVariableInteger("OperationMode", $this->Translate("t_work_mode"), "MELLOCALACDEV.".$this->insId.".WorkMode", 15);
        $this->RegisterVariableFloat("Temperature", $this->Translate("t_temp"), "~Temperature.Room", 20);
        $this->RegisterVariableFloat("RoomTemperature", $this->Translate("f_temp_in"), "~Temperature.Room", 30);
        $this->RegisterVariableFloat("OutsideTemperature", $this->Translate("f_temp_out"), "~Temperature.Room", 35);
        $this->RegisterVariableInteger("FanSpeed", $this->Translate("t_fan_speed"), "MELLOCALACDEV.".$this->insId.".FanSpeed", 40);
        $this->RegisterVariableInteger("VaneVertical", $this->Translate("VaneVertical"), "MELLOCALACDEV.".$this->insId.".VaneVertical", 50);
        $this->RegisterVariableInteger("VaneHorizontal", $this->Translate("VaneHorizontal"), "MELLOCALACDEV.".$this->insId.".VaneHorizontal", 60);
        
        // Energy monitoring variables
        $this->RegisterVariableInteger("PowerWatt", $this->Translate("PowerWatt"), "~Watt", 70);
        $this->RegisterVariableBoolean("Operating", $this->Translate("Operating"), "~Switch", 75);
        $this->RegisterVariableFloat("EnergyConsumption", $this->Translate("t_energy_consumption"), "~Electricity", 80);

        // Enable actions
        $this->EnableAction("Power");
        $this->EnableAction("OperationMode");
        $this->EnableAction("Temperature");
        $this->EnableAction("FanSpeed");
        $this->EnableAction("VaneVertical");
        $this->EnableAction("VaneHorizontal");

        // Timer for periodic updates
        $this->RegisterTimer("UpdateTimer", 0, 'MELLOCALACDEV_Update($_IPS[\'TARGET\']);');
    }

    public function Destroy() {
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return parent::Destroy();
        }
        if (!IPS_InstanceExists($this->insId)) {
            // Delete profiles
            $this->UnregisterProfile("MELLOCALACDEV.".$this->insId.".WorkMode");
            $this->UnregisterProfile("MELLOCALACDEV.".$this->insId.".FanSpeed");
            $this->UnregisterProfile("MELLOCALACDEV.".$this->insId.".VaneHorizontal");
            $this->UnregisterProfile("MELLOCALACDEV.".$this->insId.".VaneVertical");
        }
        parent::Destroy();
    }

    public function ApplyChanges() {
        parent::ApplyChanges();

        // Set update timer based on property
        if ($this->ReadPropertyInteger("UpdateInterval") > 0) {
            $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateInterval") * 1000);
        } else {
            $this->SetTimerInterval("UpdateTimer", 0);
        }
    }

    /**
     * Create variable profiles
     */
    private function CreateProfiles() {
        $workModeProfile = "MELLOCALACDEV.".$this->insId.".WorkMode";
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

        $fanSpeedProfile = "MELLOCALACDEV.".$this->insId.".FanSpeed";
        if(!IPS_VariableProfileExists($fanSpeedProfile)) {
            IPS_CreateVariableProfile($fanSpeedProfile, 1);
            IPS_SetVariableProfileValues($fanSpeedProfile, 0, 6, 0);
            IPS_SetVariableProfileIcon($fanSpeedProfile, "Ventilation");
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 0, $this->Translate("auto"), "", 0x00FF00);
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 1, "1", "", 0xC0C0C0);
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 2, "2", "", 0xC0C0C0);
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 3, "3", "", 0xC0C0C0);
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 5, "4", "", 0xC0C0C0);
            IPS_SetVariableProfileAssociation($fanSpeedProfile, 6, "5", "", 0xC0C0C0);
        }

        $vaneHProfile = "MELLOCALACDEV.".$this->insId.".VaneHorizontal";
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

        $vaneVProfile = "MELLOCALACDEV.".$this->insId.".VaneVertical";
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
    }

    /**
     * Handle user actions
     */
    public function RequestAction($Ident, $Value) {
        $this->SendDebug("RequestAction", "Ident: {$Ident}, Value: {$Value}", 0);

        // Get current state
        $currentState = $this->GetCurrentState();

        // Determine which control flag to set
        $controls = 0;
        switch($Ident) {
            case 'Power':
                $currentState['power'] = $Value ? MitsubishiParser::POWER_ON : MitsubishiParser::POWER_OFF;
                $controls = MitsubishiParser::CONTROL_POWER;
                break;
            case 'OperationMode':
                $currentState['mode'] = $Value;
                $controls = MitsubishiParser::CONTROL_MODE;
                break;
            case 'Temperature':
                $currentState['temperature'] = $Value;
                $currentState['fine_temperature'] = $Value;
                $controls = MitsubishiParser::CONTROL_TEMPERATURE;
                break;
            case 'FanSpeed':
                $currentState['wind_speed'] = $Value;
                $controls = MitsubishiParser::CONTROL_WIND_SPEED;
                break;
            case 'VaneVertical':
                $currentState['vane_vertical'] = $Value;
                $controls = MitsubishiParser::CONTROL_VANE_VERTICAL;
                break;
            case 'VaneHorizontal':
                $currentState['vane_horizontal'] = $Value;
                $controls = MitsubishiParser::CONTROL_VANE_HORIZONTAL;
                break;
            default:
                $this->SendDebug("RequestAction", "Unknown Ident: {$Ident}", 0);
                return;
        }

        // Generate command
        $hexCommand = MitsubishiParser::generateCommand($currentState, $controls);

        // Send command to parent
        $data = [
            "DataID" => "{D1E8C5F4-6A3B-9C72-4E5D-7B9A1F3E2C8D}",
            "command" => "SendCommand",
            "DeviceHost" => $this->ReadPropertyString("DeviceHost"),
            "DevicePort" => $this->ReadPropertyInteger("DevicePort"),
            "HexCommand" => $hexCommand
        ];

        $result = $this->SendDataToParent(json_encode($data));
        $this->SendDebug("RequestAction", "Result: " . $result, 0);

        // Update local variable immediately
        $this->SetValue($Ident, $Value);

        // Schedule update to get actual state
        $this->SetTimerInterval("UpdateTimer", 2000); // Update after 2 seconds
    }

    /**
     * Update device status
     */
    public function Update() {
        $host = $this->ReadPropertyString("DeviceHost");
        if (empty($host)) {
            $this->SendDebug("Update", "No device host configured", 0);
            return;
        }

        $this->SendDebug("Update", "Updating device status", 0);

        // Request status from parent
        $data = [
            "DataID" => "{D1E8C5F4-6A3B-9C72-4E5D-7B9A1F3E2C8D}",
            "command" => "GetStatus",
            "DeviceHost" => $host,
            "DevicePort" => $this->ReadPropertyInteger("DevicePort")
        ];

        $result = $this->SendDataToParent(json_encode($data));
        $this->SendDebug("Update", "Result: " . $result, 0);

        $state = json_decode($result, true);

        if (isset($state['error'])) {
            $this->LogMessage("Update Error: " . $state['error'], KL_ERROR);
            $this->SetTimerInterval("UpdateTimer", 60000); // Retry in 60 seconds
            return;
        }

        // Update variables from state
        if (isset($state['general'])) {
            $general = $state['general'];
            $this->SetValue("Power", $general['power'] == MitsubishiParser::POWER_ON);
            $this->SetValue("OperationMode", $general['mode']);
            
            // Use fine temperature if available, otherwise coarse
            $temp = isset($general['fine_temperature']) && $general['fine_temperature'] !== null 
                ? $general['fine_temperature'] 
                : $general['temperature'];
            $this->SetValue("Temperature", $temp);
            
            $this->SetValue("FanSpeed", $general['wind_speed']);
            $this->SetValue("VaneVertical", $general['vane_vertical']);
            $this->SetValue("VaneHorizontal", $general['vane_horizontal']);
        }

        if (isset($state['sensors'])) {
            $sensors = $state['sensors'];
            $this->SetValue("RoomTemperature", $sensors['room_temperature']);
            $this->SetValue("OutsideTemperature", $sensors['outside_temperature']);
        }

        if (isset($state['energy'])) {
            $energy = $state['energy'];
            $this->SetValue("PowerWatt", $energy['power_watt']);
            $this->SetValue("Operating", $energy['operating']);
            
            // Update energy consumption (convert from hecto-wh to kWh)
            if (isset($energy['energy_hecto_wh'])) {
                $energyKwh = $energy['energy_hecto_wh'] / 10;
                $this->SetValue("EnergyConsumption", $energyKwh);
            }
        }

        // Reset timer to normal interval
        $this->SetTimerInterval("UpdateTimer", 60000); // 60 seconds
    }

    /**
     * Get current state from variables
     */
    private function GetCurrentState() {
        return [
            'power' => $this->GetValue("Power") ? MitsubishiParser::POWER_ON : MitsubishiParser::POWER_OFF,
            'mode' => $this->GetValue("OperationMode"),
            'temperature' => $this->GetValue("Temperature"),
            'fine_temperature' => $this->GetValue("Temperature"),
            'wind_speed' => $this->GetValue("FanSpeed"),
            'vane_vertical' => $this->GetValue("VaneVertical"),
            'vane_horizontal' => $this->GetValue("VaneHorizontal")
        ];
    }

    public function GetConfigurationForm() {
        $form = '{
            "elements": [
                {
                    "type": "Label",
                    "label": "Local Mitsubishi AC Device"
                },
                {
                    "type": "ValidationTextBox",
                    "name": "DeviceHost",
                    "caption": "Device IP Address"
                },
                {
                    "type": "NumberSpinner",
                    "name": "DevicePort",
                    "caption": "Device Port",
                    "minimum": 1,
                    "maximum": 65535
                },
                {
                    "type": "NumberSpinner",
                    "name": "UpdateInterval",
                    "caption": "Update Interval (seconds)",
                    "minimum": 0,
                    "suffix": "seconds"
                }
            ],
            "actions": [
                {
                    "type": "Button",
                    "label": "Update Status",
                    "onClick": "MELLOCALACDEV_Update($id);"
                }
            ],
            "status": []
        }';
        return $form;
    }

    /**
     * Delete variable profile if not used elsewhere
     */
    protected function UnregisterProfile(string $Name) {
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
