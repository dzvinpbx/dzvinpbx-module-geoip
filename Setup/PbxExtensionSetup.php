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

namespace Modules\ModuleGeoIP\Setup;

use DzvinPBX\Common\Models\PbxSettings;
use DzvinPBX\Modules\Setup\PbxExtensionSetupBase;
use Modules\ModuleGeoIP\Models\GeoFilterCountries;

class PbxExtensionSetup extends PbxExtensionSetupBase
{
    /**
     * Countries blocked by default on a fresh install: Russia, China, Iran.
     * Ukraine must never appear in this list.
     */
    public const DEFAULT_BLOCKED_COUNTRIES = ['RU', 'CN', 'IR'];

    /**
     * Install module database tables and register the module.
     */
    public function installDB(): bool
    {
        $result = $this->createSettingsTableByModelsAnnotations();
        if ($result) {
            $result = $this->seedDefaultBlockedCountries();
        }
        if ($result) {
            $result = $this->registerNewModule();
        }
        if ($result) {
            $result = $this->addToSidebar();
        }
        return $result;
    }

    /**
     * Adds the module to the sidebar menu
     */
    public function addToSidebar(): bool
    {
        $menuSettingsKey = "AdditionalMenuItem{$this->moduleUniqueID}";
        $menuSettings = PbxSettings::findFirstByKey($menuSettingsKey);
        if ($menuSettings === null) {
            $menuSettings = new PbxSettings();
            $menuSettings->key = $menuSettingsKey;
        }
        $value = [
            'uniqid'        => $this->moduleUniqueID,
            'group'         => 'networkSettings',
            'iconClass'     => 'globe',
            'caption'       => "Breadcrumb{$this->moduleUniqueID}",
            'showAtSidebar' => true,
        ];
        $menuSettings->value = json_encode($value);
        return $menuSettings->save();
    }

    /**
     * Pre-select the default blocked countries when the module is installed
     * for the first time. An existing country list is never touched, so
     * reinstalling or upgrading keeps the administrator's choice.
     */
    private function seedDefaultBlockedCountries(): bool
    {
        if (GeoFilterCountries::count() > 0) {
            return true;
        }
        foreach (self::DEFAULT_BLOCKED_COUNTRIES as $countryCode) {
            if ($countryCode === 'UA') {
                continue;
            }
            $record = new GeoFilterCountries();
            $record->country_code = $countryCode;
            $record->blocked = '1';
            if (!$record->save()) {
                return false;
            }
        }
        return true;
    }
}
