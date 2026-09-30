<?php
/*
 * Dzvin PBX - free phone system for small business
 * Copyright © 2017-2026 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleGeoIP\bin;
require_once 'Globals.php';

use DzvinPBX\Common\Handlers\CriticalErrorsHandler;
use DzvinPBX\Core\System\Processes;
use DzvinPBX\Core\System\Util;
use DzvinPBX\Modules\PbxExtensionUtils;
use Modules\ModuleGeoIP\Lib\GeoIPCountryLookup;
use Modules\ModuleGeoIP\Lib\GeoIPSetManager;
use Modules\ModuleGeoIP\Lib\DBIPDataProvider;
use Modules\ModuleGeoIP\Lib\RIRDataProvider;
use Modules\ModuleGeoIP\Models\GeoFilterCountries;
use Modules\ModuleGeoIP\Models\ModuleGeoIP;
use Phalcon\Di\Di;

/**
 * One-shot CIDR updater.
 *
 * Invoked directly from cron (weekly) or from the REST "updateNow" action.
 * Downloads the configured data source, rebuilds ipset sets, reloads iptables,
 * writes progress/last-update metadata and exits. A PID-based guard prevents
 * overlapping executions when an update is already running.
 */
class WorkerGeoIPUpdater
{
    public const PROC_TITLE = 'ModuleGeoIPUpdater';

    private bool $interrupted = false;

    /**
     * Entry point.
     */
    public function run(): int
    {
        // Abort gracefully on SIGTERM/SIGINT
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->interrupted = true);
            pcntl_signal(SIGINT,  fn() => $this->interrupted = true);
        }

        if (!PbxExtensionUtils::isEnabled('ModuleGeoIP')) {
            Util::sysLogMsg(__CLASS__, 'Module disabled, skipping update');
            return 0;
        }

        $dataDir = GeoIPCountryLookup::getDataDir();
        Util::mwMkdir($dataDir);
        if (!is_dir($dataDir) || !is_writable($dataDir)) {
            Util::sysLogMsg(__CLASS__, "Data directory $dataDir is not writable, aborting");
            return 1;
        }

        Util::sysLogMsg(__CLASS__, 'Starting CIDR data update');
        $this->clearUpdateRequestedFlag();

        $settings   = ModuleGeoIP::findFirst();
        $dataSource = ($settings !== null) ? ($settings->dataSource ?? 'dbip') : 'dbip';

        if ($dataSource === 'rir') {
            RIRDataProvider::downloadAndBuild(
                $dataDir,
                fn(int $p) => $this->updateProgress($p),
                fn()       => $this->interrupted
            );
        } else {
            DBIPDataProvider::downloadAndBuild(
                $dataDir,
                fn(int $p) => $this->updateProgress($p),
                fn()       => $this->interrupted
            );
        }

        if ($this->interrupted) {
            Util::sysLogMsg(__CLASS__, 'Update interrupted');
            $this->clearProgress();
            return 130;
        }

        $blockedCodes = $this->getBlockedCodes();
        if (!empty($blockedCodes)) {
            GeoIPSetManager::rebuildSets($blockedCodes, $dataDir);

            $allowedCodes = $this->getAllowedCodes();
            if (!empty($allowedCodes)) {
                GeoIPSetManager::rebuildAllowSets($allowedCodes, $dataDir);
            }

            $this->reloadFirewall();
        }

        $settings = ModuleGeoIP::findFirst();
        if ($settings === null) {
            $settings = new ModuleGeoIP();
        }
        $settings->lastUpdate = date('c');
        $settings->save();

        $this->clearProgress();
        Util::sysLogMsg(__CLASS__, 'CIDR data update completed');
        return 0;
    }

    /**
     * Detect if another updater process is already running by looking at process titles.
     */
    public static function isAlreadyRunning(): bool
    {
        $pid = Processes::getPidOfProcess(self::PROC_TITLE, (string)getmypid());
        return !empty($pid);
    }
}

// Bootstrap: run the updater once when invoked as a CLI script.
if (isset($argv) && basename($argv[0] ?? '') === basename(__FILE__)) {
    cli_set_process_title(WorkerGeoIPUpdater::PROC_TITLE);

    if (WorkerGeoIPUpdater::isAlreadyRunning()) {
        Util::sysLogMsg(WorkerGeoIPUpdater::class, 'Another update is already running, exiting');
        exit(0);
    }

    try {
        $exitCode = (new WorkerGeoIPUpdater())->run();
        exit($exitCode);
    } catch (\Throwable $e) {
        CriticalErrorsHandler::handleExceptionWithSyslog($e);
        exit(1);
    }
}
