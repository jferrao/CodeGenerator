<?php

namespace Generator;



require_once 'Block.php';



use Generator\Block;



/**
 * Generates nicely formated PHP code
 * 
 * @author jferrao
 * @link https://github.com/jferrao/CodeGenerator
 */
class Code extends \ArrayObject
{

    /**
     * Class block identifier
     */
    const BLOCK_CLASS           = 'class';
    
    /**
     * Function block identifier
     */
    const BLOCK_FUNCTION        = 'function';
    
    /**
     * Documentation block identifier
     */
    const BLOCK_DOC             = 'doc';
    
    /**
     * Private visibility definition
     */
    const VISIBILITY_PRIVATE    = 'private';

    /**
     * Protected visibility definition
     */
    const VISIBILITY_PROTECTED  = 'protected';

    /**
     * Public visibility  definition
     */
    const VISIBILITY_PUBLIC     = 'public';



    /**
     * @var int Current block pointer
     */
    protected $current = 0;

    /**
     * @var int Nested blocks depth
     */
    protected $depth   = 0;

    /**
     * @var array The code block stack
     */
    protected $block   = array();


    
    /**
     * Constructor.
     * 
     * @param integer $depth Identation depth
     */
    public function __construct($depth = 0)
    {
        $this->depth = $depth;
    }

    /**
     * Implement prepend method for ArrayObject.
     * 
     * @param mixed $value
     * @return \Generator\Code
     */
    public function prepend($value)
    {
        $array = $this->getArrayCopy();
        array_unshift($array, $value);
        $this->exchangeArray($array);
        unset($array);
        return $this;
    }

    /**
     * Starts a class block.
     * 
     * @param string $name The class name
     * @param string $extends The class name from which it extends from
     * @param string $implements The name of the interface that it implements
     * @param boolean $abstract Whether it should be declared as an abstract class or not
     * @return \Generator\Code
     */
    public function startClass($name, $extends = null, $implements = null, $abstract = false)
    {
        $name = "class {$name}";

        if ($abstract === true) {
            $name = "abstract {$name}";
        }

        if (null !== $extends) {
            $name .= " extends {$extends}";
        }
        if (null !== $implements) {
            $name .= " implements {$implements}";
        }
        $this->write($name);
        $this->write('{');
        $this->openBlock('class');
        return $this;
    }

    /**
     * Closes a class block.
     * 
     * @return \Generator\Code
     */
    public function endClass()
    {
        $this->closeBlock();
        $this->write('}', null);
        return $this;
    }

    /**
     * Starts a function block.
     * 
     * @param string $name The function name
     * @param array $params Associative array of the function parameters
     * @param string $visibility The function visibility
     * @return \Generator\Code
     */
    public function startFunction($name, $params = null, $visibility = null)
    {
        $name = "function {$name}";

        // Check if it is inside a class block
        if (isset($this->block[$this->current]) && $this->block[$this->current]->getType() == 'class')
        {
            if (null === $visibility) {
                $visibility = self::VISIBILITY_PUBLIC;
            }
            if (null !== $visibility && in_array($visibility, array(self::VISIBILITY_PUBLIC, self::VISIBILITY_PROTECTED, self::VISIBILITY_PRIVATE))) {
                $name = "{$visibility} {$name}";
            }
        }

        if (is_array($params)) {
            $params = implode(', ', $params);
        }
        $name = "{$name}($params)";

        $this->write($name);
        $this->write('{');
        $this->openBlock('function');
        return $this;
    }

    /**
     * Closes a function block.
     * 
     * @return \Generator\Code
     */
    public function endFunction()
    {
        $this->closeBlock();
        $this->write('}', null);
        return $this;
    }

