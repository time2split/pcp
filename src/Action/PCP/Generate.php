<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\Arrays;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\MoreActions;
use Time2Split\PCP\Action\PCP\Generate\Generator;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PCP\Generate\InstructionStorage;
use Time2Split\PCP\Action\PCP\Generate\Instruction\Factory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\CElements;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\PCPPragma;

final class Generate extends BaseAction
{
    public const tag_remains = 'generate.remains';

    private const wd = 'generate';

    private const DefaultConfig = [
        'generate' => [
            'add' => null,
            'drop' => null,
            'tags' => null,
            'targets' => '.',
            'targets.prototype' => '${targets}',
            'targets.function' => '${targets}',
            'targets.code' => '${targets}',

            'name.base' => null,
            'name.prefix' => null,
            'name.suffix' => null,
            'name.format' => '${name.prefix}${name.base}${name.suffix}'
        ]
    ];

    private Factory $ifactory;

    private InstructionStorage $istorage;

    private ?CContainer $currentCContainer = null;

    private array $instructions;

    private ReadingOneFile $oneFileData;

    public function setConfig(Configuration $config): void
    {
        parent::setConfig(Configurations::hierarchy(
            App::emptyConfiguration()->mergeTree(self::DefaultConfig),
            $config,
            App::emptyConfiguration()
        ));
    }

    public static function isPCPGenerate(CElement|CContainer $element, ?string $firstArg = null): bool
    {
        if ($element instanceof CContainer) {

            if (!$element->isPCPPragma())
                return false;

            $element = $element->getCppDirective();
        }
        return CElements::isPCPCommand($element, 'generate', $firstArg);
    }

    public static function PCPIsGenerate(PCPPragma $element, ?string $firstArg = null): bool
    {
        return CElements::PCPIsCommand($element, 'generate', $firstArg);
    }

    // ========================================================================
    private bool $waitingForEnd = false;

    public function hasMonopoly(): bool
    {
        return $this->waitingForEnd;
    }

    public function onCommand(ActionCommand $command): MoreActions
    {
        if ($command->getName() !== 'generate')
            return MoreActions::empty();

        $arguments = $command->getArguments();

        if ($this->waitingForEnd) {

            if (isset($arguments['end'])) {
                $this->waitingForEnd = false;
            }
        } elseif (isset($arguments['begin']))
            $this->waitingForEnd = true;
        else
            $this->doInstruction($command);

        return MoreActions::empty();
    }

    public function onMessage(CContainer $ccontainer): MoreActions
    {
        if ($ccontainer->isDeclaration()) {
            $this->currentCContainer = $ccontainer;
            $this->processCContainer($ccontainer);
        }
        return MoreActions::empty();
    }

    private function makeInstruction(Configuration $instruction): Configuration
    {
        // The order of the $instruction arguments is important
        $first = $instruction;
        $secnd = $this->config->subTreeCopy('generate');
        return Configurations::hierarchy($secnd, $first);
    }

    private function processCContainer(CContainer $ccontainer)
    {
        if ($ccontainer->isDeclaration())
            $this->processCDeclaration($ccontainer->getDeclaration());
    }

    private function processCDeclaration(CDeclaration $declaration)
    {
        if (! $declaration->getElementType()[CElementType::Function])
            return;

        foreach ($this->instructions as $actionCommand) {
            $i = $this->makeInstruction($actionCommand->getArguments());
            $this->istorage->put($this->ifactory->create($declaration, $i));
        }
        $this->instructions = [];
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::ReadingCElement:

                if (PhaseState::Start == $phase->state) {

                    if ($this->currentCContainer) {
                        $this->processCContainer($this->currentCContainer);
                        $this->currentCContainer = null;
                    }
                }
                break;

            case PhaseName::ProcessingFiles:

                if (PhaseState::Start == $phase->state) {
                    $this->goWorkingDir();

                    if (! \is_dir(self::wd))
                        \mkdir(self::wd);

                    $this->outWorkingDir();
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->goWorkingDir(self::wd);
                    $this->generate();
                    $this->outWorkingDir();
                }
                break;

            case PhaseName::OpeningDirectory:

                if (PhaseState::Run == $phase->state) {
                    $this->resetConfig();
                }
                break;

            case PhaseName::ReadingOneFile:

                if (PhaseState::Start == $phase->state) {
                    $this->oneFileData = $data;
                    $this->ifactory = new Factory($data);
                    $this->istorage = new InstructionStorage($data);
                    $this->resetConfig();
                    $this->instructions = [];
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->flushFileInfos();
                }
                break;
        }
    }

    private function resetConfig(): void
    {
        $this->config->clear();
    }

    // ========================================================================
    private function doInstruction(ActionCommand $actionCommand): void
    {
        if ($this->instructionWithoutSubject($actionCommand))
            return;

        $args = $actionCommand->getArguments();

        if (isset($args['function']) || isset($args['prototype'])) {
            $this->instructions[] = $actionCommand;
        } else {
            // Update the configuration
            $args = Arrays::arrayMapKey(fn($k) => "generate.$k", $args->toArray());
            $this->config->merge($args);
        }
    }

    private function instructionWithoutSubject(ActionCommand $actionCommand): bool
    {
        try {
            $i = $this->makeInstruction($actionCommand->getArguments());
            $this->istorage->put($this->ifactory->createWithoutSubject($i));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========================================================================
    private function flushStorage(): void
    {
        $codes = $this->istorage->getTargetsCode();
        $finfo = $this->oneFileData->fileInfo;
        $filePath = "$finfo.gen.php";

        if ($codes->isEmpty()) {

            // Clean existing files
            if (\is_file($filePath)) {
                \unlink($filePath);
            }
        } else {
            $path = $finfo->getPath();

            if (\strlen($path) !== 0 && ! \is_dir($path))
                \mkdir($path, recursive: true);

            $export = $codes->array_encode();

            if ($export !== @include $filePath) {

                if (false === IO::printPHPFile($filePath, $export))
                    throw new \Exception("Unable to write " . getcwd() . "/$filePath");
            }
        }
    }

    private function flushFileInfos(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushStorage();
        $this->outWorkingDir();
    }

    // ========================================================================


    private function generate(): void
    {
        (new Generator($this->config))->generate();
    }
}
