<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * Represents and manipulates versions according to the Semantic Versioning 2.0.0 specification (SemVer).
 *
 * This class allows parsing a SemVer string (e.g., "1.2.3-rc.1+build.456") into its constituent parts:
 * major, minor, patch, pre-release identifiers, and build metadata.
 *
 * It provides methods to:
 * - Access individual version components (getMajor(), getMinor(), getPatch(), getPreRelease(), getMetadata()).
 * - Compare two Version objects or a Version object and a SemVer string based on SemVer precedence rules (compareTo(), equals(), lessThan(), greaterThan()).
 * - Conditionally update the version based on precedence (setIfHigher(), setIfLower()).
 *
 * Comparison follows SemVer 2.0.0 rules:
 * - Major, Minor, Patch are compared numerically.
 * - Pre-release versions have lower precedence than normal versions (1.0.0-alpha < 1.0.0).
 * - Pre-release identifiers are compared segment by segment (numeric vs. alphanumeric, lexicographical).
 * - Build metadata is *ignored* during precedence comparison.
 *
 * Example Usage:
 * ```php
 * use Hazaar\Util\Version;
 *
 * // Create a version object
 * $v1 = new Version('1.2.3-beta.1+build.100');
 * echo $v1; // Outputs: 1.2.3-beta.1+build.100
 *
 * // Access components
 * echo $v1->getMajor(); // Outputs: 1
 * echo $v1->getMinor(); // Outputs: 2
 * echo $v1->getPatch(); // Outputs: 3
 * echo $v1->getPreRelease(); // Outputs: beta.1
 * echo $v1->getMetadata(); // Outputs: build.100
 *
 * // Compare versions
 * $v2 = new Version('1.2.3-beta.2');
 * $v3 = new Version('1.2.3');
 *
 * var_dump($v1->lessThan($v2)); // bool(true) because beta.1 < beta.2
 * var_dump($v2->lessThan($v3)); // bool(true) because pre-release < normal release
 * var_dump($v1->equals('1.2.3-beta.1+build.999')); // bool(true) - metadata ignored for equality
 *
 * // Conditional update
 * $currentVersion = new Version('2.0.0');
 * $currentVersion->setIfHigher('1.9.0'); // No change, 1.9.0 is not higher
 * echo $currentVersion; // Outputs: 2.0.0
 * $currentVersion->setIfHigher('2.1.0-alpha'); // Changes, 2.1.0-alpha is higher
 * echo $currentVersion; // Outputs: 2.1.0-alpha
 * ```
 *
 * @see https://semver.org/spec/v2.0.0.html The Semantic Versioning 2.0.0 specification.
 */
class Version
{
    private string $version = 'none';
    private int $major = 0;
    private int $minor = 0;
    private int $patch = 0;
    private ?string $preRelease = null;
    private ?string $metadata = null;

    /**
     * Version constructor.
     *
     * Parses the provided SemVer 2.0.0 version string.
     *
     * @param string $version The SemVer 2.0.0 version string (e.g., "1.2.3", "2.0.0-rc.1", "1.0.0+build.123").
     *
     * @throws \Exception If the version string does not conform to the SemVer 2.0.0 format.
     */
    public function __construct(string $version)
    {
        $this->set($version);
    }

    /**
     * Magic method to output the version as a string.
     *
     * Returns the original, full version string provided during construction or via set().
     *
     * @return string the full SemVer string
     */
    public function __toString()
    {
        return $this->get();
    }

    /**
     * Sets the version string and updates the internal version components.
     *
     * Parses the provided SemVer 2.0.0 string and stores its components (major, minor, patch, pre-release, metadata).
     * The original string is also stored.
     *
     * @param string $version The SemVer 2.0.0 version string to set.
     *
     * @throws \Exception If the version string does not conform to the SemVer 2.0.0 format.
     */
    public function set(string $version): void
    {
        // SemVer 2.0.0 regex from https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
        $semVerRegex = '/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

        if (!preg_match($semVerRegex, $version, $matches)) {
            // No fallback for custom delimiters anymore
            throw new \Exception('Invalid SemVer 2.0.0 format: '.$version);
        }
        // --- SemVer Parsing ---
        $this->major = (int) $matches['major'];
        $this->minor = (int) $matches['minor'];
        $this->patch = (int) $matches['patch'];
        $this->preRelease = $matches['prerelease'] ?? null;
        $this->metadata = $matches['buildmetadata'] ?? null;
        $this->version = $version; // Store the original full version string
        // --- End SemVer Parsing ---
    }

