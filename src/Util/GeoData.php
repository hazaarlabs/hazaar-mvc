<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Application\Runtime;
use Hazaar\File;
use Hazaar\File\BTree;

/**
 * The GeoData class for accessing geographic information on countries.
 *
 * This method allows access to useful country information such as codes, names, continents,
 * states, cities and various other data.
 *
 * Data is obtained via the publicly available GeoLite2 databases provided by MaxMind.
 *
 * See the [MaxMind GeoLite2 Pages](https://dev.maxmind.com/geoip/geoip2/geolite2/) for more information.
 *
 * <p class="notice">Currently, IP information is not stored and only country/state/city level data is
 * searchable.</p>
 *
 * <p class="notice warning">The first time the \Hazaar\GeoData class is used it needs to download some files
 * and construct an internal B-Tree database.  This requires HTTP access to the internet and depending
 * on the speed of the connection can take some time (usually around 10-15 seconds).  Once the B-Tree
 * database is constructed then data access speeds are extremely fast.</p>
 *
 * @author Jamie Carl <jamie@hazaar.io>
 */
class GeoData
{
    /**
     * The current GeoData database format version.
     *
     * Changing this triggers a re-initialisation of the internal database.
     */
    public const int VERSION = 2;

    /**
     * The publicly available GeoData database data sources.
     *
     * @var array<string,string>
     */
    private static array $sources = [
        'url' => 'https://api.hazaar.io/databases/geodata.zip',
        'md5' => 'https://api.hazaar.io/databases/geodata.zip.md5',
    ];

    /**
     * The internal B-Tree database adapter.
     */
    private static ?BTree $db = null;

    private static string $dbFile = 'geodata.db';

    public function __construct(?string $dbFile = null)
    {
        if (null === $dbFile) {
            $dbFile = Runtime::getInstance()->getPath(self::$dbFile);
        }
        $downloadDBFile = false;
        if (!file_exists($dbFile)) {
            $downloadDBFile = true;
        } else {
            $db = new BTree($dbFile, true);
            if (GeoData::VERSION === $db->get('__version__')) {
                self::$db = $db;
            } else {
                $downloadDBFile = true;
            }
        }
        if ($downloadDBFile) {
            $dbFile = self::fetchDBFile($dbFile);
            self::$db = new BTree($dbFile, true);
        }
    }

    /**
     * Obtains a list of all countries indexed by code.
     *
     * @return array<string,string>
     */
    private function __list(BTree $db, ?string $field = null): array
    {
        $list = [];
        $codes = $db->range("\x00", "\xff");
        foreach ($codes as $code => $info) {
            if ('__' == substr($code, 0, 2)) {
                continue;
            }
            $list[$code] = $field ? $info[$field] ?? null : $info;
        }
        asort($list);

        return $list;
    }

    /**
     * Fetch the GeoData database file from the remote API server and extract it.
     */
    public static function fetchDBFile(?string $dbFileName = 'geodata.db'): File
    {
        if (!class_exists('ZipArchive')) {
            throw new \Exception('The GeoData class requires the ZipArchive class to be available!');
        }
        $zip = new \ZipArchive();
        $geodataZIPFilename = Runtime::getInstance()->getPath(basename(GeoData::$sources['url']));
        if (!is_writable(dirname($geodataZIPFilename))) {
            throw new \Exception('GeoData file is not writable!');
        }
        $geodataZIPFile = new File($geodataZIPFilename);
        // Download the Hazaar GeoData file and check it's MD5 signature
        $geodataData = file_get_contents(GeoData::$sources['url']);
        if (false === $geodataData) {
            throw new \Exception('Unable to download GeoData source file!');
        }
        $geodataZIPFile->putContents($geodataData);
        unset($geodataData);
        if (!$geodataZIPFile->size() > 0) {
            throw new \Exception('Unable to download GeoData source file!');
        }
        $md5 = trim(file_get_contents(GeoData::$sources['md5']));
        if ($geodataZIPFile->md5() !== $md5) {
            throw new \Exception('GeoData source file MD5 signature does not match!');
        }
        $dir = new File\Dir(Runtime::getInstance()->getPath());
        $zip->open($geodataZIPFile->fullpath());
        $zip->extractTo((string) $dir);
        $geodataZIPFile->unlink(); // Cleanup now
        $dbFile = $dir->get('geodata.db');
        if (false === $dbFile->isFile()) {
            throw new \Exception('GeoData source file not found after extraction!');
        }
        if ($dbFile->basename() !== $dbFileName) {
            $dbFile->rename($dbFileName);
        }

        return $dbFile;
    }

    /**
     * Retrieve a list of countries.
     *
     * This method will return an associative array containing a list of all countries organised by their
     * two character ISO code.  The ISO code is the key and the country name is the value.
     *
     * @return array<string,string>
     */
    public function countries(): array
    {
        return $this->__list(self::$db, 'name');
    }

