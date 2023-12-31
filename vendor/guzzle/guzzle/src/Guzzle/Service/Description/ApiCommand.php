<?php

namespace Guzzle\Service\Description;

use Guzzle\Common\Collection;
use Guzzle\Common\Exception\InvalidArgumentException;
use Guzzle\Service\Exception\ValidationException;
use Guzzle\Service\Inflector;
use Guzzle\Service\Inspector;

/**
 * Data object holding the information of an API command
 */
class ApiCommand
{
    /**
     * @var string Default command class to use when none is specified
     */
    const DEFAULT_COMMAND_CLASS = 'Guzzle\\Service\\Command\\DynamicCommand';

    /**
     * @var string Annotation used to specify Guzzle service description info
     */
    const GUZZLE_ANNOTATION = '@guzzle';

    /**
     * @var array Parameters
     */
    protected $params = array();

    /**
     * @var string Name of the command
     */
    protected $name;

    /**
     * @var string Documentation
     */
    protected $doc;

    /**
     * @var string HTTP method
     */
    protected $method;

    /**
     * @var string HTTP URI of the command
     */
    protected $uri;

    /**
     * @var string Class of the command object
     */
    protected $class;

    /**
     * @var array Cache of parsed Command class ApiCommands
     */
    protected static $apiCommandCache = array();

    /**
     * Constructor
     *
     * @param array $config Array of configuration data using the following keys
     *      - name:   Name of the command
     *      - doc:    Method documentation
     *      - method: HTTP method of the command
     *      - uri:    URI routing information of the command
     *      - class:  Concrete class that implements this command
     *      - params: Associative array of parameters for the command with each
     *                parameter containing the following keys:
     *                - name:         Parameter name
     *                - type:         Type of variable (boolean, integer, string,
     *                                array, class name, etc...)
     *                - type_args:    Argument(s) to pass to the type validation
     *                - required:     Whether or not the parameter is required
     *                - default:      Default value
     *                - doc:          Documentation
     *                - min_length:   Minimum length
     *                - max_length:   Maximum length
     *                - location:     One of query, path, header, or body
     *                - location_key: Location key mapping value (e.g. query string value name)
     *                - static:       Whether or not the param can be changed
     *                                from this value
     *                - prepend:      Text to prepend when adding this value
     *                - append:       Text to append when adding this value
     *                - filters:      Comma separated list of filters to run the
     *                                value through.  Must be a callable. Can
     *                                call static class methods by separating the
     *                                class and function with ::.
     */
    public function __construct(array $config)
    {
        $this->name = isset($config['name']) ? trim($config['name']) : '';
        $this->doc = isset($config['doc']) ? trim($config['doc']) : '';
        $this->method = isset($config['method']) ? trim($config['method']) : '';
        $this->uri = isset($config['uri']) ? trim($config['uri']) : '';
        $this->class = isset($config['class']) ? trim($config['class']) : self::DEFAULT_COMMAND_CLASS;

        if (!empty($config['params'])) {
            foreach ($config['params'] as $name => $param) {
                $this->params[$name] = $param instanceof ApiParam ? $param : new ApiParam($param);
            }
        }
    }

    /**
     * Create an ApiCommand object from a class and its docblock
     *
     * The following is the format for @guzzle arguments:
     * @guzzle argument_name [default="default value"] [required="true|false"] [type="registered constraint name"] [type_args=""] [doc="Description of argument"]
     * Example: @guzzle my_argument default="hello" required="true" doc="Set the argument to control the widget..."
     *
     * @param string $className Name of the class
     *
     * @return ApiCommand
     */
    public static function fromCommand($className)
    {
        if (!isset(self::$apiCommandCache[$className])) {

            $reflection = new \ReflectionClass($className);

            // Get all of the @guzzle annotations from the class
            $matches = array();
            $params = array();
            preg_match_all('/' . self::GUZZLE_ANNOTATION . '\s+([A-Za-z0-9_\-\.]+)\s*([A-Za-z0-9]+=".+")*/', $reflection->getDocComment(), $matches);

            // Parse the docblock annotations
            if (!empty($matches[1])) {
                foreach ($matches[1] as $index => $match) {
                    // Add the matched argument to the array keys
                    $params[$match] = array();
                    if (isset($matches[2])) {
                        // Break up the argument attributes by closing quote
                        foreach (explode('" ', $matches[2][$index]) as $part) {
                            $attrs = array();
                            // Find the attribute and attribute value
                            preg_match('/([A-Za-z0-9]+)="(.+)"*/', $part, $attrs);
                            if (isset($attrs[1]) && isset($attrs[0])) {
                                // Sanitize the strings
                                if ($attrs[2][strlen($attrs[2]) - 1] == '"') {
                                    $attrs[2] = substr($attrs[2], 0, strlen($attrs[2]) - 1);
                                }
                                $params[$match][$attrs[1]] = $attrs[2];
                            }
                        }
                    }
                    $params[$match] = new ApiParam($params[$match]);
                }
            }

            self::$apiCommandCache[$className] = new ApiCommand(array(
                'name'   => str_replace('\\_', '.', Inflector::snake(substr($className, strpos($className, 'Command') + 8))),
                'class'  => $className,
                'params' => $params
            ));
        }

        return self::$apiCommandCache[$className];
    }

