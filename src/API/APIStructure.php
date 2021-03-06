<?php

/**
 * ManyChat API PHP library
 *
 * @copyright 2019 ManyChat, Inc.
 * @license https://opensource.org/licenses/MIT The MIT License
 */

namespace ManyChat\API;

use ManyChat\Exception\CallMethodNotSucceedException;
use ManyChat\Exception\InvalidActionException;
use ManyChat\Exception\NamespaceDepthExceedException;
use ManyChat\Utils\Request;

/**
 * Recursive API-structure builder and method-runner
 * @package ManyChat\API
 */
class APIStructure
{
    /** @var string $name APIStructure name */
    private $name;

    /** @var BaseAPI $api BaseAPI instance */
    private $api;

    /** @var APIStructure|null $parent Parent's APIStructure */
    private $parent;

    /** @var int Maximum namespace depth */
    private const MAX_NAMESPACE_DEPTH = 10;

    /** @var string Namespace separator */
    private const NAMESPACE_SEPARATOR = '/';

    public function __construct(string $name, BaseAPI $api, ?APIStructure $parent)
    {
        $this->name = $name;
        $this->api = $api;
        $this->parent = $parent;
    }

    /**
     * Creates child structure when user tries to access it as a property of current structure
     *
     * @param string $name The name of the child structure
     *
     * @return APIStructure Created child structure
     */
    public function __get(string $name): APIStructure
    {
        return new APIStructure($name, $this->api, $this);
    }

    /**
     * Throws exception when user tries to change some property
     *
     * @throws InvalidActionException always
     */
    public function __set($name, $value)
    {
        throw new InvalidActionException('ManyChat\\API\\APIStructure object doesn\'t support property setting');
    }

    /**
     * Returns that every property is set
     *
     * @return bool Always true
     */
    public function __isset(string $name): bool
    {
        return true;
    }

    /**
     * Returns current structure name
     *
     * @return string Current structure name
     */
    protected function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets current structure name
     *
     * @param string $name Current structure name
     */
    protected function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Returns BaseAPI instance
     *
     * @return BaseAPI BaseAPI INSTANCE
     */
    protected function getApi(): BaseAPI
    {
        return $this->api;
    }

    /**
     * Sets BaseAPI instance
     *
     * @param BaseAPI $api BaseAPI instance
     */
    protected function setApi(BaseAPI $api): void
    {
        $this->api = $api;
    }

    /**
     * Returns parent APIStructure
     *
     * @return APIStructure Parent APIStructure
     */
    protected function getParent(): ?APIStructure
    {
        return $this->parent;
    }

    /**
     * Sets current structure's parent structure
     *
     * @param APIStructure|null $parent Parent instance
     */
    protected function setParent(?APIStructure $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Builds full ManyChat's API method path for current structure
     * e.g. if instance is named 'bar' and has parent structure 'foo',
     * and this method is called for build method 'methodName',
     * it will return '/foo/bar/methodName' string
     *
     * @param string $name Method name
     *
     * @return string Full method path
     * @throws NamespaceDepthExceedException If the namespace structure depth exceed self::MAX_NAMESPACE_DEPTH
     */
    protected function getMethodAddress(string $name): string
    {
        $methodAddress = self::NAMESPACE_SEPARATOR.$this->name.self::NAMESPACE_SEPARATOR.$name;
        $parent = $this->parent;
        $namespaceDepth = 2;
        while ($parent !== null) {
            $methodAddress = self::NAMESPACE_SEPARATOR.$parent->name.$methodAddress;
            $parent = $parent->parent;

            $namespaceDepth++;
            if ($namespaceDepth > self::MAX_NAMESPACE_DEPTH) {
                throw new NamespaceDepthExceedException('Namespace depth limit exceed');
            }
        }

        return $methodAddress;
    }

    /**
     * Calls ManyChat's API method $name with $arguments in current structure
     * By default method is called as GET request, but you could specify it with
     * 'method_type' argument in $arguments
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     *
     * @return array The resulting array that was received from ManyChat API
     * @throws CallMethodNotSucceedException If the result of calling method didn't succeed
     * @throws InvalidActionException If invalid 'method_type' was passed
     * @throws NamespaceDepthExceedException If the namespace structure depth exceed self::MAX_NAMESPACE_DEPTH
     */
    public function __call(string $name, array $arguments): array
    {
        $methodType = Request::GET;
        if (isset($arguments['method_type'])) {
            $methodType = $arguments['method_type'];
            unset($arguments['method_type']);
        }

        $methodName = $this->getMethodAddress($name);

        if (!empty($arguments)) {
            $arguments = $arguments[0];
        }

        return $this->api->callMethod($methodName, $arguments, $methodType);
    }
}
