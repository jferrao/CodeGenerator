<?php

namespace Generator;



/**
 * Code block container
 *
 * @author jferrao
 * @link https://github.com/jferrao/CodeGenerator
 */
class Block extends \ArrayObject
{

    /**
     * @var string Block type
     */
    protected $type = null;



    /**
     * Sets the block type.
     * 
     * @param type $type
     * @return \Block
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Returns the block type name.
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

}