    /**
     * Get the current version number as the original formatted string.
     *
     * Returns the full, original SemVer string that was parsed.
     *
     * @return string the original SemVer string
     */
    final public function get(): string
    {
        // Return the full original string parsed/stored in set()
        return $this->version;
    }

    /**
     * Get the major version number.
     *
     * @return int The major version component (e.g., 1 in "1.2.3").
     */
    public function getMajor(): int
    {
        return $this->major;
    }

    /**
     * Get the minor version number.
     *
     * @return int The minor version component (e.g., 2 in "1.2.3").
     */
    public function getMinor(): int
    {
        return $this->minor;
    }

    /**
     * Get the patch version number.
     *
     * @return int The patch version component (e.g., 3 in "1.2.3").
     */
    public function getPatch(): int
    {
        return $this->patch;
    }

    /**
     * Get the pre-release identifier string.
     *
     * Returns the pre-release part of the version string (e.g., "rc.1" in "1.2.3-rc.1+build.456").
     * Returns null if there is no pre-release identifier.
     *
     * @return null|string the pre-release identifier or null
     */
    public function getPreRelease(): ?string
    {
        return $this->preRelease;
    }

    /**
     * Get the build metadata string.
     *
     * Returns the build metadata part of the version string (e.g., "build.456" in "1.2.3-rc.1+build.456").
     * Returns null if there is no build metadata.
     *
     * @return null|string the build metadata or null
     */
    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    /**
     * Compare the current version instance to another version according to SemVer 2.0.0 precedence rules.
     *
     * Comparison logic:
     * 1. Major, Minor, Patch are compared numerically.
     * 2. Pre-release versions have lower precedence than normal versions (e.g., 1.0.0-alpha < 1.0.0).
     * 3. Pre-release identifiers are compared dot-separated field by field:
     *    - Numeric fields compared numerically.
     *    - Alphanumeric fields compared lexicographically (ASCII sort order).
     *    - Numeric fields have lower precedence than alphanumeric fields.
     *    - Shorter set of fields has lower precedence if all preceding fields are equal (e.g., 1.0.0-alpha < 1.0.0-alpha.1).
     * 4. Build metadata is ignored during precedence comparison.
     *
     * Returns an integer indicating the result:
     * * 0: Indicates the versions have the same precedence (ignoring build metadata).
     * * -1: Means the current version has lower precedence than $that.
     * * 1: Means the current version has higher precedence than $that.
     *
     * @param string|Version $that The version to compare against. Can be either a SemVer string or another Version object.
     *
     * @return int -1, 0, or 1 based on SemVer precedence comparison
     *
     * @throws \Exception If the $that string is not a valid SemVer 2.0.0 format when creating a temporary Version object.
     */
    public function compareTo(string|Version $that): int
    {
        if (!$that instanceof Version) {
            $that = new Version($that);
        }
        // Compare major, minor, patch numerically
        if ($this->major !== $that->getMajor()) {
            return $this->major < $that->getMajor() ? -1 : 1;
        }
        if ($this->minor !== $that->getMinor()) {
            return $this->minor < $that->getMinor() ? -1 : 1;
        }
        if ($this->patch !== $that->getPatch()) {
            return $this->patch < $that->getPatch() ? -1 : 1;
        }
        // Basic comparison for pre-release (treat null as higher than a value, like SemVer)
        $thisPre = $this->getPreRelease();
        $thatPre = $that->getPreRelease();
        if (null === $thisPre && null !== $thatPre) {
            return 1;
        } // No pre-release > pre-release
        if (null !== $thisPre && null === $thatPre) {
            return -1;
        } // Pre-release < no pre-release
        if (null !== $thisPre && null !== $thatPre) {
            // SemVer pre-release comparison: split by dots, compare identifiers
            $thisPreParts = explode('.', $thisPre);
            $thatPreParts = explode('.', $thatPre);
            $maxParts = max(count($thisPreParts), count($thatPreParts));
            for ($i = 0; $i < $maxParts; ++$i) {
                $thisPart = $thisPreParts[$i] ?? null;
                $thatPart = $thatPreParts[$i] ?? null;

                if (null === $thisPart && null !== $thatPart) {
                    return -1;
                } // Shorter pre-release < longer pre-release
                if (null !== $thisPart && null === $thatPart) {
                    return 1;
                }  // Longer pre-release > shorter pre-release
                if ($thisPart === $thatPart) {
                    continue;
                } // Identifiers are equal

                $thisIsNumeric = is_numeric($thisPart);
                $thatIsNumeric = is_numeric($thatPart);

                if ($thisIsNumeric && !$thatIsNumeric) {
                    return -1;
                } // Numeric < Alphanumeric
                if (!$thisIsNumeric && $thatIsNumeric) {
                    return 1;
                }  // Alphanumeric > Numeric

                if ($thisIsNumeric) { // Both numeric
                    return (int) $thisPart < (int) $thatPart ? -1 : 1;
                }   // Both alphanumeric

                return strcmp($thisPart, $thatPart) < 0 ? -1 : 1;
            }
        }

        // Metadata is ignored in comparison precedence according to SemVer

        return 0; // If all comparable parts are equal
    }

