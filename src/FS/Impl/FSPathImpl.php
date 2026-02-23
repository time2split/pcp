<?php

namespace Time2Split\PCP\FS\Impl;

use Closure;
use SplFileInfo;
use Time2Split\Help\Arrays;
use Time2Split\Help\Container\Impl\PathImpl;
use Time2Split\Help\Container\PathEdge;
use Time2Split\Help\Iterables;
use Time2Split\Help\Optional;
use Time2Split\Help\TriState;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSPath;
use Time2Split\PCP\FS\FSPathException;

class FSPathImpl
extends PathImpl
implements FSPath
{
    #[\Override]
    public function __toString(): string
    {
        return FS::implode($this);
    }

    #[\Override]
    public function setRooted(TriState $rooted): FSPath
    {
        return new static($rooted, $this->leafed(), $this);
    }

    #[\Override]
    public function setLeafed(TriState $leafed): FSPath
    {
        return new static($this->rooted, $leafed, $this);
    }

    private SplFileInfo $info;

    #[\Override]
    public function info(): \SplFileInfo
    {
        return $this->info ??= new \SplFileInfo("$this");
    }

    #[\Override]
    public function append(FSPath|string|iterable $path, ?TriState $forceLeafed = null): FSPath
    {
        if ($this->leafed() === TriState::Yes)
            throw new FSPathException(
                "Cannot append ($path) after a file",
                fsPath: $this
            );

        $next = FS::pathOf($path);
        $rooted = $this->rooted;
        $leafed = $forceLeafed ?? $next->leafed;
        $edges = Iterables::append($this, $next);
        $fsPathClass = match ($leafed) {
            TriState::Yes => FSFileImpl::class,
            TriState::No => FSDirImpl::class,
            TriState::Maybe => FSPathImpl::class,
        };
        return new $fsPathClass($rooted, $leafed, $edges);
    }

    #[\Override]
    function appendDir(FSPath|string|iterable $path): FSPath
    {
        return $this->append($path, TriState::No);
    }

    #[\Override]
    function appendFile(FSPath|string|iterable $path): FSPath
    {
        return $this->append($path, TriState::Yes);
    }

    #[\Override]
    public function inFS(bool $checkLeafType = true): bool
    {
        $leafed = $checkLeafType ? $this->leafed() : TriState::Maybe;

        return match ($leafed) {
            TriState::Yes => $this->info()->isFile(),
            TriState::No => $this->info()->isDir(),
            TriState::Maybe => \file_exists("$this"),
        };
    }

    #[\Override]
    function hasPrefix(FSPath $fsPrefix, bool $strictPrefix = false): bool
    {
        if (\count($this) < \count($fsPrefix))
            return false;

        return Iterables::listHasPrefixRelation(
            $fsPrefix,
            $this,
            fn(PathEdge $a, PathEdge $b) => $a->getLabel() === $b->getLabel(),
            $strictPrefix,
        );
    }

    #[\Override]
    function removePrefix(FSPath $fsPrefix): Optional
    {
        if (\count($fsPrefix) === 0)
            return Optional::of($this);
        if (!$this->hasPrefix($fsPrefix))
            return Optional::empty();
        if (\count($fsPrefix) === \count($this))
            return Optional::of(FS::pathOf(''));

        $edges = $this->toListOfElements();
        $edges = \array_slice($edges, \count($fsPrefix));
        return Optional::of(
            FS::pathOf(
                $edges,
                forceRoot: TriState::Maybe,
                forceLeaf: $this->leafed,
            )
        );
    }

    #[\Override]
    public function setLast(FSPath|string|Closure $setLast, TriState $leafed = TriState::Maybe): FSPath
    {
        if (\count($this) === 0)
            return $this;

        if ($setLast instanceof Closure) {
            $last = Arrays::lastValue($this->storage)->getLabel();
            $setLast = $setLast($last);
        }
        if (\is_string($setLast)) {
            $parts = $this->toListOfElements();
            $parts[\array_key_last($parts)] = $setLast;
            return FS::pathOf($parts, $this->rooted(), $leafed);
        } elseif ($setLast instanceof FSPath) {
            $parts = \array_slice($this->storage, 0, -1);
            return FS::pathOf([...$parts, ...$setLast->toListOfElements()], $this->rooted(), $setLast->leafed());
        } else {
            $type = \gettype($setLast);
            throw new \InvalidArgumentException("Invalid type for \$setLast: $type");
        }
    }
}
