<?php

declare(strict_types=1);

/**
 * @file        Hazaar/LDAP/LDAP.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2015 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Net;

use LDAP\Connection;

/**
 * @brief LDAP access class
 *
 * @detail LDAP is the Lightweight Directory Access Protocol, and is a protocol used to access "Directory Servers". The
 * Directory is a special kind of database that holds information in a tree structure.
 *
 * The concept is similar to your hard disk directory structure, except that in this context, the root directory is
 * "The world" and the first level subdirectories are "countries". Lower levels of the directory structure contain
 * entries for companies, organisations or places, while yet lower still we find directory entries for people, and
 * perhaps equipment or documents.
 *
 *        @module ldap
 */
class LDAP
{
    private ?Connection $conn;
    private string $suffix;

    /**
     * LDAP constructor.
     *
     * @param string $host
     *                        If you are using OpenLDAP 2.x.x you can specify a URL instead of the hostname. To use LDAP
     *                        with SSL, compile OpenLDAP 2.x.x with SSL support, configure PHP with SSL, and set this parameter as
     *                        ldaps://hostname/.
     * @param int    $port
     *                        The port to connect to. Not used when using URLs.
     * @param int    $version
     *                        The LDAP protocol version used to communicate with the server
     */
    public function __construct(string $host, int $port = 389, int $version = 3)
    {
        $conn = ldap_connect($host, $port);
        if (false !== $conn) {
            $this->conn = $conn;
            ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, $version);
            ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);
        }
    }

    /**
     * Sets the BaseDN suffix to apply to method calls.
     *
     * This suffix is applied to methods that require a baseDN parameter, such as Hazaar\LDAP::search().
     *
     * @param string $base The base DN to append
     */
    public function setBaseSuffix(string $base): void
    {
        $this->suffix = trim($base, ',');
    }

    public function bind(string $user, ?string $secret = null): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_bind($this->conn, $user, $secret);
    }

    /**
     * Search LDAP tree.
     *
     * Performs the search for a specified filter on the directory with the scope of LDAP_SCOPE_SUBTREE. This is
     * equivalent to searching the entire directory.
     *
     * @param string        $filter     The search filter can be simple or advanced, using boolean operators in the format
     *                                  described in the LDAP documentation (see the »
     *                                  [Netscape Directory SDK](http://www.mozilla.org/directory/csdk-docs/filter.htm) for full information on filters).
     * @param string        $base       The base DN for the directory
     * @param array<string> $attributes An array of the required attributes, e.g. ["mail", "sn", "cn"]. Note that the "dn"
     *                                  is always returned irrespective of which attributes types are requested.
     *                                  Using this parameter is much more efficient than the default action (which is to return all attributes and their
     *                                  associated values). The use of this parameter should therefore be considered good practice.
     * @param null          $attrsonly  Should be set to 1 if only attribute types are wanted. If set to 0 both attributes types
     *                                  and attribute values are fetched which is the default behaviour.
     * @param null          $sizelimit  Enables you to limit the count of entries fetched. Setting this to 0 means no limit.
     *                                  p(notice). This parameter can NOT override server-side preset sizelimit. You can set it lower though. Some
     *                                  directory server hosts will be configured to return no more than a preset number of entries. If this occurs, the
     *                                  server will indicate that it has only returned a partial results set. This also occurs if you use this parameter
     *                                  to limit the count of fetched entries.
     * @param null          $timelimit  Sets the number of seconds how long is spend on the search. Setting this to 0 means no
     *                                  limit.
     *                                  p(notice). This parameter can NOT override server-side preset timelimit. You can set it lower though.
     * @param null          $deref      Specifies how aliases should be handled during the search. It can be one of the following:
     *                                  * *LDAP_DEREF_NEVER* - (default) aliases are never dereferenced.
     *                                  * *LDAP_DEREF_SEARCHING* - aliases should be dereferenced during the search but not when locating the base object of the search.
     *                                  * *LDAP_DEREF_FINDING* - aliases should be dereferenced when locating the base object but not during the search.
     *                                  * *LDAP_DEREF_ALWAYS* - aliases should be dereferenced always.
     *
     * @return array<mixed>|false
     */
    public function search(
        string $filter,
        ?string $base = null,
        ?array $attributes = null,
        $attrsonly = null,
        $sizelimit = null,
        $timelimit = null,
        $deref = null
    ): array|false {
        if (null === $this->conn) {
            return false;
        }
        $search_result = ldap_search($this->conn, $base.($this->suffix ? ','.$this->suffix : ''), $filter);

        return ldap_get_entries($this->conn, $search_result);
    }

    /**
     * Add entries to LDAP directory.
     *
     * @param string       $dn    The distinguished name of an LDAP entity
     * @param array<mixed> $entry An array that specifies the information about the entry. The values in the entries are
     *                            indexed by individual attributes. In case of multiple values for an attribute, they are indexed using integers
     *                            starting with 0.
     */
    public function add(string $dn, array $entry): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_add($this->conn, $dn, $entry);
    }

    /**
     * Modify the existing entries in the LDAP directory.
     *
     * The structure of the entry is same as in Hazaar\LDAP::add().
     *
     * @param string       $dn    The distinguished name of an LDAP entity
     * @param array<mixed> $entry An array that specifies the information about the entry. The values in the entries are
     *                            indexed by individual attributes. In case of multiple values for an attribute, they are indexed using integers
     *                            starting with 0.
     */
    public function modify(string $dn, array $entry): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_modify($this->conn, $dn, $entry);
    }

    /**
     * Delete an entry from a directory.
     *
     * @param string $dn The distinguished name of an LDAP entity
     */
    public function delete($dn): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_delete($this->conn, $dn);
    }

    /**
     * Add attribute values to current attributes.
     *
     * Adds one or more attributes to the specified dn. It performs the modification at the attribute level as opposed
     * to the object level. Object-level additions are done by the Hazaar\LDAP::add() function.
     *
     * @param string       $dn    The distinguished name of an LDAP entity
     * @param array<mixed> $entry
     */
    public function modAdd(string $dn, array $entry): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_mod_add($this->conn, $dn, $entry);
    }

    /**
     * Replace attribute values to current attributes.
     *
     * Replaces one or more attributes from the specified dn. It performs the modification at the attribute level as opposed
     * to the object level. Object-level modifications are done by the Hazaar\LDAP::modify() function.
     *
     * @param string       $dn    The distinguished name of an LDAP entity
     * @param array<mixed> $entry
     */
    public function modReplace(string $dn, array $entry): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_mod_replace($this->conn, $dn, $entry);
    }

    /**
     * Delete attribute values from current attributes.
     *
     * Removes one or more attributes from the specified dn. It performs the modification at the attribute level as
     * opposed to the object level. Object-level deletions are done by the Hazaar\LDAP::delete() function.
     *
     * @param string       $dn    The distinguished name of an LDAP entity
     * @param array<mixed> $entry
     */
    public function modDel(string $dn, array $entry): bool
    {
        if (null === $this->conn) {
            return false;
        }

        return ldap_mod_del($this->conn, $dn, $entry);
    }
}
