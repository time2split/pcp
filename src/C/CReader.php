<?php

declare(strict_types=1);

namespace Time2Split\PCP\C;

use Time2Split\PCP\_internal\AbstractReader;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\File\Section;

final class CReader extends AbstractReader
{
    private const debug = false;

    // ========================================================================

    private function parseException(?string $msg): void
    {
        $fp = $this->fnav->getStream();
        $cursor = $this->fnav->getCursorPosition();
        throw new \RuntimeException("$fp: line($cursor->line) column($cursor->linePos) $msg");
    }

    private const C_DELIMITERS = '""\'\'(){}[]';


    // ========================================================================

    private function getPossibleSpecifiers(): array
    {
        $ret = [];

        while (true) {
            $text = $this->nextWord();

            if (0 === \strlen($text)) {
                return $ret;
            } else {
                $ret[] = $text;
            }
        }
    }

    private function getPointers(): array
    {
        $ret = [];

        while (true) {

            while ($this->nextChar() === '*')
                $ret[] = '*';

            $this->fungetc();

            while (true) {
                $text = $this->nextWord();

                if (0 === \strlen($text))
                    return $ret;

                $ret[] = $text;
            }
        }
    }

    // ========================================================================

    private array $states;

    private const zeroData = [
        'e' => []
    ];

    private function clearStates(): void
    {
        $this->states = [];
        $this->pushState(CReaderState::start, self::zeroData);
    }

    private function pushState(CReaderState $s, $data = null): void
    {
        $this->states[] = [
            $s,
            $data
        ];
    }

    private function popState(): array
    {
        if (empty($this->states))
            return [
                CReaderState::invalid,
                null
            ];

        return \array_pop($this->states);
    }

    // ========================================================================

    private function newElement(): array
    {
        return [
            'type' => CElementType::ofVariableDeclaration(),
            'cursor' => $this->fnav->getCursorPosition(),
            'items' => [],
            'infos' => []
        ];
    }

    private static function elementAddItems(array &$element, array $items, ?string $name = null): void
    {
        $element['items'] = \array_merge($element['items'], $items);

        if ($name !== null) {
            $element[$name][] = $items;
        }
    }

    private static function elementSet(array &$element, string $name, $v): void
    {
        if (!isset($element[$name]) && isset($v))
            $element[$name] = $v;
    }

    private static function mergeElements(array &$element, array $toMerge): void
    {
        $prevNbItems = \count($element['items']);
        self::elementAddItems($element, $toMerge['items']);

        if (isset($toMerge['identifier']))
            self::setElementIdentifier($element, $toMerge['identifier']['pos'] + $prevNbItems);
    }

    private static function setElementIdentifier(array &$element, int $pos): void
    {
        $element['identifier'] = [
            'pos' => $pos
        ];
    }

    private static function makeElementIdentifier(array &$element): void
    {
        if (!array_key_exists('identifier', $element)) {
            $i = \count($element['items']) - 1;

            if ($i == -1)
                return;
            $id = $element['items'][$i];

            // Abstract declarator
            if ((\strlen($id) > 0 && !\ctype_alpha($id[0])) || CMatching::isSpecifier($id)) {
                $element['items'][] = (string)null;
                $i++;
            }
            self::setElementIdentifier($element, $i);
        }
    }

    /**
     * Hypothesis: no macro are present in the associated element
     */
    private static function elementIsParameter(array $unknownInfos): bool
    {
        if ($unknownInfos['specifiers']['type.nb'] > 0)
            return true;

        // If more than 2 specifiers then it cannot be just an identifier
        if ($unknownInfos['specifiers']['nb'] > 1)
            return true;
        // If only 1 specifier but some unknown pointers
        if ($unknownInfos['specifiers']['nb'] == 1 && $unknownInfos['unknown']['nb'] > 1)
            return true;

        return false;
    }

    private static function elementIsNotParameter(array $unknownInfos): bool
    {
        if ($unknownInfos['specifiers']['type.nb'] == 0 && $unknownInfos['pointers']['nb'] > 0)
            return true;

        return false;
    }

    private static function elementIsEmpty(array $e): bool
    {
        return empty($e['items']);
    }

    // ========================================================================

    public static function parseCPPDefine(string $text): ?array
    {
        return self::ofString($text)->_parseDefine();
    }

