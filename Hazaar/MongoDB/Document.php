<?php
/**
 * @file        Hazaar/Db/MongoDB/Document.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief      MongoDB Object Classes
 */
namespace Hazaar\MongoDB;

/**
 * @brief       MongoDB Document class
 *
 * @detail      This class can be used for inserting, updating and deleting a single document in a MongoDB collection.
 *              It has many advanced features that allow in-place 'atomic' updates of fields via is built-in differencing
 *              engine.
 *
 *              <div class="alert alert-info">At the time of writing, this class works with MongoDB version 2.2.1.</div>
 *
 *              h3. Example
 *
 *              <code>
 *              $db = new Hazaar\MongoDB(array('hosts' => 'localhost', 'database' => 'mydb'));
 *              $doc = new Hazaar\MongoDB\Document($db, 'users', array('name' => 'myusername'));
 *              $doc->address[] = array('street', 'state', 'postcode');
 *              $doc->update();
 *              </code>
 *
 *              In this example we create a new MongoDB connection object to connect to a database server
 *              on the localhost and using the 'mydb' database.  We then find the document in the users collection
 *              where the name is 'myusername'.  After the document is loaded we add an address to the address
 *              array and then update the document.
 *
 * @since       1.0.0
 */
class Document extends EmbeddedDocument {

    private $db;

    private $collection;

    private $collection_name;

    private $sleep_values = array();

    private $exists = false;

    /**
     * @detail      Loading a %MongoDB Document using an existing MongoDB connection object is easy.
     *
     * @since       1.0.0
     *
     * @param       \Hazaar\MongoDB $db         A MongoDB connection object.
     *
     * @param       string          $collection The name of the collection to query.
     *
     * @param       Array           $criteria   Array of search criteria.  See the
     *                                          [[http://docs.mongodb.org/manual/reference/operators/|MongoDB Documentation]] for information on
     *                                          querying.
     *
     * @param       Array           $fields     An array of fields that you only want returned
     */
    function __construct(\Hazaar\MongoDB $db, $collection, $criteria = array(), $fields = array()) {

        parent::__construct();

        $this->collection = $collection;

        if($criteria !== null || $criteria !== false) {

            if(! $this->collection instanceof \MongoCollection)
                $this->collection = $db->selectCollection($this->collection);

            $this->collection_name = $this->collection->getName();

            if(! \Hazaar\Map::is_array($criteria))
                $criteria = array('_id' => new \MongoId($criteria));

            if(! \Hazaar\Map::is_array($fields))
                $fields = array();

            if(! \Hazaar\Map::is_array($values = $this->collection->findOne($criteria, $fields))) {

                $this->populate($criteria);

            } else {

                $this->populate($values);

                $this->exists = true;

            }

        }

        $this->db = $db;

    }

    /**
     * @brief       Magic method used by serialize to convert a Document object into a string.
     *
     * @detail      Helpful for storing or transferring a Document class use a mechanism that doesn't support objects.
     *
     * @since       1.0.0
     *
     * @return      Array As per the definition of the __sleep() method, we return an array of members that are
     *              safe to serialize.
     */
    public function __sleep() {

        $this->sleep_values = $this->toArray();

        return array(
            'sleep_values',
            'db',
            'collection_name',
            'exists'
        );

    }

    /**
     * @brief       Magic method used by unserialize to allow the Document object to be serialised into a string value.
     *
     * @detail      Helpful for storing or transferring a Document class use a mechanism that doesn't support objects.
     *
     * @since       1.0.0
     */
    public function __wakeup() {

        //$this->collection = new \MongoCollection($this->collection_name);

        $this->populate($this->sleep_values);

        $this->sleep_values = array();

        $this->collection = $this->db->selectCollection($this->collection_name);

    }

    /**
     * @brief       Test if the document requested on instantiation actually exists.
     *
     * @since       1.0.0
     *
     * @return      boolean True if the document exists or false if it does not.
     */
    public function exists() {

        return $this->exists;

    }

