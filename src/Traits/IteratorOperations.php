<?php

namespace Antevemus\ALinq\Traits;

/**
 * IteratorOperations Trait
 *
 * Provides iterator operations for collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait IteratorOperations
{
    /**
     * Get the current element in the collection
     */
    public function current()
    {
        return current($this->items);
    }

    /**
     * Get the key of the current element
     */
    public function key()
    {
        return key($this->items);
    }

    /**
     * Move the internal pointer to the next element
     */
    public function next()
    {
        return next($this->items);
    }

    /**
     * Move the internal pointer to the previous element
     */
    public function prev()
    {
        return prev($this->items);
    }

    /**
     * Reset the internal pointer to the first element
     */
    public function reset()
    {
        return reset($this->items);
    }

    /**
     * Move the internal pointer to the last element
     */
    public function end()
    {
        return end($this->items);
    }
}
