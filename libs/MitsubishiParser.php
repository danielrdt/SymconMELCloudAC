<?php

/**
 * Mitsubishi AC Protocol Parser
 * 
 * Parses device state from hex payloads and generates commands
 * Based on pymitsubishi/mitsubishi_parser.py
 */
class MitsubishiParser {
    // Power states
    const POWER_OFF = 0;
    const POWER_ON = 1;
    
    // Drive modes
    const MODE_AUTO = 8;
    const MODE_HEATER = 1;
    const MODE_COOLER = 3;
    const MODE_DEHUM = 2;
    const MODE_FAN = 7;
    
    // Wind speeds
    const WIND_AUTO = 0;
    const WIND_S1 = 1;
    const WIND_S2 = 2;
    const WIND_S3 = 3;
    const WIND_S4 = 5;
    const WIND_FULL = 6;
    
    // Vertical wind direction
    const VANE_V_AUTO = 0;
    const VANE_V_1 = 1;
    const VANE_V_2 = 2;
    const VANE_V_3 = 3;
    const VANE_V_4 = 4;
    const VANE_V_5 = 5;
    const VANE_V_SWING = 7;
    
    // Horizontal wind direction
    const VANE_H_AUTO = 0;
    const VANE_H_FAR_LEFT = 1;
    const VANE_H_LEFT = 2;
    const VANE_H_CENTER = 3;
    const VANE_H_RIGHT = 4;
    const VANE_H_FAR_RIGHT = 5;
    const VANE_H_SWING = 12;
    
    // Control flags
    const CONTROL_POWER = 0x0100;
    const CONTROL_MODE = 0x0200;
    const CONTROL_TEMPERATURE = 0x0400;
    const CONTROL_WIND_SPEED = 0x0800;
    const CONTROL_VANE_VERTICAL = 0x1000;
    const CONTROL_VANE_HORIZONTAL = 0x0001;
    const CONTROL_OUTSIDE = 0x0002;
    
    /**
     * Parse code values from XML response
     * 
     * @param string $responseXml XML response from device
     * @return array Parsed device state
     */
    public static function parseCodeValues($responseXml) {
        $xml = simplexml_load_string($responseXml);
        if ($xml === false) {
            throw new Exception("Failed to parse response XML");
        }
        
        $state = [
            'general' => null,
            'sensors' => null,
            'energy' => null,
            'errors' => null,
            'mac' => '',
            'serial' => ''
        ];
        
        // Extract MAC and Serial
        if (isset($xml->MAC)) {
            $state['mac'] = (string)$xml->MAC;
        }
        if (isset($xml->SERIAL)) {
            $state['serial'] = (string)$xml->SERIAL;
        }
        
        // Parse CODE values
        foreach ($xml->xpath('//CODE/VALUE') as $value) {
            $hexValue = (string)$value;
            $data = hex2bin($hexValue);
            
            if ($data === false || strlen($data) < 6) {
                continue;
            }
            
            // Determine payload type by data[5]
            $payloadType = ord($data[5]);
            
            switch ($payloadType) {
                case 0x02: // General states
                    $state['general'] = self::parseGeneralStates($data);
                    break;
                case 0x03: // Sensor states
                    $state['sensors'] = self::parseSensorStates($data);
                    break;
                case 0x04: // Error states
                    $state['errors'] = self::parseErrorStates($data);
                    break;
                case 0x06: // Energy states
                    $state['energy'] = self::parseEnergyStates($data);
                    break;
            }
        }
        
        return $state;
    }
    
    /**
     * Parse general states (power, mode, temperature, etc.)
     * 
     * @param string $data Binary payload
     * @return array General state
     */
    public static function parseGeneralStates($data) {
        if (strlen($data) < 21) {
            throw new Exception("GeneralStates payload too short");
        }
        
        // Verify checksum
        $calculatedFcc = self::calculateFCC(substr($data, 1, 20));
        $receivedFcc = ord($data[21]);
        if ($calculatedFcc !== $receivedFcc) {
            throw new Exception("Invalid checksum");
        }
        
        $fineTemp = ord($data[16]) != 0 ? (ord($data[16]) - 0x80) / 2 : null;
        
        return [
            'power' => ord($data[8]),
            'mode' => ord($data[9]) & 0x07,
            'temperature' => 31 - ord($data[10]),
            'wind_speed' => ord($data[11]),
            'vane_vertical' => ord($data[12]),
            'vane_horizontal' => ord($data[15]) & 0x0F,
            'fine_temperature' => $fineTemp
        ];
    }
    
    /**
     * Parse sensor states (temperatures)
     * 
     * @param string $data Binary payload
     * @return array Sensor state
     */
    public static function parseSensorStates($data) {
        if (strlen($data) < 21) {
            throw new Exception("SensorStates payload too short");
        }
        
        return [
            'room_temperature' => (ord($data[11]) - 0x80) * 0.5,
            'outside_temperature' => (ord($data[10]) - 0x80) * 0.5
        ];
    }
    
    /**
     * Parse error states
     * 
     * @param string $data Binary payload
     * @return array Error state
     */
    public static function parseErrorStates($data) {
        if (strlen($data) < 11) {
            throw new Exception("ErrorStates payload too short");
        }
        
        $errorCode = (ord($data[9]) << 8) | ord($data[10]);
        
        return [
            'error_code' => $errorCode,
            'is_abnormal' => $errorCode !== 0x8000
        ];
    }
    
    /**
     * Parse energy states
     * 
     * @param string $data Binary payload
     * @return array Energy state
     */
    public static function parseEnergyStates($data) {
        if (strlen($data) < 14) {
            throw new Exception("EnergyStates payload too short");
        }
        
        return [
            'operating' => ord($data[9]) > 0,
            'power_watt' => (ord($data[10]) << 8) | ord($data[11]),
            'energy_hecto_wh' => (ord($data[12]) << 8) | ord($data[13])
        ];
    }
    
    /**
     * Generate command hex string
     * 
     * @param array $state Current state
     * @param int $controls Control flags
     * @return string Hex command string
     */
    public static function generateCommand($state, $controls) {
        $cmd = "\x41\x01\x30\x10\x01" . str_repeat("\x00", 15);
        
        // Add outside control flag
        $controls |= self::CONTROL_OUTSIDE;
        
        // Set control flags (bytes 5-6)
        $cmd[5] = chr(($controls >> 8) & 0xFF);
        $cmd[6] = chr($controls & 0xFF);
        
        // Set state values
        $cmd[7] = chr($state['power']);
        $cmd[8] = chr($state['mode']);
        $cmd[9] = chr(31 - intval($state['temperature']));
        $cmd[10] = chr($state['wind_speed']);
        $cmd[11] = chr($state['vane_vertical']);
        $cmd[17] = chr($state['vane_horizontal']);
        
        // Fine temperature
        if (isset($state['fine_temperature']) && $state['fine_temperature'] !== null) {
            $cmd[18] = chr(0x80 + intval($state['fine_temperature'] * 2));
        }
        
        $cmd[19] = "\x41";
        
        // Calculate and append FCC
        $fcc = self::calculateFCC($cmd);
        
        return bin2hex("\xFC" . $cmd . chr($fcc));
    }
    
    /**
     * Calculate FCC checksum
     * 
     * @param string $payload Binary payload
     * @return int Checksum byte
     */
    private static function calculateFCC($payload) {
        $sum = 0;
        for ($i = 0; $i < min(20, strlen($payload)); $i++) {
            $sum += ord($payload[$i]);
        }
        return (0x100 - ($sum % 0x100)) % 0x100;
    }
}