    /**
     * @brief       Updates the loaded document with any changes.
     *
     * @detail      If the document does not exist, it will be created and the _id field will be populated.
     *
     * @since       1.0.0
     *
     * @return      boolean True if the update was successful, false otherwise.
     */
    public function update() {

        $ret = false;

        if(! $this->exists()) {

            $ret = $this->insert();

        } else {

            $updates = $this->resolve();

            if(count($updates) > 0) {

                /*
                 * Updates is an array so that we can run multiple updates in one hit.
                 * This is because sometimes we need to clean up nulls after doing an $unset
                 */
                foreach($updates as $update) {

                    if(count($update) > 0) {

                        $ret = $this->collection->update(array('_id' => $this['_id']), $update);

                        if(! $ret)
                            return false;

                    }

                }

                $this->commit();

            }

        }

        return $ret;

    }

    /**
     * @brief       Insert a new document into the collection.
     *
     * @detail      Normally update will call this automatically if the document with '_id' does not exist.  You can call
     *              this method directly to force an insert.  If the _id field is set then it is highly likely that the
     *              MongoDB database will throw an error.
     *
     * @since       1.0.0
     *
     * @return      boolean True on success, false otherwise.
     */
    public function insert() {

        $new = $this->toArray();

        if($this->collection->insert($new)) {

            $id = $new['_id'];

            $this->populate($new);

            return $id;

        }

        return false;

    }

    /**
     * @brief       Delete the document from the MongoDB database
     *
     * @since       1.0.0
     *
     * $retval      boolean True on success, false if the document could not be removed for whatever reason.
     */
    public function delete() {

        $id = $this->get('_id');

        return $this->collection->remove(array('_id' => $id));

    }

    /**
     * @brief       Convert a multi-dimensional array into a single dimension with dot-notation keys
     *
     * @since       1.0.0
     *
     * @param       Array  $values The source array to convert
     *
     * @param       string $prefix The current notational prefix (internal use only)
     *
     * @param       int    $depth  The current depth (internal use only)
     *
     * @param       Array  $note   Recursive node list (internal use only)
     *
     * @return      Array An single dimensional array of key/value pairs where the key is a dot notation representation
     *              of a multi-dimensional array.
     */
    private function makeDotNotation($values, $prefix = null, $depth = null, &$note = array()) {

        if($values instanceof \ArrayAccess) {

            $values = $values->toArray();

        }

        if(is_array($values)) {

            if(! $depth || $depth > 0) {

                foreach($values as $key => $value) {

                    foreach($this->makeDotNotation($value, ($prefix ? $prefix . '.' : null) . $key, --$depth) as $node => $data) {

                        $note[$node] = $data;

                    }

                }

            } else {

                $note[$prefix] = $values;

            }

        } elseif($prefix) {

            $note[$prefix] = $values;

        } else {

            throw new Exception\BadDotNotation();

        }

        return $note;

    }

    /**
     * @brief       Resolves all changes that are to be made to the current MongoDB Document.
     *
     * @detail      Changes are any altered values, new fields or removed fields.
     *
     * @since       1.0.0
     *
     * @return      Array An array of changed values in MongoDB 'update format' for use in a mongoDB
     *              MongoCollection::update() method call
     */
    public function resolve() {

        $update = array();

        if($set = $this->makeDotNotation($this->getChanges())) {

            $update[0]['$set'] = $set;

        }

        $removes = $this->getRemoves();

        if($unset = $this->makeDotNotation($removes)) {

            $update[0]['$unset'] = $unset;

            /*
             * This fixes a quirk in MongoBD where a $pull is required for numerically keyed arrays.  Doing an unset on a
             * numerically keyed array
             * will leave the element in place with a value of NULL, so we do pull.
             */
            foreach($unset as $item => $value) {

                $pos = strrpos($item, '.');

                if(is_numeric(substr($item, $pos + 1))) {

                    $update[1]['$pull'][substr($item, 0, $pos)] = null;

                }

            }

        }

        foreach($this->new as $key => $value) {

            if($value instanceof \Hazaar\Map || $value instanceof EmbeddedDocument) {

                $value = $value->toArray();

            }

            $update[0]['$set'][$key] = $value;

        }

        $this->resolveRecursive($update, $this->values);

        return $update;

    }

