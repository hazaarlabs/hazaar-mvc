<?php

declare(strict_types=1);

namespace Hazaar\File;

define('METRIC_FLOAT_LEN', strlen(pack('f', 0)));
define('METRIC_HDR_LEN', 8);
define('METRIC_DSDEF_LEN', 12 + (2 * METRIC_FLOAT_LEN));
define('METRIC_ARCHIVE_HDR_LEN', 18);  // plus the value of ID length
define('METRIC_PDP_HDR_LEN', 6);
define('METRIC_PDP_ROW_LEN', METRIC_PDP_HDR_LEN + METRIC_FLOAT_LEN);
define('METRIC_CDP_HDR_LEN', 6);
define('METRIC_TYPE_HDR', 0xA1);   // Header
define('METRIC_TYPE_DS', 0xA2);    // Data Source
define('METRIC_TYPE_AD', 0xA3);    // Archive Definition
define('METRIC_TYPE_PDP', 0xA4);   // Primary Data Point
define('METRIC_TYPE_CDP', 0xA5);   // Consolidated Data Point

class Metric
{
    private string $file;
    private int $version = 1;
    private int $tickSec = 0;

    /**
     * @var resource
     */
    private mixed $handle = null;

    /**
     * @var array<string, array{name:string,desc:string,type:int,ticks:int,min:?int,max:?int,last:int}>
     */
    private array $dataSources = [];

    /**
     * @var array<string, array<string, int|string>>
     */
    private array $archives = [];

    /**
     * @var array<mixed>
     */
    private array $lastTick = ['data' => [], 'archive' => []];

    /**
     * @var array<mixed>
     */
    private array $dataSourceTypes = [
        'GAUGE' => 0x01,
        'COUNTER' => 0x02,
        'ABSOLUTE' => 0x03,
        'GAUGEZ' => 0x04,
    ];

    /**
     * @var array<mixed>
     */
    private array $archiveCFs = [
        'AVERAGE' => 0x01,
        'MIN' => 0x02,
        'MAX' => 0x03,
        'LAST' => 0x04,
        'COUNT' => 0x05,
    ];

    public function __construct(string $file)
    {
        $this->file = $file;
        if ($this->exists()) {
            $this->handle = fopen($file, 'c+');
            $this->restoreOptions();
            $this->update();
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function exists(): bool
    {
        return file_exists($this->file) && filesize($this->file) > 0;
    }

    /**
     * addDataSource('speed', 'COUNTER', 0, 600, 'Speed Counter');.
     *
     * @param string $dsname      //The name of the data source
     * @param string $type        //Data source types are: GAUGE, COUNTER, ABSOLUTE
     * @param int    $min         //Minimum allowed value
     * @param int    $max         //Maximum allowed value
     * @param string $description //String describing the data source
     *
     * @return bool
     */
    public function addDataSource(
        string $dsname,
        string $type,
        ?int $min = null,
        ?int $max = null,
        ?string $description = null
    ) {
        $type = strtoupper($type);
        if (!array_key_exists($type, $this->dataSourceTypes)) {
            return false;
        }
        $this->dataSources[$dsname] = [
            'name' => $dsname,
            'desc' => $description,
            'type' => $this->dataSourceTypes[$type],
            'ticks' => 0,
            'min' => $min,
            'max' => $max,
            'last' => -1,
        ];

        return true;
    }

    /**
     * addArchive('day_average', 'AVERAGE', 60, 24);.
     *
     * @param string $archiveID   Name of the archive
     * @param string $cf          Consolidation function and can be: AVERAGE, MIN, MAX or LAST
     * @param int    $ticks       Number of ticks to consolidate into a row
     * @param int    $rows        Number of rows to store in the archive
     * @param string $description A string describing the archive
     *
     * @return bool
     */
    public function addArchive(
        string $archiveID,
        string $cf,
        ?int $ticks = null,
        ?int $rows = null,
        ?string $description = null
    ) {
        if (!$archiveID) {
            return false;
        }
        $cf = strtoupper($cf);
        if (!array_key_exists($cf, $this->archiveCFs)) {
            return false;
        }
        $this->archives[$archiveID] = [
            'id' => $archiveID,
            'desc' => $description,
            'cf' => $this->archiveCFs[$cf],  // Consolidation function
            'ticks' => $ticks,               // Number of ticks to consolidate into a row
            'rows' => $rows,                 // Number of rows to store in the archive
            'last' => -1,                    // Pointer to the current row
        ];

        return true;
    }

    /**
     * Get a tick value.
     *
     * @param int $time Defaults to the current time if not specified
     */
    public function getTick(?int $time = null): int
    {
        if (null === $time) {
            $time = time();
        }

        return (int) floor($time / $this->tickSec);
    }

    public function create(int $tickSec = 1): bool
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
            unlink($this->file);
        }
        if (!($this->handle = fopen($this->file, 'c+'))) {
            return false;
        }
        $this->tickSec = $tickSec;
        $header = pack('vvV', METRIC_TYPE_HDR, $this->version, $this->tickSec);
        fwrite($this->handle, $header);
        // Store the data source definitions
        // Data sources consist of a header, followed by enough primary data points to maintain the archives
        foreach ($this->dataSources as &$ds) {
            $this->writeDataSource($ds);
        }
        // Create the archive sections
        // Archives consist of a header, followed by a payload section of size $rows
        foreach ($this->archives as &$archive) {
            $this->writeArchive($archive);
        }

        return true;
    }

