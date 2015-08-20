<?php

namespace Invoiced;

use ArrayAccess;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use ICanBoogie\Inflector;

class Object implements ArrayAccess, JsonSerializable
{
    /**
     * @staticvar array properties that cannot be updated
     */
    static $permanentAttributes = ['id'];

    /**
     * @var Client
     */
    protected $_client;

    /**
     * @var string
     */
    protected $_endpoint;

    /**
     * @var array
     */
    protected $_values;

    /**
     * @var array
     */
    protected $_unsaved;

    /**
     * @param Invoiced\Client $client API client instance
     * @param string          $id
     * @param array           $values
     */
    public function __construct(Client $client, $id = null, array $values = [])
    {
        $this->_client = $client;

        // generate the endpoint based on class name
        $inflector = Inflector::get();
        $classname = implode('', array_slice(explode('\\', get_class($this)), -1));
        $this->_endpoint = strtolower($inflector->pluralize($inflector->underscore($classname)));

        $this->_values = [];

        if ($id !== null) {
            $this->_endpoint .= '/'.$id;
            $this->_values = array_replace($values, ['id' => $id]);
            $this->_unsaved = [];
        }
    }

    // PHP magic methods

    public function __set($k, $v)
    {
        if ($v === "") {
            throw new InvalidArgumentException(
                'You cannot set \''.$k.'\'to an empty string. '
                .'We interpret empty strings as NULL in requests. '
                .'You may set obj->'.$k.' = NULL to delete the property'
            );
        }

        $this->_values[$k] = $v;

        if (!in_array($k, self::$permanentAttributes) && !in_array($k, $this->_unsaved)) {
            $this->_unsaved[] = $k;
        }
    }

    public function __isset($k)
    {
        return isset($this->_values[$k]);
    }

    public function __unset($k)
    {
        unset($this->_values[$k]);
        if ($key = array_search($k, $this->_unsaved) !== false) {
            unset($this->_unsaved[$key]);
        }
    }

    public function &__get($k)
    {
        if (array_key_exists($k, $this->_values)) {
            return $this->_values[$k];
        } else {
            $class = get_class($this);
            throw new Exception("Undefined property of $class: $k");
        }
    }

    public function __toString()
    {
        $class = get_class($this);

        return $class.' JSON: '.$this->__toJSON();
    }

    // implements ArrayAccess

    public function offsetSet($k, $v)
    {
        $this->$k = $v;
    }

    public function offsetExists($k)
    {
        return array_key_exists($k, $this->_values);
    }

    public function offsetUnset($k)
    {
        unset($this->$k);
    }

    public function offsetGet($k)
    {
        return array_key_exists($k, $this->_values) ? $this->_values[$k] : null;
    }

    public function keys()
    {
        return array_keys($this->_values);
    }

    // implements JsonSerializable

    public function jsonSerialize()
    {
        return $this->__toArray(true);
    }

    public function __toJSON()
    {
        return json_encode($this->__toArray(true), JSON_PRETTY_PRINT);
    }

    public function __toArray()
    {
        return $this->_values;
    }

    // Object getters

    /**
     * Gets the client instance used by this object.
     *
     * @return Invoiced\Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Retrieves an instance of this object given an ID.
     *
     * @param string $id
     * @param array  $opts optional options to pass on
     *
     * @return Invoiced\Object
     */
    public function retrieve($id, array $opts = [])
    {
        if (!$id) {
            throw new InvalidArgumentException("Missing ID.");
        }

        $response = $this->_client->request('get', "{$this->_endpoint}/$id", $opts);

        return new self($this->_client, $id, $response['body']);
    }
}
