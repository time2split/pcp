<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\Help\ArrayCodec;

final class TargetsCode implements ArrayCodec, \IteratorAggregate
{

    private \SplObjectStorage $targetCodes;

    public function __construct()
    {
        $this->targetCodes = new \SplObjectStorage();
    }

    public function putCode(GeneratedCode $code, Target ...$targets): void
    {
        foreach ($targets as $target) {
            $codes = $this->targetCodes[$target] ?? [];

            $codes[] = $code;
            $this->targetCodes[$target] = $codes;
        }
    }

    public function getIterator(): \Iterator
    {
        foreach ($this->targetCodes as $target)
            yield $target => $this->targetCodes[$target];
    }

    public function isEmpty(): bool
    {
        return \count($this->targetCodes) === 0;
    }

    // ========================================================================
    public function array_encode(): array
    {
        $instrIndex = new \SplObjectStorage();
        $targetCodes = [];
        $i = 0;

        foreach ($this->targetCodes as $target) {
            $codeIndexes = [];

            // Retrieves the index of the instruction
            foreach ($this->targetCodes[$target] as $code) {
                $idx = $instrIndex[$code] ?? null;

                if (! isset($idx))
                    $instrIndex[$code] = $idx = $i ++;

                $codeIndexes[] = $idx;
            }
            $k = (string) $target->getFileInfo();
            $targetCodes[$k] = $codeIndexes;
        }
        $instructions = \array_map( //
        fn (GeneratedCode $c) => $c->array_encode(), //
        \iterator_to_array($instrIndex));
        $ret = [
            'instructions' => $instructions,
            'targetCodes' => $targetCodes
        ];
        return $ret;
    }

    public static function array_decode(array $array): self
    {
        $ret = new self();
        $targetsStorage = new TargetStorage();
        $instructions = \array_map(GeneratedCode::array_decode(...), $array['instructions']);

        foreach ($array['targetCodes'] as $target => $codes) {
            $target = $targetsStorage->getTarget($target);

            foreach ($codes as $idx)
                $ret->putCode($instructions[$idx], $target);
        }
        return $ret;
    }
}