    public function setValue(string $dsname, float $value): bool
    {
        if (!array_key_exists($dsname, $this->dataSources)) {
            return false;
        }
        $tick = $this->getTick();
        $ds = &$this->dataSources[$dsname];
        // Set the minimum value
        if (null !== $ds['min'] && $ds['min'] > $value) {
            $value = $ds['min'];
        }
        // Set the maximum value
        if (null !== $ds['max'] && $ds['max'] < $value) {
            $value = $ds['max'];
        }
        flock($this->handle, LOCK_EX);
        if ($ds['last'] < 0) {
            $ds['last'] = 0;
        }
        $offset_start = $this->getDataSourceOffset($dsname);
        $offset = $offset_start
            + METRIC_DSDEF_LEN
            + strlen($ds['name'].$ds['desc']);
        $pos = $offset + ($ds['last'] * METRIC_PDP_ROW_LEN);
        fseek($this->handle, $pos);
        $bytes = fread($this->handle, METRIC_PDP_ROW_LEN);
        $current = unpack('vtype/Vtick/fvalue', $bytes);
        if (($diff = $tick - $current['tick']) > 0) {
            // Only bring the PDPs up to date if this isn't the first write
            if ($current['tick'] > 0) {
                // Calculate the last row by adding the diff and getting remainder of division by ticks.
                $ds['last'] = ($ds['last'] + $diff) % $ds['ticks'];
                if ($diff > $ds['ticks']) {
                    $diff = $ds['ticks'];
                }
                for ($i = 1; $i < $diff; ++$i) {
                    $num = $diff - $i;
                    $newtick = $tick - $num;
                    $row = $ds['last'] - $num;
                    if ($row < 0) {
                        $row = $ds['ticks'] + $row;
                    }
                    fseek($this->handle, $offset + ($row * METRIC_PDP_ROW_LEN));
                    fwrite($this->handle, pack('vVf', METRIC_TYPE_PDP, $newtick, 0));
                }
                $pos = $offset + ($ds['last'] * METRIC_PDP_ROW_LEN);
            }
            $current['tick'] = $tick;
            $current['value'] = 0;
            fseek($this->handle, $offset_start + METRIC_DSDEF_LEN + strlen($ds['name'].$ds['desc']) - 4);
            fwrite($this->handle, pack('l', $ds['last']));
            $this->lastTick['data'][$dsname] = $tick;
        }

        switch ($this->dataSources[$dsname]['type']) {
            case 0x01:  // GAUGE
            case 0x04:  // GAUGEZ
                if ($value > $current['value']) {
                    $current['value'] = $value;
                }

                break;

            case 0x02:  // COUNTER
                $current['value'] += $value;

                break;

            case 0x03:  // ABSOLUTE
                $current['value'] = $value;

                break;
        }
        fseek($this->handle, $pos);
        fwrite($this->handle, pack('vVf', METRIC_TYPE_PDP, $current['tick'], $current['value']));
        flock($this->handle, LOCK_UN);

        return true;
    }

