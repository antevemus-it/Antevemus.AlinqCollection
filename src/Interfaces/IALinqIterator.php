<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqIterator
 *
 * Interface for iterator operations on collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqIterator extends IALinqBaseCollection
{
    /**
     * Get the current element in the collection
     */
    public function current();

    /**
     * Get the key of the current element
     */
    public function key();

    /**
     * Move the internal pointer to the next element
     */
    public function next();

    /**
     * Move the internal pointer to the previous element
     */
    public function prev();

    /**
     * Reset the internal pointer to the first element
     */
    public function reset();

    /**
     * Move the internal pointer to the last element
     */
    public function end();
}