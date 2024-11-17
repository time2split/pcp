<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\Set;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\CElements;
use Time2Split\PCP\C\Element\CElementType;

abstract class CActionSubject
{

    protected CElement $subject;

    private Configuration $config;

    protected Set $tags;

    protected function __construct(CElement $subject)
    {
        $this->subject = $subject;
        $this->tags = CElements::tagsOf($subject);
        $this->config = Configurations::ofTree();

        $this->config['tags'] = $this->tags;

        $ctypes = $subject->getElementType($subject);

        // Set C informations
        /** @var CDeclaration $subject */
        if ($ctypes[CElementType::Function])
            $this->config->mergeTree([
                'C' => [
                    'specifiers' => $subject->getSpecifiers(),
                    'identifier' => $subject->getIdentifier(),
                ]
            ]);
    }

    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    public function getSubject(): CElement
    {
        return $this->subject;
    }

    public function getTags(): Set
    {
        return $this->tags;
    }
}
