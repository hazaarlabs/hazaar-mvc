<?php

namespace Hazaar\Model;

/**
 * Automatic implementing child model
 *
 * The ChildModel class is used to allow a strict model to automatically be implemented
 * by the implementing models field definition.  This is a field with type 'model' that
 * contains a parameter 'items', which is the child models field definition.
 *
 * More of these can be contained within each ChildModel object and there is no limit to
 * how far the child model definitions can recurse other than memory and the time you
 * are willing to spend defining your fields.
 */
class ChildModel extends Strict {

    /**
     * ChildModel constructor
     *
     * Child model extends the Model\Strict class and so operates almost exactly the same.
     * The only difference is how the field definition is defined.  Because this is not
     * an abstract class and there is no implementing class code, the definition is
     * provided by the parent class and is defines in a higher level class' field definition.
     *
     * @param mixed $field_definition The field definition for this strict model.
     * @param mixed $values The initial values to populate the model with.
     */
    function __construct($field_definition, $values = array()) {

        parent::loadDefinition($field_definition);

        if ($values)
            $this->populate($values);

    }

}

