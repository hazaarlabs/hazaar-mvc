<?php

declare(strict_types=1);

namespace Hazaar;

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

    public function __construct(bool $re_intialise = false)
    {
        $filename = Application::getInstance()->getRuntimePath('geodata.db');
        $file = new File($filename);
        if (true === $re_intialise || !$file->exists()) {
            $this->__initialise();
        }
        GeoData::$db = new BTree($file, true);
        if (GeoData::VERSION !== GeoData::$db->get('__version__')) {
            $this->__initialise();
        }
    }

    /**
     * Initialises the internal B-Tree database with all available data.
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function __initialise()
    {
        if (!class_exists('ZipArchive')) {
            throw new \Exception('The GeoData class requires the ZipArchive class to be available!');
        }
        $geodataFilename = 'geodata.db';
        if (self::$db instanceof BTree) {
            GeoData::$db->close();
        }
        $zip = new \ZipArchive();
        $geodataFile = new File(Application::getInstance()->getRuntimePath(basename(GeoData::$sources['url'])));
        // Download the Hazaar GeoData file and check it's MD5 signature
        $geodataFile->putContents(file_get_contents(GeoData::$sources['url']));
        if (!$geodataFile->size() > 0) {
            throw new \Exception('Unable to download GeoData source file!');
        }
        $md5 = trim(file_get_contents(GeoData::$sources['md5']));
        if ($geodataFile->md5() !== $md5) {
            throw new \Exception('GeoData source file MD5 signature does not match!');
        }
        $dir = new File\Dir(Application::getInstance()->getRuntimePath());
        $zip->open($geodataFile->fullpath());
        $zip->extractTo((string) $dir);
        $geodataFile->unlink(); // Cleanup now
        if (true === $dir->exists($geodataFilename)) {
            if (self::$db instanceof BTree) {
                self::$db->open();
            }

            return true;
        }

        return false;
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
            $list[$code] = $field ? ake($info, $field) : $info;
        }
        asort($list);

        return $list;
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
        return $this->__list(GeoData::$db, 'name');
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
        $info = GeoData::$db->get(strtoupper($code));
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
        return $this->__list(GeoData::$db);
    }

    /**
     * Retrieve a list of states for a country.
     *
     * Using a two character ISO country code, this method will return a list of states for that country.
     *
     * @param string $country_code Two character ISO country code
     *
     * @return array<string, string> an array of states with their state code as the key and full name as the value
     */
    public function states(string $country_code): array
    {
        $list = [];
        if ($country = GeoData::$db->get(strtoupper($country_code))) {
            foreach (ake($country, 'states') as $code => $state) {
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
     * @param string $country_code two character ISO country code
     * @param string $state_code   state code
     *
     * @return array<string> an array of city names
     */
    public function cities(string $country_code, string $state_code): array
    {
        $list = [];
        if ($country = GeoData::$db->get(strtoupper($country_code))) {
            $cities = ake(ake(ake($country, 'states'), $state_code), 'cities', []);
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
     * @param string $country_code a two character ISO country code
     */
    public function countryName(string $country_code): string
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'name');
    }

    /**
     * Quick access method to find a country code using the name of the country.
     *
     * @param string $name the full name of the country to get the ISO code for
     */
    public function countryCode(string $name): ?string
    {
        $info = GeoData::$db->range("\x00", "\xff");
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
     * @param string $country_code two character ISO country code
     *
     * @return array<string,string>
     */
    public function countryContinent(string $country_code): ?array
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'continent');
    }

    /**
     * Quick access method to retrieve country language info.
     *
     * @param string $country_code two character ISO country code
     *
     * @return array<string>
     */
    public function countryLanguages(string $country_code): array
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'languages');
    }

    /**
     * Quick access method to retrieve country phone dialling code.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryPhoneCode(string $country_code): int
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'phone_code');
    }

    /**
     * Quick access method to retrieve country capital name.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryCapital(string $country_code): string
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'capital');
    }

    /**
     * Quick access method to retrieve country capital timezone.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryCapitalTimezone(string $country_code): string
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'capital_timezone');
    }

    /**
     * Quick access method to retrieve country area in square kilometers.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryArea(string $country_code): int
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'area');
    }

    /**
     * Quick access method to retrieve country estimated internet hosts.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryHosts(string $country_code): int
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'hosts');
    }

    /**
     * Quick access method to retrieve country estimated internet users.
     *
     * @param string $country_code two character ISO country code
     */
    public function countryUsers(string $country_code): int
    {
        $info = $this->countryInfo($country_code);

        return ake($info, 'users');
    }
}