    /**
     * Retrieve information about a country by it's ISO code.
     *
     * This method will return an array that contains:
     * - id := The GeoNamesID
     * - code := Two character ISO country code
     * - name := Country name
     * - continent := Continent info containing the continent ISO code and name.
     * - phone_code := Two digit telephone dialing code (E164)
     * - languages := Array of languages used in this country
     * - capital := Name of the capital city
     * - capital_timezone := The timezone in the capital city
     * - area := Physical area in KM/2
     * - hosts := Estimated number of active internet hosts
     * - users := Estimated number of active internet users
     *
     * @param string $code the two character ISO country code to get information for
     *
     * @return array<string,mixed> an array of available country information
     */
    public function countryInfo(string $code): array
    {
        $info = self::$db->get(strtoupper($code));
        unset($info['states'], $info['cities']);

        return $info;
    }

    /**
     * Retrieve information about all countries.
     *
     * This method will return an array of all countries with their ISO code as the key and an array of country information as the value.
     *
     * @return array<mixed> an array of all countries with their ISO code as the key and an array of country information as the value
     */
    public function countryInfoAll(): array
    {
        return $this->__list(self::$db);
    }

    /**
     * Retrieve a list of states for a country.
     *
     * Using a two character ISO country code, this method will return a list of states for that country.
     *
     * @param string $countryCode Two character ISO country code
     *
     * @return array<string, string> an array of states with their state code as the key and full name as the value
     */
    public function states(string $countryCode): array
    {
        $list = [];
        if ($country = self::$db->get(strtoupper($countryCode))) {
            foreach ($country['states'] as $state) {
                if (!isset($state['code'], $state['name'])) {
                    continue;
                }
                $list[$state['code']] = $state['name'];
            }
            asort($list);
        }

        return $list;
    }

    /**
     * Retrieve a list of cities for the requested country and state.
     *
     * Using a two character ISO country code and the state code this method will return an
     * array of cities in that state.
     *
     * @param string $countryCode two character ISO country code
     * @param string $stateCode   state code
     *
     * @return array<string> an array of city names
     */
    public function cities(string $countryCode, string $stateCode): array
    {
        $list = [];
        if ($country = self::$db->get(strtoupper($countryCode))) {
            $cities = ($country['states'][$stateCode]['cities'] ?? []);
            foreach ($cities as $id) {
                if (!($city = $country['cities'][$id])) {
                    continue;
                }
                $list[] = $city['name'];
            }
            sort($list);
        }

        return $list;
    }

    /**
     * Quick access method to retrieve a country name.
     *
     * @param string $countryCode a two character ISO country code
     */
    public function countryName(string $countryCode): string
    {
        $info = $this->countryInfo($countryCode);

        return $info['name'] ?? '';
    }

    /**
     * Quick access method to find a country code using the name of the country.
     *
     * @param string $name the full name of the country to get the ISO code for
     */
    public function countryCode(string $name): ?string
    {
        $info = self::$db->range("\x00", "\xff");
        foreach ($info as $code => $country) {
            if ('__' == substr($code, 0, 2)) {
                continue;
            }
            if (0 == strcasecmp($country['name'], $name)) {
                return $country['code'];
            }
        }

        return null;
    }

    /**
     * Quick access method to retrieve country continent info.
     *
     * @param string $countryCode two character ISO country code
     *
     * @return array<string,string>
     */
    public function countryContinent(string $countryCode): ?array
    {
        $info = $this->countryInfo($countryCode);

        return $info['continent'] ?? null;
    }

    /**
     * Quick access method to retrieve country language info.
     *
     * @param string $countryCode two character ISO country code
     *
     * @return array<string>
     */
    public function countryLanguages(string $countryCode): array
    {
        $info = $this->countryInfo($countryCode);

        return $info['languages'] ?? [];
    }

    /**
     * Quick access method to retrieve country phone dialling code.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryPhoneCode(string $countryCode): int
    {
        $info = $this->countryInfo($countryCode);

        return $info['phone_code'] ?? 0;
    }

    /**
     * Quick access method to retrieve country capital name.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryCapital(string $countryCode): string
    {
        $info = $this->countryInfo($countryCode);

        return $info['capital'] ?? '';
    }

    /**
     * Quick access method to retrieve country capital timezone.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryCapitalTimezone(string $countryCode): string
    {
        $info = $this->countryInfo($countryCode);

        return $info['capital_timezone'] ?? '';
    }

    /**
     * Quick access method to retrieve country area in square kilometers.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryArea(string $countryCode): int
    {
        $info = $this->countryInfo($countryCode);

        return $info['area'] ?? 0;
    }

    /**
     * Quick access method to retrieve country estimated internet hosts.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryHosts(string $countryCode): int
    {
        $info = $this->countryInfo($countryCode);

        return $info['hosts'] ?? 0;
    }

    /**
     * Quick access method to retrieve country estimated internet users.
     *
     * @param string $countryCode two character ISO country code
     */
    public function countryUsers(string $countryCode): int
    {
        $info = $this->countryInfo($countryCode);

        return $info['users'] ?? 0;
    }
}