    /**
     * Checks if the current version is equal to another version based on SemVer precedence.
     *
     * Note: This ignores build metadata as per SemVer 2.0.0 rules for precedence.
     * Two versions differing only in build metadata are considered equal in precedence.
     *
     * @param string|Version $that the version to compare to (string or Version object)
     *
     * @return bool TRUE if the versions have the same precedence, FALSE otherwise
     *
     * @throws \Exception If the $that string is not a valid SemVer 2.0.0 format.
     */
    public function equals(string|Version $that): bool
    {
        // Use compareTo to check for equality
        return 0 === $this->compareTo($that);
    }

    /**
     * Sets the current version instance to the given version if the given version has higher precedence.
     *
     * Compares the current version with the provided one using SemVer precedence rules.
     * If the provided version is higher, the current instance is updated to match it.
     *
     * @param string|Version $version the version to compare against and potentially set (string or Version object)
     *
     * @throws \Exception If the $version string is not a valid SemVer 2.0.0 format.
     */
    public function setIfHigher(string|Version $version): void
    {
        if (-1 === $this->compareTo($version)) {
            // Ensure $version is a string before calling set
            $versionString = ($version instanceof Version) ? $version->get() : $version;
            $this->set($versionString);
        }
    }

    /**
     * Sets the current version instance to the given version if the given version has lower precedence.
     *
     * Compares the current version with the provided one using SemVer precedence rules.
     * If the provided version is lower, the current instance is updated to match it.
     *
     * @param string|Version $version the version to compare against and potentially set (string or Version object)
     *
     * @throws \Exception If the $version string is not a valid SemVer 2.0.0 format.
     */
    public function setIfLower(string|Version $version): void
    {
        if (1 === $this->compareTo($version)) {
            // Ensure $version is a string before calling set
            $versionString = ($version instanceof Version) ? $version->get() : $version;
            $this->set($versionString);
        }
    }

    /**
     * Checks if the current version is equal to another version based on SemVer precedence.
     *
     * Alias for `equals()`. Ignores build metadata.
     *
     * @param string|Version $that the version to compare to (string or Version object)
     *
     * @return bool TRUE if the versions have the same precedence, FALSE otherwise
     *
     * @throws \Exception If the $that string is not a valid SemVer 2.0.0 format.
     */
    public function equalTo(string|Version $that): bool
    {
        return 0 === $this->compareTo($that);
    }

    /**
     * Checks if the current version has lower precedence than another version.
     *
     * Compares versions based on SemVer 2.0.0 precedence rules.
     *
     * @param string|Version $that the version to compare to (string or Version object)
     *
     * @return bool TRUE if the current version is lower in precedence, FALSE otherwise
     *
     * @throws \Exception If the $that string is not a valid SemVer 2.0.0 format.
     */
    public function lessThan(string|Version $that): bool
    {
        return -1 === $this->compareTo($that);
    }

    /**
     * Checks if the current version has higher precedence than another version.
     *
     * Compares versions based on SemVer 2.0.0 precedence rules.
     *
     * @param string|Version $that the version to compare to (string or Version object)
     *
     * @return bool TRUE if the current version is higher in precedence, FALSE otherwise
     *
     * @throws \Exception If the $that string is not a valid SemVer 2.0.0 format.
     */
    public function greaterThan(string|Version $that): bool
    {
        return 1 === $this->compareTo($that);
    }
}