    /**
     * The update function stores a consolidated data point in the archive for each data source based on the settings
     * supplied when defining the archive.
     *
     * * A primary data point is a single value stored for each 'tick'.
     * * A consolidated data point is a value calculated based on 1 or more primary data points using the consolidation
     * function specified when defining the archive.
     *
     * How this works is as follows:
     *
     * * Step 1: Load all the available data points ready to be processed
     * * Step 2: Make sure there are data points for all ticks from lastTick to currentTick
     * * Step 3: Check if there are enough primary data points to create a consolidated data point.
     * * Step 4: If so, apply the consolidation function
     * * Step 5: Update the starting point in the archive definition
     * * Step 6: Store the consolidated data point value in the archive.
     */
    public function update(): bool
    {
        if (!is_resource($this->handle)) {
            return false;
        }
        $currentTick = $this->getTick();
        $updates = [];
        foreach ($this->archives as $archiveID => $archive) {
            $data = [];
            $lastTick = $this->lastTick['archive'][$archiveID] ?? $this->getTick();
            $updateTick = $lastTick + $archive['ticks'];
            if ($currentTick > $updateTick) {
                foreach ($this->dataSources as $dsname => $ds) {
                    if ($ds['ticks'] < $archive['ticks']) {
                        throw new \Exception("There are not enough primary data points({$ds['ticks']}) to satify this archive({$archive['ticks']})");
                    }
                    $start = $this->getDataSourceOffset($dsname) + METRIC_DSDEF_LEN + strlen($ds['name'].$ds['desc']);
                    for ($tick = $currentTick - 1; $tick > $lastTick; --$tick) {
                        // If the PDP for this tick has not been written
                        if ($this->lastTick['data'][$dsname] < $tick) {
                            $data[$tick] = 0;
                        } else {
                            $diff = ($tick - ($this->lastTick['data'][$dsname] ?? $this->getTick()));
                            $row = $ds['last'] + $diff;
                            if ($row < 0) {
                                $row = $ds['ticks'] + $row;
                            }
                            fseek($this->handle, $start + ($row * METRIC_PDP_ROW_LEN));
                            $pdp = unpack('vtype/Vtick/fvalue', fread($this->handle, METRIC_PDP_ROW_LEN));
                            $data[$tick] = ($pdp['tick'] === $tick) ? $pdp['value'] : 0;
                        }
                    }
                    ksort($data);
                    $current_data = [];
                    foreach ($data as $tick => $value) {
                        if ($tick >= $currentTick) {         // Not ready to process this data point yet
                            break;
                        }
                        $current_data[$tick] = $value;
                        if (count($current_data) == $archive['ticks']) {
                            $cvalue = $this->consolidate($archive['cf'], $current_data, $ds['type']);
                            $updates[$archiveID][$tick][$dsname] = $cvalue;
                            $current_data = [];
                        }
                    }
                }
            }
        }
        if (count($updates) > 0) {
            foreach ($updates as $archiveID => $rows) {
                $archive = &$this->archives[$archiveID];
                // Get the start of the archive
                $offset_start = $this->getArchiveOffset($archiveID);
                foreach ($rows as $tick => $values) {
                    if (count($values) != count($this->dataSources)) {
                        throw new \Exception('All dataSources must be written in an update!');
                    }
                    // Get the current row we are working on
                    $row = $archive['last'] + 1;
                    if ($row >= $archive['rows']) {
                        $row = 0;
                    }
                    $offset = METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'].$archive['desc']) + ($row * $this->getCDPLength());
                    $pos = $offset_start + $offset;
                    fseek($this->handle, $pos); // Seek to the correct archive position
                    $this->writeCDP($tick, $values);
                    $archive['last'] = $row;
                    $this->lastTick['archive'][$archiveID] = $tick;
                }
                $pos = $offset_start + METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'].$archive['desc']) - 4;
                fseek($this->handle, $pos);
                fwrite($this->handle, pack('l', $archive['last']));
            }

            return true;
        }

        return false;
    }

    public function hasDataSource(string $name): bool
    {
        return isset($this->dataSources[$name]);
    }

    /**
     * @return array<string>
     */
    public function getDataSources(): array
    {
        return array_keys($this->dataSources);
    }

    /**
     * @return array<string>
     */
    public function getArchives(): array
    {
        return array_keys($this->archives);
    }

    public function getfile(): string
    {
        return $this->file;
    }

