<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\InvalidActionCommandException;
use Time2Split\PCP\Action\MoreActions;
use Time2Split\PCP\Action\PCP\Generate\Generator;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PCP\Generate\InstructionStorage;
use Time2Split\PCP\Action\PCP\Generate\Instruction\Factory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\CExpression;
use Time2Split\PCP\FS\FSDir;

final class Generate extends BaseAction
{
    public const FILE_EXTENSION = 'gen.php';

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

    private ?CExpression $currentCExpression = null;

    private array $actionCommands;

    private ReadingOneFile $oneFileData;

    public function setConfig(Configuration $config): void
    {
        parent::setConfig(Configurations::hierarchy(
            App::emptyConfiguration()->mergeTree(self::DefaultConfig),
            $config,
            App::emptyConfiguration()
        ));
    }

    public static function checkActionCommand(ActionCommand $command, ?string $firstArg = null): bool
    {
        return ActionCommand::checkType($command, 'generate', $firstArg);
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

    public function onMessage(CElement $element): MoreActions
    {
        if ($element instanceof CDeclaration) {
            $this->currentCExpression = $element;
            $this->processCDeclaration($element);
        }
        return MoreActions::empty();
    }

    private function makeActionCommand(ActionCommand $actionCommand): ActionCommand
    {
        // The order of the $instruction arguments is important
        $first = $actionCommand->getArguments();
        $secnd = $this->config->subTreeCopy('generate');
        return $actionCommand->setArguments(Configurations::hierarchy($secnd, $first));
    }

    private function processCExpression(CExpression $expression)
    {
        if ($expression instanceof CDeclaration)
            $this->processCDeclaration($expression);
    }

    private function processCDeclaration(CDeclaration $declaration)
    {
        if (! $declaration->getElementType()[CElementType::Function])
            return;

        foreach ($this->actionCommands as $actionCommand) {
            $actionCommand = $this->makeActionCommand($actionCommand);
            $this->istorage->put($this->ifactory->create($actionCommand, $declaration));
        }
        $this->actionCommands = [];
    }

    private FSDir $fsGenRoot;

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::ReadingCElement:

                if (PhaseState::Start == $phase->state) {

                    if ($this->currentCExpression) {
                        $this->processCExpression($this->currentCExpression);
                        $this->currentCExpression = null;
                    }
                }
                break;

            case PhaseName::ProcessingFiles:

                if (PhaseState::Start == $phase->state) {
                    $this->fsGenRoot = $data->fsWDRoot->appendDir(self::wd);
                    $dir = (string)$this->fsGenRoot;

                    if (! \is_dir($dir)) {
                        \mkdir($dir);
                    }
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->generate(
                        $data->fsSrcRoot,
                        $this->fsGenRoot,
                    );
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
                    $this->actionCommands = [];
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

        if ($args->isPresent('function') || $args->isPresent('prototype')) {
            $this->actionCommands[] = $actionCommand;
        } elseif ($args->isPresent('area')) {
        } else {
            throw new InvalidActionCommandException($actionCommand);
        }
    }

    private function instructionWithoutSubject(ActionCommand $actionCommand): bool
    {
        if (!$actionCommand->getArguments()->isPresent('code'))
            return false;

        $actionCommand = $this->makeActionCommand($actionCommand);
        $this->istorage->put($this->ifactory->createWithoutSubject($actionCommand));
        return true;
    }

    // ========================================================================

    private function flushStorage(): void
    {
        $codes = $this->istorage->getTargetsCode();
        $fsFile = $this->oneFileData->fsFile;
        $genFile = $fsFile->info()->getFilename() . '.' . self::FILE_EXTENSION;
        $fsGen = $fsFile->setFile($genFile);

        if ($codes->isEmpty()) {

            // Clean existing files
            if ($fsGen->inFS()) {
                \unlink("$fsGen");
            }
        } else {
            $fsDir = $fsFile->getPath();

            if (\count($fsDir) > 0 && !$fsDir->inFS())
                \mkdir("$fsDir", recursive: true);

            $export = $codes->array_encode();

            if ($export !== @include "$fsGen") {

                if (false === IO::printPHPFile("$fsGen", $export))
                    throw new \Exception("Unable to write " . \getcwd() . "/$fsGen");
            }
        }
    }

    private function flushFileInfos(): void
    {
        try {
            $this->goWorkingDir(self::wd);
            $this->flushStorage();
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->outWorkingDir();
        }
    }

    // ========================================================================

    private function generate(FSDir $fsSrcRoot, FSDir $FSGenRoot): void
    {
        (new Generator(
            $fsSrcRoot,
            $FSGenRoot,
            $this->config,
        ))->generate();
    }
}
