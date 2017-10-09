<?php

namespace Medipim\Api\V3;

class MedipimApiV3Error extends \RuntimeException implements \ArrayAccess
{
    /** @var array */
    private $body;

    public function __construct($message, array $body = [])
    {
        parent::__construct($message);
        $this->body = $body;
    }

    //
    // ArrayAccess
    //

    public function offsetExists($offset)
    {
        return isset($this->body[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->body[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception("This object is read-only");
    }

    public function offsetUnset($offset)
    {
        throw new \Exception("This object is read-only");
    }
}