    /**
     * @return array<string,mixed>|false
     */
    public function graph(string $dsname, string $archiveID = 'default'): array|false
    {
        if (!$this->exists()) {
            return false;
        }
        $data = [];
        if (!array_key_exists($dsname, $this->dataSources)) {
            return false;
        }
        if (!array_key_exists($archiveID, $this->archives)) {
            return false;
        }
        $archive = $this->archives[$archiveID];
        $offset = $this->getArchiveOffset($archiveID) + METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'].$archive['desc']);
        fseek($this->handle, $offset);
        $row_length = $this->getCDPLength();
        while ($type = fread($this->handle, 2)) {
            if (METRIC_TYPE_CDP != ord($type)) {
                break;
            }
            $parts = unpack('Vtick', fread($this->handle, 4));
            $tick = $parts['tick'] * $this->tickSec;
            if (!($tick > 0)) {
                break;
            }
            $values = array_combine(array_keys($this->dataSources), unpack('f*', fread($this->handle, $row_length - 6)));
            $data[$tick] = $values[$dsname];
        }
        $step_sec = $archive['ticks'] * $this->tickSec;
        if (($diff = ($archive['rows'] - count($data))) > 0) {
            if (count($data) > 0) {
                $min = min(array_keys($data));
            } else {
                $min = $this->getTick() * $this->tickSec;
            }
            $start_tick = $min - ($diff * $step_sec);
            for ($tick = $start_tick; $tick < $min; $tick += $step_sec) {
                $data[$tick] = floatval(0);
            }
        }
        ksort($data);

        return [
            'ds' => $this->dataSources[$dsname],
            'archive' => $archive,
            'ticks' => $data,
        ];
    }

    /**
     * Retrieve the raw primary data points stored in a data source.
     *
     * @param string $dsname the name of the data source
     *
     * @return array<string,mixed>|false
     */
    public function data(string $dsname): array|false
    {
        $data = [];
        $offset = $this->getDataSourceOffset($dsname);
        fseek($this->handle, $offset);
        $bytes = fread($this->handle, 2);
        if (METRIC_TYPE_DS !== ord($bytes)) {
            return false;
        }
        $header = unpack('vtype/Clen', fread($this->handle, 3));
        $name = fread($this->handle, $header['len']);
        $body = unpack('Clen', fread($this->handle, 1));
        $desc = (($body['len'] > 0) ? fread($this->handle, $body['len']) : null);
        $foot = unpack('vticks/fmin/fmax/llast', fread($this->handle, 6 + (2 * METRIC_FLOAT_LEN)));
        $ds = [
            'name' => $name,
            'desc' => $desc,
            'type' => $header['type'],
            'ticks' => $foot['ticks'],
            'min' => (-1 == $foot['min']) ? null : $foot['min'],
            'max' => (-1 == $foot['max']) ? null : $foot['max'],
            'last' => $foot['last'],
        ];
        while ($type = fread($this->handle, 2)) {
            if (METRIC_TYPE_PDP != ord($type)) {
                break;
            }
            $row = unpack('Vtick/fvalue', fread($this->handle, METRIC_PDP_ROW_LEN - 2));
            if ($row['tick'] <= 0) {
                break;
            }
            $data[$row['tick']] = $row['value'];
        }
        ksort($data);

        return [
            'ds' => $ds,
            'ticks' => $data,
        ];
    }

    /**
     * Calculate the length of a row within an archive.
     */
    private function getCDPLength(): int
    {
        return METRIC_CDP_HDR_LEN + (count($this->dataSources) * METRIC_FLOAT_LEN);
    }

    /**
     * Calculate the start position in the file of a data source.
     *
     * @param mixed $dsname The name of the data source.  If omitted, returns the position of the byte after all data sources
     */
    private function getDataSourceOffset($dsname = null): int
    {
        $offset = METRIC_HDR_LEN;
        // Get the length of all names and descriptions
        foreach ($this->dataSources as $ds) {
            if ($ds['name'] === $dsname) {
                break;
            }
            $offset += METRIC_DSDEF_LEN + strlen($ds['name'].$ds['desc']) + ($ds['ticks'] * METRIC_PDP_ROW_LEN);
        }

        return $offset;
    }