    /**
     * @brief       Recursively resolve changes to the document elements.
     *
     * @detail      This looks for changes in the current document as well as recursing into any embedded documents or
     *              arrays.
     *
     * @since       1.0.0
     *
     * @return      array An array of changed elements in dot notation.
     */
    private function resolveRecursive(&$update, $values, $parent = null) {

        if(! is_array($values) && ! $values instanceof EmbeddedDocument)
            return null;

        $new = array();

        /*
         * Now we need to find any children that have new values
         */
        foreach($values as $key => $value) {

            if($value instanceof EmbeddedDocument) {

                /*
                 * If this embedded document is requesting to be removed, don't worry about it.
                 */
                if($value->isRemoved())
                    continue;

                /*
                 * If we are cycling through a mongo array, check if this is something being removed first
                 */
                if($values instanceof EmbeddedDocument && in_array($key, $values->getRemoves()))
                    continue;

                if($value->hasChanges()) {

                    $node = ($parent ? $parent . '.' : null) . $key;

                    $changes = $value->getChanges();

                    /*
                     * If the new changes are an associative array, but the existing array is numerically keyed,
                     * then we need to rewrite the whole array as an object.
                     */
                    if(is_assoc($changes) !== is_assoc($value->toArray(true))) {

                        $update[0]['$set'][$node] = $value->toArray();

                    } else {

                        $this->makeDotNotation($changes, $node, null, $update[0]['$set']);

                    }

                }

                if($value->hasNew()) {

                    /*
                     * If the array exists, but is empty, we need to replace it.
                     */
                    if($value->count(true) == 0) {

                        $node = ($parent ? $parent . '.' : null) . $key;

                        $update[0]['$set'][$node] = $value->toArray();

                    } else {

                        $new = $value->getNew();

                        foreach($new as $new_key => $new_values) {

                            $node = ($parent ? $parent . '.' : null) . $key;

                            /*
                             * Check if the new value is an associative or indexed array
                             */
                            //if(is_assoc($new) !== is_assoc($value->toArray(true)))

                            /*
                             * If it is associative, we set values by key
                             */
                            if(is_assoc($new)) {

                                /*
                                 * If the old value is not assoc, then we need to replace the whole thing
                                 */
                                if(! is_assoc($value->toArray(true))) {

                                    $update[0]['$set'][$node] = $value->toArray();

                                } else {

                                    $node .= '.' . $new_key;

                                    if($new_values instanceof EmbeddedDocument) {

                                        $update[0]['$set'][$node] = $new_values->toArray();

                                    } else {

                                        $update[0]['$set'][$node] = $new_values;

                                    }

                                }

                                /*
                                 * If it is indexed we push the values on to the array
                                 */
                            } else {

                                if($new_values instanceof EmbeddedDocument) {

                                    $update[0]['$pushAll'][$node][] = $new_values->toArray();

                                } else {

                                    $update[0]['$pushAll'][$node][] = $new_values;

                                }

                            }

                        }

                    }

                }

                $node = ($parent ? $parent . '.' : null) . $key;

                $this->resolveRecursive($update, $value, $node);

            }

        }

        return $new;

    }

    /**
     * @brief       Resolves any removed elements to generate a $pull update on the MongoDB database.
     *
     * @detail      This is because a normal $unset on an array will merely set the value to 'null' so we need to pull
     *              the leftover null values.
     *
     * @since       1.0.0
     *
     * @param       Array  $removes An array of values that have been removed that we need to generate $pull for.
     *
     * @param       string $parent  The current parent node to prepend.
     *
     * @return      Array An array of statements for use in a $pull modifier.
     */
    private function resolveRemoves($removes, $parent = null) {

        $array = array();

        foreach($removes as $key => $value) {

            if(! is_array($value)) {

                return $parent;

            } else {

                $node = ($parent ? $parent . '.' : null) . $key;

                $remove = $this->resolveRemoves($value, $node);

                if($parent)
                    return $remove;
            }

            $array[$key] = $remove;

        }

        return $array;

    }

}