    /**
     * Get as an array
     *
     * @return true
     */
    public function toArray()
    {
        return array(
            'name'   => $this->name,
            'doc'    => $this->doc,
            'method' => $this->method,
            'uri'    => $this->uri,
            'class'  => $this->class,
            'params' => $this->params
        );
    }

    /**
     * Get the params of the command
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get a single parameter of the command
     *
     * @param string $param Parameter to retrieve by name
     *
     * @return ApiParam|null
     */
    public function getParam($param)
    {
        return isset($this->params[$param]) ? $this->params[$param] : null;
    }

    /**
     * Get the HTTP method of the command
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get the concrete command class that implements this command
     *
     * @return string
     */
    public function getConcreteClass()
    {
        return $this->class;
    }

    /**
     * Get the name of the command
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the documentation for the command
     *
     * @return string
     */
    public function getDoc()
    {
        return $this->doc;
    }

    /**
     * Get the URI that will be merged into the generated request
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Validates that all required args are included in a config object,
     * and if not, throws an InvalidArgumentException with a helpful error message.  Adds
     * default args to the passed config object if the parameter was not
     * set in the config object.
     *
     * @param Collection $config Configuration settings
     *
     * @throws ValidationException when validation errors occur
     */
    public function validate(Collection $config, Inspector $inspector = null)
    {
        $inspector = $inspector ?: Inspector::getInstance();
        $typeValidation = $inspector->getTypeValidation();
        $errors = array();

        foreach ($this->params as $name => $arg) {

            $currentValue = $config->get($name);
            $configValue = $arg->getValue($currentValue);

            // Inject configuration information into the config value
            if ($configValue && is_string($configValue)) {
                $configValue = $config->inject($configValue);
            }

            // Ensure that required arguments are set
            if ($arg->getRequired() && ($configValue === null || $configValue === '')) {
                $errors[] = 'Requires that the ' . $name . ' argument be supplied.' . ($arg->getDoc() ? '  (' . $arg->getDoc() . ').' : '');
                continue;
            }

            // Ensure that the correct data type is being used
            if ($typeValidation && $configValue !== null && $argType = $arg->getType()) {
                $validation = $inspector->validateConstraint($argType, $configValue, $arg->getTypeArgs());
                if ($validation !== true) {
                    $errors[] = $name . ': ' . $validation;
                    $config->set($name, $configValue);
                    continue;
                }
            }

            $configValue = $arg->filter($configValue);

            // Update the config value if it changed
            if (!$configValue !== $currentValue) {
                $config->set($name, $configValue);
            }

            // Check the length values if validating data
            $argMinLength = $arg->getMinLength();
            if ($argMinLength && strlen($configValue) < $argMinLength) {
                $errors[] = 'Requires that the ' . $name . ' argument be >= ' . $arg->getMinLength() . ' characters.';
            }

            $argMaxLength = $arg->getMaxLength();
            if ($argMaxLength && strlen($configValue) > $argMaxLength) {
                $errors[] = 'Requires that the ' . $name . ' argument be <= ' . $arg->getMaxLength() . ' characters.';
            }
        }

        if (!empty($errors)) {
            $e = new ValidationException('Validation errors: ' . implode("\n", $errors));
            $e->setErrors($errors);
            throw $e;
        }
    }
}