    /**
     * Calculate the start position in the file of an archive.
     *
     * @param mixed $archiveID The name of the archive
     */
    private function getArchiveOffset($archiveID = 'default'): int
    {
        $row_length = $this->getCDPLength();
        $offset = METRIC_HDR_LEN;
        foreach ($this->dataSources as $ds) {
            $offset += METRIC_DSDEF_LEN + strlen($ds['name'].$ds['desc']) + ($ds['ticks'] * METRIC_PDP_ROW_LEN);
        }
        foreach ($this->archives as $id => $archive) {
            if ($archiveID == $id) {
                break;
            }
            $offset += METRIC_ARCHIVE_HDR_LEN + ($row_length * $archive['rows']) + strlen($archive['id'].$archive['desc']);
        }

        return $offset;
    }

    /**
     * Load data sources and archives from an existing RRD database file.
     */
    private function restoreOptions(): bool
    {
        if (!is_resource($this->handle)) {
            return false;
        }
        while ($type = fread($this->handle, 2)) {
            switch (ord($type)) {
                case METRIC_TYPE_HDR: // Header
                    $bytes = fread($this->handle, METRIC_HDR_LEN - 2);
                    $header = unpack('vversion/Vticksec', $bytes);
                    if ((int) $header['version'] != $this->version) {
                        throw new \Exception('RRD file version error.  File is version '.$header['version'].' but RRD is version '.$this->version);
                    }
                    $this->tickSec = $header['ticksec'];

                    break;

                case METRIC_TYPE_DS: // DataSource
                    $header = unpack('vtype/Clen', fread($this->handle, 3));
                    $name = fread($this->handle, $header['len']);
                    $body = unpack('Clen', fread($this->handle, 1));
                    $desc = (($body['len'] > 0) ? fread($this->handle, $body['len']) : null);
                    $foot = unpack('vticks/fmin/fmax/llast', fread($this->handle, 6 + (2 * METRIC_FLOAT_LEN)));
                    $ds = [
                        'name' => $name,
                        'desc' => $desc,
                        'type' => $header['type'],
                        'ticks' => $foot['ticks'],
                        'min' => (-1 == $foot['min']) ? null : $foot['min'],
                        'max' => (-1 == $foot['max']) ? null : $foot['max'],
                        'last' => $foot['last'],
                    ];
                    $this->dataSources[$name] = $ds;
                    $start = ftell($this->handle);
                    if ($ds['last'] < 0) {
                        $this->lastTick['data'][$ds['name']] = $this->getTick() - 1;
                    } else {
                        // Skip to the current PDP row
                        $offset = $start + $ds['last'] * METRIC_PDP_ROW_LEN;
                        fseek($this->handle, $offset);
                        $pdp = unpack('vtype/Vtick/fvalue', fread($this->handle, METRIC_PDP_ROW_LEN));
                        $this->lastTick['data'][$ds['name']] = $pdp['tick'];
                    }
                    // Skip over the primary data points
                    fseek($this->handle, $start + ($ds['ticks'] * METRIC_PDP_ROW_LEN));

                    break;

                case METRIC_TYPE_AD: // Archive definition
                    $archive = [];
                    $header = unpack('Clen', fread($this->handle, 1));
                    $archive['id'] = fread($this->handle, $header['len']);
                    $body = unpack('Clen', fread($this->handle, 1));
                    $archive['desc'] = fread($this->handle, $body['len']);
                    $archive = array_merge($archive, unpack('vcf/Vticks/Vrows/llast', fread($this->handle, 14)));
                    $this->archives[$archive['id']] = $archive;
                    $start = ftell($this->handle);
                    $len = $this->getCDPLength();
                    if ($archive['last'] < 0) {
                        $this->lastTick['archive'][$archive['id']] = $this->getTick() - $archive['ticks'] - 1;
                    } else {
                        // Skip to the current archive row
                        $offset = $len * $archive['last'];
                        fseek($this->handle, $offset, SEEK_CUR);
                        $row = unpack('vtype/Vtick', fread($this->handle, $len));
                        $this->lastTick['archive'][$archive['id']] = $row['tick'];
                    }
                    // Skip the rest of the archive
                    fseek($this->handle, $start + ($len * $archive['rows']));

                    break;

                case METRIC_TYPE_PDP: // PDP (Primary Data Point)
                case METRIC_TYPE_CDP: // CDP (Consolidated Data Point)
                    exit('THIS SHOULD NOT HAPPEN AT FILE POSITION '.ftell($this->handle)."\n");

                default:
                    exit('Unexpected block type! TYPE='.dechex(ord($type))."\n");
            }
        }

        return true;
    }

