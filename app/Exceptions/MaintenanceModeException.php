<?php

namespace App\Exceptions;

use Exception;

class MaintenanceModeException extends Exception
{
    protected $maintenanceData;

    public function __construct($message, $maintenanceData = [])
    {
        parent::__construct($message);
        $this->maintenanceData = $maintenanceData;
    }

    public function getMaintenanceData()
    {
        return $this->maintenanceData;
    }
}

class EmergencyModeException extends Exception
{
    protected $emergencyData;

    public function __construct($message, $emergencyData = [])
    {
        parent::__construct($message);
        $this->emergencyData = $emergencyData;
    }

    public function getEmergencyData()
    {
        return $this->emergencyData;
    }
}