    /**
     * Declares a constant on a class or global scope depending on whether
     * it is inside a class block or not.
     * 
     * @param string $name The constant name
     * @param mixed $value The constant value
     * @param boolean $defined Whether to apply a defined check or not
     * @return \Generator\Code
     */
    public function constant($name, $value, $defined = false)
    {
        if (null === $value || is_bool($value) || is_array($value)) {
            $value = preg_replace('/(NULL)/', 'null', var_export($value, true));
        }

        $code = '';
        if (isset($this->block[$this->current]) && $this->block[$this->current]->getType() == 'class') {
            $code .= "const {$name} = {$value}";
        } else {        
            if ($defined === true) {
                $code .= "defined('{$name}') || ";
            }
            $code .= "define('{$name}', {$value})";
        }
        $this->write($code);
        return $this;
    }

    /**
     * Declares a variable or class property declaration.
     * 
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @param string $context Variable context
     * @param string $visibility  Variable visibility
     * @return \Generator\Code
     */
    public function variable($name, $value, $context = null, $visibility = null)
    {
        if (strpos($name, '$') !== 0) {
            throw new Exception('Variable name must start with $ sign');
        }

        if (isset($this->block[$this->current]) && $this->block[$this->current]->getType() == 'class')
        {
            if (null === $visibility) {
                $visibility = self::VISIBILITY_PUBLIC;
            }
            if (null !== $visibility && in_array($visibility, array(self::VISIBILITY_PUBLIC, self::VISIBILITY_PROTECTED, self::VISIBILITY_PRIVATE))) {
                $name = "{$visibility} {$name}";
            }
        }

        if (null !== $context) {
            $name = preg_replace('/(\$)/', "{$context}->", $name);
        }

        if (null === $value || is_bool($value)) {
            $value = preg_replace('/(NULL)/', 'null', var_export($value, true));
        }

        // Trying to improve indentation on arrays returned by var_export()
        if (is_array($value)) {
            $value = preg_replace('/(NULL)/', 'null', var_export($value, true));
            $value = preg_replace('/(array \()/', 'array(', $value);                                                    // Remove space in "array ()"
            $value = preg_replace('/(\.*)\s=>\s\n(.*)\n/', "$1 => array(\n", $value);                                   // Remove line feed in the array declaration
            $value = preg_replace('/(\s)\1/', str_repeat(' ', 4), $value);                                              // Transform from 2 to 4 spaces
            if ($this->depth > 0) {
                $value = preg_replace('/(\s)(\1{2,}+)/', str_repeat(' ', 4 * $this->depth) . ' ' . '$2', $value);       // Adjust spaces to the depth level
                $value = preg_replace('/(\))$/', str_pad(')', (4 * $this->depth) + 1, ' ', STR_PAD_LEFT), $value);      // Adjust the final ")"
            }
        }

        $code = "{$name} = {$value}";
        $this->write($code);

        return $this;
    }

    /**
     * Include or include once statement.
     * 
     * @param string $file The filename of the file to be included
     * @param boolean $once Whether to use an include_once or include statement
     * @return \Generator\Code
     */
    public function includeFile($file, $once = true)
    {
        $function = ($once === true) ? 'include_once' : 'include';
        if (preg_match('/\'|\"/', $file)) {
            $this->write("{$function} {$file}");
        } else {
            $this->write("{$function} '{$file}'");
        }
        return $this;
    }

    /**
     * Require or require_once statement.
     * 
     * @param string $file The filename of the file to be included
     * @param boolean $once Whether to use a require_once or require statement
     * @return \Generator\Code
     */
    public function requireFile($file, $once = true)
    {
        $function = ($once === true) ? 'require_once' : 'require';
        if (preg_match('/\'|\"/', $file)) {
            $this->write("{$function} {$file}");
        } else {
            $this->write("{$function} '{$file}'");
        }
        return $this;
    }

    /**
     * Starts an if block.
     * 
     * @param string $condition The statement condition
     * @return \Generator\Code
     */
    public function startIfCondition($condition)
    {
        $this->write("if ({$condition}) {", null);
        $this->openBlock();
        return $this;
    }

    /**
     * Starts an else block.
     * 
     * @return \Generator\Code
     */
    public function elseCondition()
    {
        $this->closeBlock();
        $this->write("} else {", null);
        $this->openBlock();
        return $this;
    }

