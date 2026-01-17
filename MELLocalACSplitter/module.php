<?php

require_once(__DIR__ . '/../libs/MitsubishiAPI.php');
require_once(__DIR__ . '/../libs/MitsubishiParser.php');

/**
 * MELLocalACSplitter
 * 
 * Splitter module for local Mitsubishi AC control
 * Manages communication with multiple local AC devices
 */
class MELLocalACSplitter extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        // Properties
        $this->RegisterPropertyString("EncryptionKey", "unregistered");
        $this->RegisterPropertyInteger("UpdateInterval", 60);
    }
    
    public function ApplyChanges() {
        parent::ApplyChanges();
        
        // Set receive data filter for child devices
        $this->SetReceiveDataFilter(".*\"DataID\":\"\\{D1E8C5F4-6A3B-9C72-4E5D-7B9A1F3E2C8D\\}\".*");
    }
    
    public function GetConfigurationForm() {
        $form = '{
            "elements": [
                {
                    "type": "Label",
                    "label": "Local Mitsubishi AC Splitter"
                },
                {
                    "type": "ValidationTextBox",
                    "name": "EncryptionKey",
                    "caption": "Encryption Key"
                },
                {
                    "type": "NumberSpinner",
                    "name": "UpdateInterval",
                    "caption": "Update Interval (seconds)",
                    "minimum": 10,
                    "maximum": 3600
                }
            ],
            "actions": [],
            "status": []
        }';
        return $form;
    }
    
    /**
     * Forward data from child devices
     */
    public function ForwardData($JSONString) {
        $data = json_decode($JSONString, true);
        
        $this->SendDebug("ForwardData", "Received: " . $JSONString, 0);
        
        if (!isset($data['command'])) {
            return json_encode(['error' => 'No command specified']);
        }
        
        switch($data['command']) {
            case 'GetStatus':
                return $this->getDeviceStatus($data['DeviceHost'], $data['DevicePort']);
                
            case 'SendCommand':
                return $this->sendDeviceCommand(
                    $data['DeviceHost'], 
                    $data['DevicePort'], 
                    $data['HexCommand']
                );
                
            default:
                return json_encode(['error' => 'Unknown command: ' . $data['command']]);
        }
    }
    
    /**
     * Get device status
     * 
     * @param string $host Device IP address or hostname
     * @param int $port Device port
     * @return string JSON encoded device state
     */
    private function getDeviceStatus($host, $port) {
        try {
            $this->SendDebug("GetStatus", "Requesting status from {$host}:{$port}", 0);
            
            $api = new MitsubishiAPI($host, $port, $this->ReadPropertyString("EncryptionKey"));
            $response = $api->sendStatusRequest();
            
            $this->SendDebug("GetStatus", "Response XML: " . $response, 0);
            
            $state = MitsubishiParser::parseCodeValues($response);
            
            $this->SendDebug("GetStatus", "Parsed state: " . json_encode($state), 0);
            
            return json_encode($state);
        } catch (Exception $e) {
            $this->SendDebug("GetStatus Error", $e->getMessage(), 0);
            $this->LogMessage("GetStatus Error: " . $e->getMessage(), KL_ERROR);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Send command to device
     * 
     * @param string $host Device IP address or hostname
     * @param int $port Device port
     * @param string $hexCommand Hex command string
     * @return string JSON encoded device state
     */
    private function sendDeviceCommand($host, $port, $hexCommand) {
        try {
            $this->SendDebug("SendCommand", "Sending command to {$host}:{$port} - Command: {$hexCommand}", 0);
            
            $api = new MitsubishiAPI($host, $port, $this->ReadPropertyString("EncryptionKey"));
            $response = $api->sendHexCommand($hexCommand);
            
            $this->SendDebug("SendCommand", "Response XML: " . $response, 0);
            
            $state = MitsubishiParser::parseCodeValues($response);
            
            $this->SendDebug("SendCommand", "Parsed state: " . json_encode($state), 0);
            
            return json_encode($state);
        } catch (Exception $e) {
            $this->SendDebug("SendCommand Error", $e->getMessage(), 0);
            $this->LogMessage("SendCommand Error: " . $e->getMessage(), KL_ERROR);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
