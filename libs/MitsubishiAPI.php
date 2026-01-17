<?php

require_once(__DIR__ . '/MitsubishiCrypto.php');

/**
 * Mitsubishi AC HTTP API Handler
 * 
 * Handles HTTP communication with Mitsubishi AC devices
 * Based on pymitsubishi/mitsubishi_api.py
 */
class MitsubishiAPI {
    private $deviceHost;
    private $devicePort;
    private $crypto;
    private $adminUsername;
    private $adminPassword;
    
    public function __construct($deviceHost, $devicePort = 80, $encryptionKey = "unregistered") {
        $this->deviceHost = $deviceHost;
        $this->devicePort = $devicePort;
        $this->crypto = new MitsubishiCrypto($encryptionKey);
        $this->adminUsername = "admin";
        $this->adminPassword = "me1debug@0567";
    }
    
    /**
     * Make HTTP request to /smart endpoint
     * 
     * @param string $payloadXml XML payload to send
     * @return string Decrypted response XML
     */
    public function makeRequest($payloadXml) {
        // Encrypt the XML payload
        $encryptedPayload = $this->crypto->encrypt($payloadXml);
        
        // Create the full XML request body
        $requestBody = '<?xml version="1.0" encoding="UTF-8"?><ESV>' . $encryptedPayload . '</ESV>';
        
        // Prepare HTTP request
        $url = "http://{$this->deviceHost}:{$this->devicePort}/smart";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: ' . $this->deviceHost . ':' . $this->devicePort,
            'Content-Type: text/plain;charset=UTF-8',
            'Connection: keep-alive',
            'Accept: */*',
            'User-Agent: KirigamineRemote/5.1.0 (jp.co.MitsubishiElectric.KirigamineRemote; build:3; iOS 17.5.1) Alamofire/5.9.1',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("HTTP request failed: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP request failed with code: " . $httpCode);
        }
        
        // Parse response XML
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new Exception("Failed to parse response XML");
        }
        
        $encryptedResponse = (string)$xml;
        if (empty($encryptedResponse)) {
            throw new Exception("Empty response from device");
        }
        
        // Decrypt response
        return $this->crypto->decrypt($encryptedResponse);
    }
    
    /**
     * Send status request to get current device state
     * 
     * @return string Decrypted response XML
     */
    public function sendStatusRequest() {
        return $this->makeRequest('<CSV><CONNECT>ON</CONNECT></CSV>');
    }
    
    /**
     * Send hex command to device
     * 
     * @param string $hexCommand Hex command string
     * @return string Decrypted response XML
     */
    public function sendHexCommand($hexCommand) {
        $payloadXml = "<CSV><CONNECT>ON</CONNECT><CODE><VALUE>{$hexCommand}</VALUE></CODE></CSV>";
        return $this->makeRequest($payloadXml);
    }
    
    /**
     * Get unit information from admin interface
     * 
     * @return array Unit information
     */
    public function getUnitInfo() {
        $url = "http://{$this->deviceHost}:{$this->devicePort}/unitinfo";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->adminUsername . ':' . $this->adminPassword);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return [];
        }
        
        return $this->parseUnitInfoHTML($response);
    }
    
    /**
     * Parse unit info HTML response
     * 
     * @param string $html HTML content
     * @return array Parsed unit information
     */
    private function parseUnitInfoHTML($html) {
        $unitInfo = [];
        $section = "";
        
        // Parse sections and key-value pairs
        preg_match_all('/<div class="titleA">([^<]*)<\/div>|<dt>([^<]+)<\/dt>\s*<dd>([^<]+)<\/dd>/', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                // Section header
                $section = $match[1];
                $unitInfo[$section] = [];
            } elseif (!empty($match[2])) {
                // Key-value pair
                $unitInfo[$section][$match[2]] = $match[3];
            }
        }
        
        return $unitInfo;
    }
}