    private function _parseDefine(): ?array
    {
        $name = $this->nextWord();
        $params = [];

        if ($name === false)
            return null;

        $c = $this->fgetc();

        if ($c === '(') {
            $params = [];

            while (true) {
                $w = $this->nextWord();

                if (null === $w)
                    return null;

                $params[] = $w;
                $c = $this->nextChar();
                if ($c === ',')
                    continue;
                if ($c === ')')
                    break;
            }
        }
        return [
            'name' => $name,
            'params' => $params,
            'text' => \stream_get_contents($this->fnav->getStream())
        ];
    }

    private function readCPPDirectiveText(): string
    {
        $text = '';

        while (true) {
            $c = $this->fgetc();

            // TODO handle comments
            if ($c === "\n" || $c === false) {
                return $text;
            }
            $text .= $c;
        }
    }

    private function getCPPDirective(): ?CPPDirective
    {
        $state = CReaderState::start;

        while (true) {

            switch ($state) {

                case CReaderState::start:
                    $c = $this->nextChar();

                    if ($c === false)
                        return null;

                    if ($c === '#') {
                        $cursors[] = $this->fnav->getCursorPosition()->decrement();
                        $state = CReaderState::cpp_directive;
                    } else
                        return null;
                    break;

                // ======================================================

                case CReaderState::cpp_directive:
                    $directive = $this->nextWord();
                    $this->skipSpaces();

                    if ($directive === 'define')
                        $state = CReaderState::cpp_define_id;
                    else {
                        $text = $this->readCPPDirectiveText();
                        $cursors[] = $this->fnav->getCursorPosition();
                        return CElements::createSimpleCPPDirective($directive, $text, new Section(...$cursors));
                    }
                    break;

                case CReaderState::cpp_define_id:
                    //TODO: handle bad parenthesis format errors ie. "#define id(var1, tokens"
                    $id = $this->nextWord();
                    $c = $this->fgetc();
                    $this->fungetc();
                    $paramsText = ($c === '(')
                        ? $this->getDelimitedText('()')
                        : '';

                    $spaces = $this->fnav->getChars(\ctype_space(...));
                    $tokens = $this->readCPPDirectiveText();
                    $text = "$id$paramsText$spaces$tokens";
                    $cursors[] = $this->fnav->getCursorPosition();
                    return CElements::createCPPDefine($text, $id, $paramsText, $tokens, new Section(...$cursors));
            }
        }
    }

    // ========================================================================

