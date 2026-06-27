<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * Universal interface every deployment driver must implement.
 *
 * The installation pipeline ONLY calls methods on this interface.
 * It never inspects DriverType directly — it queries capabilities().
 *
 * To add a new hosting type: implement this interface, declare capabilities(),
 * register in config/customer_installation.php. Pipeline is untouched.
 */
interface DeploymentDriverInterface extends RemoteConnectionInterface
{
    /**
     * Declare what this driver can do on the target hosting platform.
     * The pipeline uses this to enable/skip stages at runtime.
     * Never check DriverType in a stage — always check capabilities().
     */
    public function capabilities(): DriverCapabilities;

    /**
     * Validate the deployment target configuration without opening a connection.
     * Called before connect() to catch misconfiguration early.
     */
    public function validate(): ValidationResult;

    /**
     * Driver-level rollback hook invoked after a stage fails.
     * Stage-specific rollback logic lives in Stage::rollback().
     * This method handles any driver-level cleanup (e.g., closing sessions).
     *
     * @param string $stageName  The stage name that failed
     * @param array  $snapshot   Rollback snapshot persisted by the pipeline
     */
    public function rollbackStage(string $stageName, array $snapshot): void;

    /**
     * Return the canonical type identifier for this driver.
     * Used for logging, metrics, and UI display only — NOT for conditional logic.
     */
    public function driverType(): DriverType;
}