    /**
     * Starts an elseif block.
     * 
     * @param string $condition The statementen condition
     * @return \Generator\Code
     */
    public function elseIfCondition($condition)
    {
        $this->closeBlock();
        $this->write("} elseif ({$condition}) {", null);
        $this->openBlock();
        return $this;
    }

    /**
     * Closes an if, elseif or else block.
     * 
     * @return \Generator\Code
     */
    public function endIfCondition()
    {
        $this->closeBlock();
        $this->write("}", null);
        return $this;
    }

    /**
     * Creates an arbitrary line of code.
     * 
     * @param string $code The arbitrary line of code
     * @param string $ending The ending to be applied to the line
     * @return \Generator\Code
     */
    public function write($code, $ending = ';')
    {
        if (strpos(trim($code), '//') === 0 ||
            strpos(trim($code), '/*') === 0 ||
            strpos(trim($code), '*/') === 0 ||
            strpos(ltrim($code), 'class ') !== false ||
            strpos(ltrim($code), 'function ') !== false ||
            strpos(trim($code), '{') === 0 ||
            strrpos(rtrim($code), '}') == strlen($code) - 1)
        {
            $ending = null;
        }

        if (isset($this->block[$this->current])) {
            // Prepend doc characters if inside a doc block
            if ($this->block[$this->current]->getType() == 'doc') {
                $code = ' * ' . $code;
                $code = str_pad($code, (4*$this->depth) - 4 + strlen($code), ' ', STR_PAD_LEFT);
                $ending = null;
            } else {
                $code = str_pad($code, (4*$this->depth) + strlen($code), ' ', STR_PAD_LEFT);
            }
            $this->block[$this->current]->append($code . $ending);
        } else {
            $this->append($code . $ending);
        }
        return $this;
    }

    /**
     * Stars a documentation block.
     * 
     * @return \Generator\Code
     */
    public function startDoc()
    {
        $this->write('/**');
        $this->openBlock('doc');
        return $this;
    }

    /**
     * Closes a documentation block.
     * 
     * @return \Generator\Code
     */
    public function endDoc()
    {
        $this->closeBlock();
        $this->write(' */');
        return $this;
    }

    /**
     * Single inline comment.
     * 
     * @param string $comment The comment
     * @return \Generator\Code
     */
    public function comment($comment)
    {
        $this->write('// ' . $comment);
        return $this;
    }

    /**
     * Blank line, usefull for generated code readability .
     * 
     * @return \Generator\Code
     */
    public function blankLine($lines = 1)
    {
        for ($l = 1; $l <= $lines; $l++) {
            $this->write('', false);
        }
        return $this;
    }



    /**
     * Creates a new block of code.
     * 
     * @param string $type Block type
     * @return \Spin\Generator\Code
     */
    private function openBlock($type = null)
    {
        $this->current++;
        $this->depth++;
        $this->block[$this->current] = new Block();
        if (null !== $type) {
            $this->block[$this->current]->setType($type);
        }
        return $this;
    }

    /**
     * Closes a block of code.
     * 
     * @return \Spin\Generator\Code
     */
    private function closeBlock()
    {
        if (isset($this->block[$this->current-1])) {
            $this->block[$this->current-1]->exchangeArray(array_merge($this->block[$this->current-1]->getArrayCopy(), $this->block[$this->current]->getArrayCopy()));
        } else {
            $this->exchangeArray(array_merge($this->getArrayCopy(), $this->block[$this->current]->getArrayCopy()));
        }
        unset($this->block[$this->current]);
        $this->current--;
        $this->depth--;
        return $this;
    }

    /**
     * Generate code.
     * 
     * @param boolean $asArray Whether the return value as string or array
     * @return array|string Return generated code
     */
    public function dump($asArray = false)
    {
        $this->prepend("<?php");
        $this->append("\n");
        if ($asArray === true) {
            return $this->getArrayCopy();
        }
        return implode("\n", $this->getArrayCopy());
    }

}
