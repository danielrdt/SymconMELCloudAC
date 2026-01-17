<?php

/**
 * MELLocalACConfigurator
 * 
 * Configurator module for local Mitsubishi AC devices
 * Provides manual configuration interface for creating device instances
 */
class MELLocalACConfigurator extends IPSModule {

    private $insId = 0;

    public function __construct($InstanceID) {
        parent::__construct($InstanceID);
        $this->insId = $InstanceID;
    }

    public function Create() {
        parent::Create();

        // Connect to parent splitter
        $this->ConnectParent("{B8E7A5D2-4F3C-8A91-2E6D-9C1B7F4E8A3D}");
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
    }

    /**
     * Get configuration form with manual device configuration
     */
    public function GetConfigurationForm() {
        $SplitterID = $this->GetSplitter();
        
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        
        // Check if splitter is connected
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
            return json_encode($Form);
        }
        
        // Check if splitter is active
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
            return json_encode($Form);
        }

        // Get existing device instances
        $Values = $this->GetDeviceList($SplitterID);
        
        // Build configuration form
        $Form = [
            'elements' => [
                [
                    'type' => 'Label',
                    'label' => $this->Translate('Manual Device Configuration')
                ],
                [
                    'type' => 'Label',
                    'label' => $this->Translate('Enter IP addresses of your Mitsubishi AC devices to create instances.')
                ]
            ],
            'actions' => [
                [
                    'type' => 'Configurator',
                    'name' => 'DeviceConfigurator',
                    'caption' => $this->Translate('Available Devices'),
                    'rowCount' => 10,
                    'add' => true,
                    'delete' => false,
                    'sort' => [
                        'column' => 'name',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'caption' => $this->Translate('Device Name'),
                            'name' => 'name',
                            'width' => '300px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Device IP Address'),
                            'name' => 'host',
                            'width' => '200px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Port'),
                            'name' => 'port',
                            'width' => '100px',
                            'add' => 80,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => $this->Translate('Location'),
                            'name' => 'location',
                            'width' => 'auto'
                        ]
                    ],
                    'values' => $Values
                ]
            ],
            'status' => []
        ];
        
        return json_encode($Form);
    }

    /**
     * Get list of existing and potential device instances
     */
    private function GetDeviceList($SplitterID) {
        $Values = [];
        
        // Get all existing MELLocalACDevice instances
        $InstanceIDs = IPS_GetInstanceListByModuleID('{C9F6B4E3-5D2A-7B81-3F4E-8A2C6D9E1B5F}');
        
        foreach ($InstanceIDs as $InstanceID) {
            // Only include instances connected to this splitter
            if (IPS_GetInstance($InstanceID)['ConnectionID'] == $SplitterID) {
                $host = IPS_GetProperty($InstanceID, 'DeviceHost');
                $port = IPS_GetProperty($InstanceID, 'DevicePort');
                
                $Value = [
                    'instanceID' => $InstanceID,
                    'name' => IPS_GetName($InstanceID),
                    'host' => $host,
                    'port' => $port,
                    'location' => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true),
                    'create' => [
                        'moduleID' => '{C9F6B4E3-5D2A-7B81-3F4E-8A2C6D9E1B5F}',
                        'configuration' => [
                            'DeviceHost' => $host,
                            'DevicePort' => $port
                        ]
                    ]
                ];
                
                $Values[] = $Value;
            }
        }
        
        // Add template for new device
        if (count($Values) == 0) {
            $Values[] = [
                'name' => $this->Translate('New Device'),
                'host' => '',
                'port' => 80,
                'location' => '',
                'create' => [
                    'moduleID' => '{C9F6B4E3-5D2A-7B81-3F4E-8A2C6D9E1B5F}',
                    'configuration' => [
                        'DeviceHost' => '',
                        'DevicePort' => 80
                    ]
                ]
            ];
        }
        
        return $Values;
    }

    /**
     * Get the connected splitter instance
     */
    private function GetSplitter() {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
    }
}
