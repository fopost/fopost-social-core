<?php

declare(strict_types=1);

namespace Owlstack\Core\Content;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * A typed collection of Media objects.
 *
 * @implements IteratorAggregate<int, Media>
 */
class MediaCollection implements Countable, IteratorAggregate
{
    /** @var Media[] */
    private array $items;

    /**
     * @param Media[] $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Add a media item to the collection.
     */
    public function add(Media $media): self
    {
        $items = $this->items;
        $items[] = $media;

        return new self($items);
    }

    /**
     * Get all media items.
     *
     * @return Media[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first media item, or null if empty.
     */
    public function first(): ?Media
    {
        return $this->items[0] ?? null;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Filter media by type.
     */
    public function images(): self
    {
        return new self(array_filter($this->items, fn(Media $m) => $m->isImage()));
    }

    /**
     * Filter media to videos only.
     */
    public function videos(): self
    {
        return new self(array_filter($this->items, fn(Media $m) => $m->isVideo()));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
