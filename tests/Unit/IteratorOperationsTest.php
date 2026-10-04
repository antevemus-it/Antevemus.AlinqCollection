<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Iterator Operations Trait
 */
class IteratorOperationsTest extends TestCase
{
    public function testCurrentReturnsCurrentElement(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $this->assertEquals('a', $collection->current());
    }

    public function testKeyReturnsCurrentKey(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $this->assertEquals(0, $collection->key());
    }

    public function testKeyWithAssociativeArray(): void
    {
        $collection = ALinqCollection::from(['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals('foo', $collection->key());
    }

    public function testNextMovesToNextElement(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $collection->next();
        $this->assertEquals('b', $collection->current());

        $collection->next();
        $this->assertEquals('c', $collection->current());
    }

    public function testPrevMovesToPreviousElement(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $collection->next();
        $collection->next();
        $this->assertEquals('c', $collection->current());

        $collection->prev();
        $this->assertEquals('b', $collection->current());
    }

    public function testResetMovesToFirstElement(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $collection->next();
        $collection->next();
        $this->assertEquals('c', $collection->current());

        $collection->reset();
        $this->assertEquals('a', $collection->current());
    }

    public function testEndMovesToLastElement(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $collection->end();
        $this->assertEquals('c', $collection->current());
    }

    public function testIteratorOperationsSequence(): void
    {
        $collection = ALinqCollection::from([10, 20, 30, 40, 50]);

        // Start at first
        $this->assertEquals(10, $collection->current());
        $this->assertEquals(0, $collection->key());

        // Move forward
        $collection->next();
        $this->assertEquals(20, $collection->current());

        // Jump to end
        $collection->end();
        $this->assertEquals(50, $collection->current());

        // Move back
        $collection->prev();
        $this->assertEquals(40, $collection->current());

        // Reset
        $collection->reset();
        $this->assertEquals(10, $collection->current());
    }

    public function testIteratorOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertFalse($collection->current());
        $this->assertNull($collection->key());
    }

    public function testIteratorWithAssociativeKeys(): void
    {
        $collection = ALinqCollection::from([
            'first' => 'one',
            'second' => 'two',
            'third' => 'three'
        ]);

        $this->assertEquals('one', $collection->current());
        $this->assertEquals('first', $collection->key());

        $collection->next();
        $this->assertEquals('two', $collection->current());
        $this->assertEquals('second', $collection->key());
    }
}
