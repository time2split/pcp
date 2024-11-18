<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Help\Set;
use Time2Split\Help\Sets;
use Time2Split\PCP\App;
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
        $this->config = App::emptyConfiguration();

        $this->config['tags'] = $this->tags;

        $ctypes = $subject->getElementType($subject);

        // Set C informations
        /** @var CDeclaration $subject */
        if ($ctypes[CElementType::Function])
            $this->config->merge([
                'C.specifiers' => Sets::arrayKeys($subject->getSpecifiers()),
                'C.identifier' =>  Sets::arrayKeys($subject->getIdentifier()),
            ]);
    }

    public static function of(CElement $subject): self
    {
        return new class($subject) extends CActionSubject {

            public function __construct(CElement $subject)
            {
                parent::__construct($subject);
            }
        };
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
