# GeoIP Filter Module for Dzvin PBX

[![GitHub release](https://img.shields.io/github/v/release/dzvinpbx/dzvinpbx-module-geoip)](https://github.com/dzvinpbx/dzvinpbx-module-geoip/releases)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

**[Українською](README.uk.md)** | **English**

The module blocks incoming connections to your PBX from selected countries, which protects against SIP scanners, password brute-forcing and unwanted calls from blocked regions.

> This is a Dzvin PBX fork of the MikoPBX **ModuleGeoIP** module - see [Origin](#origin).

## Default blocked countries

On a fresh install the module pre-selects **Russia (RU), China (CN) and Iran (IR)** for blocking. Ukraine is never blocked by default and is listed first in the country list. Change the selection in the module UI; reinstalling or upgrading keeps a saved selection. Filtering takes effect once the module is enabled.

## Features

- Block by country (all 249 ISO 3166-1 countries), IPv4 and IPv6
- Search and status filter, block/unblock all
- Weekly automatic update of the address lists, "Update now" with progress
- Your firewall rules and SIP provider addresses take precedence over the GeoIP block, so trusted addresses are never blocked

## How it works

Blocking uses kernel `ipset` sets and DROP rules placed after all allow rules of the PBX firewall: established connections, your firewall subnets and SIP provider addresses are passed first, then the GeoIP filter is applied.

## Requirements

- Dzvin PBX 2026.1.223 or newer
- `ipset` support in the Linux kernel

## Data sources

No MIKO-hosted services, account or licence key are needed:

- **DB-IP Lite (country)** - default. A copy is bundled (`db/dbip-country-lite.csv.gz`), so initial data works offline; a newer one is fetched from `download.db-ip.com` when the network is available. Licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) - **IP Geolocation by [DB-IP](https://db-ip.com)** (shown in the module UI).
- **RIR delegation files** - public statistics files of the regional internet registries.

The `ipdeny.com` source of the original module was removed because its data has no explicit open licence. MaxMind GeoLite2 (account, licence key and its own EULA) is not used. To refresh the bundled copy run `scripts/update-offline-db.sh`.

## License

GPL-3.0-or-later - see [LICENSE](LICENSE). DB-IP Lite data is CC BY 4.0.

## Origin

Based on [`mikopbx/ModuleGeoIP`](https://github.com/mikopbx/ModuleGeoIP) `v1.2` (commit `c98679e`), (c) 2017-2026 Alexey Portnov and Nikolay Beketov, GPL-3.0. The fork renames the PBX core namespace the module depends on (`MikoPBX\` to `DzvinPBX\`), sets the default blocked countries (RU, CN, IR), drops the `ipdeny.com` source, completes the Ukrainian UI translation and adapts the release process. Original copyright headers are kept in every source file. The core this module is built for is [MikoPBX Core](https://github.com/mikopbx/Core).