    /**
     * @param array<string,null|int|string> $ds
     */
    private function writeDataSource(array &$ds): bool
    {
        // Calculate how many primary data points we need
        foreach ($this->archives as $archive) {
            if ($archive['ticks'] > $ds['ticks']) {
                $ds['ticks'] = $archive['ticks'];
            }
        }
        $line = pack('vvC', METRIC_TYPE_DS, $ds['type'], strlen($ds['name'])).$ds['name'];
        $line .= pack('C', strlen($ds['desc'])).$ds['desc'];
        $min = (null === $ds['min']) ? -1 : $ds['min'];
        $max = (null === $ds['max']) ? -1 : $ds['max'];
        $line .= pack('vffl', $ds['ticks'], $min, $max, $ds['last']);
        $len = strlen($line) - strlen($ds['name']) - strlen($ds['desc']);
        if (METRIC_DSDEF_LEN != $len) {
            exit('dataSource header length is not METRIC_DSDEF_LEN('.METRIC_DSDEF_LEN.") LENGTH={$len}\n");
        }
        if (fwrite($this->handle, $line) !== strlen($line)) {
            return false;
        }
        $pdp = str_repeat(pack('vVf', METRIC_TYPE_PDP, 0, 0), $ds['ticks']);

        return fwrite($this->handle, $pdp) === strlen($pdp);
    }

    /**
     * @param array<string,null|int|string> $archive
     */
    private function writeArchive(array &$archive): bool
    {
        $data = pack('vC', METRIC_TYPE_AD, strlen($archive['id'])).$archive['id'];
        $data .= pack('C', strlen($archive['desc'])).$archive['desc'];
        $data .= pack('vVVl', $archive['cf'], $archive['ticks'], $archive['rows'], $archive['last']);
        $len = strlen($data) - strlen($archive['id']) - strlen($archive['desc']);
        if (METRIC_ARCHIVE_HDR_LEN != $len) {
            exit('archive header length is not METRIC_ARCHIVE_HDR_LEN('.METRIC_ARCHIVE_HDR_LEN.") LENGTH={$len}\n");
        }
        for ($i = 0; $i < $archive['rows']; ++$i) {
            $data .= $this->writeCDP(0, array_fill(0, count($this->dataSources), 0), false);
        }

        return fwrite($this->handle, $data) === strlen($data);
    }

    /**
     * @param array<float> $values
     */
    private function writeCDP(int $tick, array $values, bool $doWrite = true): bool|string
    {
        $row = pack('vV', METRIC_TYPE_CDP, $tick);
        if (METRIC_CDP_HDR_LEN != strlen($row)) {
            exit('Archive row length is not METRIC_CDP_HDR_LEN('.METRIC_CDP_HDR_LEN.') LENGTH='.strlen($row));
        }
        foreach ($values as $value) {
            $row .= pack('f', $value);
        }
        if (true === $doWrite) {
            return fwrite($this->handle, $row) === strlen($row);
        }

        return $row;
    }

    /**
     * @param array<float> $dataPoints
     */
    private function consolidate(int $cf, array $dataPoints, ?int $dsType = null): float
    {
        $value = null;
        // GAUGEZ means we want to ignore zero values in our consolidation
        if (0x04 === $dsType) {
            $dataPoints = array_filter($dataPoints, function ($value) {
                return 0 != $value;
            });
        }

        switch ($cf) {
            case 0x01: // AVERAGE
                $value = 0;
                if (count($dataPoints) > 0) {
                    foreach ($dataPoints as $dp) {
                        $value += $dp;
                    }
                    $value = $value / count($dataPoints);
                }

                break;

            case 0x02: // MIN
                $value = array_shift($dataPoints);
                foreach ($dataPoints as $dp) {
                    if ($dp < $value) {
                        $value = $dp;
                    }
                }

                break;

            case 0x03: // MAX
                $value = array_shift($dataPoints);
                foreach ($dataPoints as $dp) {
                    if ($dp > $value) {
                        $value = $dp;
                    }
                }

                break;

            case 0x04: // LAST
                $value = array_pop($dataPoints);

                break;

            case 0x05:
                $value = array_sum($dataPoints);

                break;
        }

        return $value;
    }
}