    public function next(): ?CElement
    {
        $this->clearStates();
        $declarator_level = 0;
        $retElements = [];

        while (true) {
            list($state, $data) = $this->popState();
            $element = &$data['e'];

            if (self::debug) {
                $cursor = $this->fnav->getCursorPosition();
                $c = $this->fnav->getc();

                if ($c === false)
                    $c = 'false';
                else
                    $this->fnav->ungetc();

                echo "$state->name[ $c ]", " $cursor\n";
            }

            switch ($state) {

                case CReaderState::start:

                    // Skip useless code
                    while (true) {
                        $c = $this->nextChar();

                        if ($c === false)
                            return null;
                        if ($c === ';')
                            continue;
                        if ($c === '{') {
                            $c = $this->fungetc();
                            $this->getDelimitedText(self::C_DELIMITERS);
                            continue;
                        }
                        break;
                    }

                    if ($c === false)
                        return null;

                    if ($c === '#') {
                        $this->fungetc();
                        return $this->getCPPDirective();
                    } else {
                        $this->fungetc();
                        $element = $this->newElement();
                        $this->pushState(CReaderState::returnElement, $data);
                        $this->pushState(CReaderState::declaration_end, $data);
                        $this->pushState(CReaderState::declaration, $data);
                    }
                    break;

                case CReaderState::returnElement:

                    if (empty($retElements)) {
                        $this->clearStates();
                        break;
                    }
                    return CDeclaration::fromReaderElements($retElements[0]);

                    // ======================================================

                case CReaderState::declaration:
                    $this->pushState(CReaderState::declarator, $data);
                    $this->pushState(CReaderState::declaration_specifiers, $data);
                    break;

                // specifiers declarator
                case CReaderState::declaration_specifiers:
                    $specifiers = $this->getPossibleSpecifiers();
                    $element['infos']['specifiers.nb'] = \count($specifiers);

                    if (empty($specifiers))
                        break;

                    $element['items'] = $specifiers;
                    break;

                case CReaderState::declaration_end:

                    if ($element['type'] === CElementType::ofFunctionDefinition())
                        $retElements[] = $element;
                    else {
                        $c = $this->nextChar();

                        if ($c === ';') {
                            assert($declarator_level == 0, "Into a recursive declarator: level $declarator_level");
                            $retElements[] = $element;
                        } elseif ($c === '#') {
                            $this->fungetc();
                            return $this->getCPPDirective();
                        } else
                            $this->pushState(CReaderState::wait_end_declaration);
                    }
                    break;

                // Well state for an unrecognized declaration
                case CReaderState::wait_end_declaration:

                    while (true) {
                        $c = $this->nextChar();

                        if ($c === false)
                            return null;
                        if ($c === ';' || $c === '{') {
                            $this->fungetc();
                            $this->clearStates();
                            break;
                        }
                    }
                    break;

                // ======================================================

                // pointer direct_declarator
                case CReaderState::declarator:
                    // Pointer
                    $pointers = $this->getPointers();
                    self::elementAddItems($element, $pointers);

                    $this->pushState(CReaderState::declarator_end, $data);
                    $this->pushState(CReaderState::direct_declarator, $data);
                    break;

                case CReaderState::declarator_end:
                    self::makeElementIdentifier($element);
                    break;

                case CReaderState::direct_declarator:
                    $c = $this->nextChar();

                    // It may be a recursive declarator or a function declaration
                    if ($c === '(') {
                        $newElement = $this->newElement();
                        $this->pushState(CReaderState::subdeclarator, [
                            'e' => &$element,
                            'n' => &$newElement
                        ]);
                        $this->pushState(CReaderState::declaration, [
                            'e' => &$newElement
                        ]);
                        unset($newElement);
                    } else {
                        $this->fungetc();
                        $this->pushState(CReaderState::opt_array, $data);
                    }
                    break;

                    /**
                     * data['n']: the sub declaration
                     */
                case CReaderState::subdeclarator:
                    $c = $this->silentChar();

                    // List of parameters
                    if ($c === ',') {
                        $element['type'] = CElementType::ofFunctionDeclaration();
                        $this->pushState(CReaderState::opt_function_definition, $data);
                        $this->pushState(CReaderState::parameter_list, $data);
                    } elseif ($c === ')') {
                        $subDeclaration = $data['n'];
                        $uinfos = CDeclaration::makeUnknownInfos($subDeclaration);

                        if (self::elementIsEmpty($subDeclaration) || self::elementIsParameter($uinfos)) {
                            $element['type'] = CElementType::ofFunctionDeclaration();
                            $this->pushState(CReaderState::opt_function_definition, $data);
                            $this->pushState(CReaderState::parameter_list, $data);
                        } elseif (self::elementIsNotParameter($uinfos)) {
                            $element['items'][] = '(';
                            $declarator_level++;
                            $this->pushState(CReaderState::subdeclarator_end, $data);
                        } else {
                            // Unknown parenthesis type
                            // The following part will determine the type
                            $this->fgetc();

                            $newElement = $this->newElement();
                            $this->pushState(CReaderState::subdeclarator_after, $data + [
                                'a' => &$newElement
                            ]);
                            $this->pushState(CReaderState::opt_array_or_function, [
                                'e' => &$newElement
                            ]);
                            unset($newElement);
                        }
                    } else
                        $this->pushState(CReaderState::wait_end_declaration);
                    break;

                case CReaderState::subdeclarator_after:
                    $after = $data['a'];
                    $type2 = $after['type'];

                    $c = $this->silentChar();

                    // The subdeclarator is followed by a function|array declarator
                    if (($isfun = ($type2[CElementType::Function]))) {

                        // Merge the sub declarator with the main declarator
                        $element['items'][] = '(';
                        self::mergeElements($element, $data['n']);
                        $element['items'][] = ')';

                        // Merge the function|array declarator
                        $element['type'] = $type2;

                        if ($isfun) {
                            $element['group'] = $after['group'];

                            // Add the parameters to the current element
                            {
                                $element['items'][] = '(';
                                $poffset = \count($element['items']);

                                foreach ($after['parameters'] as $ppos)
                                    $element['items'][] = $after['items'][$ppos];

                                $element['items'][] = ')';
                                $element['parameters'] = \range($poffset, $poffset + \count($after['parameters']) - 1);
                            }

                            if (isset($after['cstatement'])) {
                                self::elementSet($element, 'cstatement', $after['cstatement']);
                            }
                        } else {
                            self::elementAddItems($element, $after['items']);
                        }
                    } elseif ($c === '{') {
                        $element['type'] = CElementType::ofFunctionDefinition();
                        self::elementSet($element, 'parameters', [
                            $data['n']
                        ]);
                        $this->pushState(CReaderState::opt_function_definition, [
                            'e' => &$element
                        ]);
                    } else {
                        $n = $data['n'];

                        $element['items'][] = '(';
                        self::mergeElements($element, $data['n']);
                        $element['items'][] = ')';

                        if ($n['type'][CElementType::Function]) {
                            self::elementSet($element, 'parameters', $n['parameters']);
                        } else {
                            // Arbitrary set the element to be a recursive declaration
                            self::elementAddItems($element, $after['items']);
                        }
                    }
                    break;

                    /** 
                     *  is a recursive declarator
                     */
                case CReaderState::subdeclarator_end:
                    $c = $this->fgetc();

                    if ($c === ')') {
                        $declarator_level--;
                        $subDeclaration = $data['n'];

                        self::mergeElements($element, $subDeclaration);
                        self::makeElementIdentifier($element);
                        $element['items'][] = ')';
                        $this->pushState(CReaderState::opt_array_or_function, $data);
                    } else {
                        $this->clearStates();
                        $this->fungetc();
                    }
                    break;

                case CReaderState::opt_array_or_function:
                    $c = $this->silentChar();

                    if ($c === '[') {
                        $this->pushState(CReaderState::opt_array, $data);
                    } elseif ($c === '(') {
                        $this->fgetc();
                        $this->pushState(CReaderState::direct_declarator_function, $data);
                    }
                    break;

                case CReaderState::opt_array:
                    $c = $this->silentChar();

                    if ($c === '[') {
                        // Arrays may repeat
                        $this->pushState(CReaderState::opt_array, $data);
                        self::makeElementIdentifier($element);
                        $arrayExpr = $this->getDelimitedText(self::C_DELIMITERS);
                        $this->elementAddItems($element, [$arrayExpr]);
                    }
                    break;

                case CReaderState::opt_cstatement:
                    $c = $this->silentChar();

                    if ($c === '{') {
                        $cstatement = $this->getDelimitedText(self::C_DELIMITERS);
                        $element['type'] = CElementType::ofFunctionDefinition();
                        $element['cstatement'] = $cstatement;
                    }
                    break;

                case CReaderState::opt_function_definition:

                    if ($declarator_level === 0)
                        $this->pushState(CReaderState::opt_cstatement, $data);
                    break;

                case CReaderState::direct_declarator_function:
                    $element['type'] = CElementType::ofFunctionDeclaration();
                    $this->pushState(CReaderState::opt_function_definition, $data);
                    $this->pushState(CReaderState::parameter, $data);
                    break;

                // ======================================================

                case CReaderState::parameter:
                    $newElement = $this->newElement();
                    $this->pushState(CReaderState::parameter_list, [
                        'e' => &$element,
                        'n' => &$newElement
                    ]);
                    $data = [
                        'e' => &$newElement
                    ];
                    $this->pushState(CReaderState::declarator, $data);
                    $this->pushState(CReaderState::declaration_specifiers, $data);
                    unset($newElement);
                    break;

                case CReaderState::parameter_list:
                    $c = $this->nextChar();
                    $element['_parameters'][] = $data['n'];

                    if ($c === ',') {
                        $this->pushState(CReaderState::parameter, $data);
                    } elseif ($c === ')') {
                        $this->makeElementIdentifier($element);
                        $params = $element['_parameters'];
                        unset($element['_parameters']);

                        if (\count($params) === 1 && self::elementIsEmpty($params[0])) {
                            $element['parameters'] = [];
                            $element['items'][] = '(';
                            $element['items'][] = ')';
                        } else {
                            $empty = \array_filter($params, self::elementIsEmpty(...));

                            if (!empty($empty)) {
                                $this->pushState(CReaderState::wait_end_declaration);
                                break;
                            }
                            $nbItems = \count($element['items']) + 1;
                            $element['parameters'] = \range($nbItems, $nbItems + \count($params) - 1);

                            $element['items'][] = '(';
                            foreach ($params as $p)
                                $element['items'][] = CDeclaration::fromReaderElements($p);
                            $element['items'][] = ')';
                        }
                    } else
                        $this->fungetc();
                    break;

                default:
                    $this->parseException("Invalid state: $state->name");
            }
        }
        return $element;
    }
}
