<?php
namespace C;

class Reader
{

    private const debug = false;

    private string $filePath;

    private ?\File\Navigator $fnav;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function __destruct()
    {
        $this->fclose();
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    // ========================================================================
    public function fgetc()
    {
        return $this->fnav->getc();
    }

    public function fungetc(int $nb = 1)
    {
        return $this->fnav->ungetc($nb);
    }

    private function fopen(): void
    {
        if (! isset($this->fnav))
            $this->fnav = \File\Navigator::fromStream(\fopen($this->filePath, 'r'));
    }

    private function fclose(): void
    {
        if (isset($this->fnav)) {
            $this->fnav->close();
            $this->fnav = null;
        }
    }

    // ========================================================================
    private function parseException(?string $msg): void
    {
        throw new \RuntimeException("$this->filePath: line($this->line_n) char($this->char_n) $msg");
    }

    private const C_DELIMITERS = '""\'\'(){}[]';

    /*
     * A '/' as been read
     */
    private function skipComment(): bool
    {
        $state = 0;
        while (true) {
            $c = $this->fgetc(); // Why ?

            if ($c === false)
                goto failure;
            // $this->parseException("Waited for comment; end of file reached");

            switch ($state) {

                // Comment ?
                case 0:
                    if ('/' === $c)
                        $state = 1;
                    elseif ('*' === $c)
                        $state = 100;
                    else
                        goto failure;
                    // $this->parseException("Waited for comment");
                    break;
                case 1:
                    if ("\n" === $c)
                        return true;
                    break;
                // Multiline comment
                case 100:
                    if ('*' === $c)
                        $state ++;
                    break;
                case 101:

                    if ('/' === $c)
                        return true;
                    elseif ('*' === $c);
                    else
                        $state --;
                    break;
            }
        }
        failure:
        $this->fungetc();
        return false;
    }

    private function skipSpaces(): void
    {
        $this->fnav->skipChars('\ctype_space');
    }

    private function skipUselessText(): void
    {
        do {
            $skipped = $this->skipSpaces();
            $c = $this->fgetc();

            if ($c === false)
                return;

            if ($c === '/') {

                if (! $this->skipComment())
                    $this->fungetc();
                else
                    $skipped = true;
            } else
                $this->fungetc();
        } while ($skipped);
    }

    private function skipSimpleDelimitedText(string $endDelimiter): void
    {
        $this->nav->getCharsUntil($endDelimiter);
    }

    private function getDelimitedText(string $delimiters = self::C_DELIMITERS): string
    {
        $buff = "";
        $skip = false;
        $endDelimiters = [];
        $endDelimiter = null;

        while (true) {
            $c = $this->fgetc();
            $buff .= $c;

            if ($c === false)
                return false;
            if ($c === '\\')
                $skip = true;
            elseif ($c == '/') {
                if ($this->skipComment())
                    $buff = \substr($buff, 0, - 1);
            } elseif ($c === $endDelimiter && ! $skip) {
                $endDelimiter = \array_pop($endDelimiters);

                if (! isset($endDelimiter))
                    return $buff;
            } else {

                if ($skip)
                    $skip = false;

                $end = \Help\FIO::isDelimitation($c, $delimiters);
                if (null !== $end) {
                    \array_push($endDelimiters, $endDelimiter);
                    $endDelimiter = $end;
                }
            }
        }
    }

    // ========================================================================
    private function nextWord(?callable $pred = null): ?string
    {
        if (! isset($pred)) {
            $pred = fn ($c) => //
            ctype_alnum($c) || $c === '_';
        }
        $this->skipUselessText();
        return $this->fnav->getChars($pred);
    }

    private function nextChar()
    {
        $this->skipUselessText();
        return $this->fgetc();
    }

    private function silentChar()
    {
        $c = $this->nextChar();
        $this->fungetc();
        return $c;
    }

    private function nextIdentifier(): ?string
    {
        $c = $this->nextChar();

        if (! ($c === '_' || \ctype_alpha($c))) {
            $this->fungetc();
            return null;
        }
        return $c . $this->nextWord();
    }

    private function getPossibleSpecifiers(): array
    {
        $ret = [];

        while (true) {
            $this->skipUselessText();
            $text = $this->nextWord();

            if ($text === null) {
                $lastWord = $text;
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

            while (($c = $this->nextChar()) === '*')
                $ret[] = '*';

            $this->fungetc();

            while (true) {
                $text = $this->nextWord();

                if (null === $text)
                    return $ret;

                $ret[] = $text;
            }
        }
    }

    // ========================================================================
    private array $states;

    private array $fileStates;

    private const zeroData = [
        'e' => []
    ];

    private function clearStates(): void
    {
        $this->states = [];
        $this->pushState(ReaderState::start, self::zeroData);
    }

    private function pushFileState(): void
    {
        $this->fileStates[] = \ftell($this->fp);
    }

    private function popFileState(): void
    {
        \fseek(SEEK_SET, \array_pop($this->fileStates));
    }

    private function pushState(ReaderState $s, $data = null, bool $fileState = false): void
    {
        $this->states[] = [
            $s,
            $data,
            $fileState
        ];

        if ($fileState)
            $this->pushFileState();
    }

    private function popState(): array
    {
        if (empty($this->states))
            return [
                ReaderState::invalid,
                null
            ];

        list ($state, $data, $fileState) = \array_pop($this->states);

        if ($fileState)
            $this->popFileState();

        return [
            $state,
            $data
        ];
    }

    private function forgetState(ReaderState $s): void
    {
        list ($state, $data, $fileState) = \array_pop($this->states);

        if ($state !== $s)
            throw new \Exception(__class__ . " waiting to forget $s->name but have $state->name");
    }

    // ========================================================================
    private function newElement(): array
    {
        return [
            'group' => DeclarationGroup::declaration,
            'type' => DeclarationType::tvariable,
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
        if (! isset($element[$name]) && isset($v))
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
            'pos' => $pos,
            'name' => &$element['items'][$pos]
        ];
    }

    private static function makeElementIdentifier(array &$element): void
    {
        if (! array_key_exists('identifier', $element)) {
            $i = \count($element['items']) - 1;

            if ($i == - 1)
                return;
            $id = $element['items'][$i];

            // Abstract declarator
            if ((\strlen($id) > 0 && ! \ctype_alpha($id[0])) || Matching::isSpecifier($id)) {
                $elements['items'][] = null;
                $i ++;
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
    public function next()
    {
        $this->fopen();
        $this->clearStates();

        $declarator_level = 0;
        $retElements = [];

        while (true) {
            list ($state, $data) = $this->popState();
            $element = &$data['e'];

            if (self::debug) {
                $cursor = $this->fnav->getCursorPosition();
                $c = $this->fnav->getc();
                $this->fnav->ungetc();
                echo "$state->name[ $c ]", " $cursor\n";
            }

            switch ($state) {

                case ReaderState::start:

                    // Skip useless code
                    while (true) {
                        $c = $this->nextChar();
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
                        return false;

                    if ($c === '#') {
                        $element = [
                            'group' => DeclarationGroup::cpp,
                            'cursor' => $this->fnav->getCursorPosition()
                        ];
                        $this->pushState(ReaderState::returnElement, $data);
                        $this->pushState(ReaderState::cpp_directive, $data);
                    } else {
                        $this->fungetc();
                        $element = $this->newElement();
                        $this->pushState(ReaderState::returnElement, $data);
                        $this->pushState(ReaderState::declaration_end, $data);
                        $this->pushState(ReaderState::declaration, $data);
                    }
                    break;

                case ReaderState::returnElement:

                    if (empty($retElements)) {
                        $this->clearStates();
                        break;
                    }
                    return $retElements[0];

                // ======================================================

                case ReaderState::cpp_directive:
                    $element['directive'] = $this->nextWord();
                    $this->skipSpaces();

                    $skipNext = false;
                    $buff = '';

                    while (true) {
                        $c = $this->fgetc();
                        $buff .= $c;

                        if (($c === "\n" && ! $skipNext) || $c === false) {
                            $element['text'] = \rtrim($buff);
                            $retElements[] = $element;
                            break;
                        } elseif ($c === '\\')
                            $skipNext = true;
                        elseif ($skipNext)
                            $skipNext = false;
                    }
                    break;

                // ======================================================

                case ReaderState::declaration:
                    $this->pushState(ReaderState::declarator, $data);
                    $this->pushState(ReaderState::declaration_specifiers, $data);
                    break;

                // specifiers declarator
                case ReaderState::declaration_specifiers:
                    $specifiers = $this->getPossibleSpecifiers();
                    $element['infos']['specifiers.nb'] = \count($specifiers);

                    if (empty($specifiers))
                        break;

                    $element['items'] = $specifiers;
                    break;

                case ReaderState::declaration_end:

                    if ($element['group'] === DeclarationGroup::definition && $element['type'] == DeclarationType::tfunction)
                        $retElements[] = $element;
                    else {
                        $c = $this->nextChar();

                        if ($c === ';') {
                            assert($declarator_level == 0, "Into a recursive declarator: level $declarator_level");
                            $retElements[] = $element;
                        } else
                            $this->pushState(ReaderState::wait_end_declaration);
                    }
                    break;

                // Well state for an unrecognized declaration
                case ReaderState::wait_end_declaration:

                    while (true) {
                        $c = $this->nextChar();

                        if ($c === ';' || $c === '{' || $c === false) {
                            $this->fungetc();
                            $this->clearStates();
                            break;
                        }
                    }
                    break;

                // ======================================================

                // pointer direct_declarator
                case ReaderState::declarator:
                    // Pointer
                    $pointers = $this->getPointers();
                    self::elementAddItems($element, $pointers);

                    $this->pushState(ReaderState::declarator_end, $data);
                    $this->pushState(ReaderState::direct_declarator, $data);
                    break;

                case ReaderState::declarator_end:
                    self::makeElementIdentifier($element);
                    break;

                case ReaderState::direct_declarator:
                    $c = $this->nextChar();

                    // It may be a recursive declarator or a function declaration
                    if ($c === '(') {
                        $newElement = $this->newElement();
                        $this->pushState(ReaderState::subdeclarator, [
                            'e' => &$element,
                            'n' => &$newElement
                        ]);
                        $this->pushState(ReaderState::declaration, [
                            'e' => &$newElement
                        ]);
                        unset($newElement);
                    } else {
                        $this->fungetc();
                    }
                    break;

                /*
                 * data['n']: the sub declaration
                 */
                case ReaderState::subdeclarator:
                    $c = $this->silentChar();

                    // List of parameters
                    if ($c === ',') {
                        $element['type'] = DeclarationType::tfunction;
                        $this->pushState(ReaderState::opt_function_definition, $data);
                        $this->pushState(ReaderState::parameter_list, $data);
                    } elseif ($c === ')') {
                        $subDeclaration = $data['n'];
                        $uinfos = Declaration::makeUnknownInfos($subDeclaration);

                        if (self::elementIsEmpty($subDeclaration) || self::elementIsParameter($uinfos)) {
                            $element['type'] = DeclarationType::tfunction;
                            $this->pushState(ReaderState::opt_function_definition, $data);
                            $this->pushState(ReaderState::parameter_list, $data);
                        } elseif (self::elementIsNotParameter($uinfos)) {
                            $element['items'][] = '(';
                            $this->pushState(ReaderState::subdeclarator_end, $data);
                        } else {
                            // Unknown parenthesis type
                            // The following part will determine the type
                            $this->fgetc();

                            $newElement = $this->newElement();
                            $this->pushState(ReaderState::subdeclarator_after, $data + [
                                'a' => &$newElement
                            ]);
                            $this->pushState(ReaderState::opt_array_or_function, [
                                'e' => &$newElement
                            ]);
                            unset($newElement);
                        }
                    } else
                        $this->pushState(ReaderState::wait_end_declaration);
                    break;

                case ReaderState::subdeclarator_after:
                    $after = $data['a'];
                    $type2 = $after['type'];

                    $c = $this->silentChar();

                    // The subdeclarator is followed by a function|array declarator
                    if (($isfun = ($type2 === DeclarationType::tfunction)) || $type2 === DeclarationType::tarray) {

                        // Merge the sub declarator with the main declarator
                        $element['items'][] = '(';
                        self::mergeElements($element, $data['n']);
                        $element['items'][] = ')';

                        // Merge the function|array declarator
                        $element['type'] = $type2;

                        if ($isfun) {
                            $element['group'] = $after['group'];
                            self::elementSet($element, 'parameters', $after['parameters']);
                            self::elementSet($element, 'cstatement', $after['cstatement']);
                        } else
                            self::elementAddItems($element, $after['items']);
                    } elseif ($c === '{') {
                        $element['group'] = DeclarationGroup::definition;
                        $element['type'] = DeclarationType::tfunction;
                        self::elementSet($element, 'parameters', [
                            $data['n']
                        ]);
                        $this->pushState(ReaderState::opt_function_definition, [
                            'e' => &$element
                        ]);
                    } else {
                        $n = $data['n'];

                        $element['items'][] = '(';
                        self::mergeElements($element, $data['n']);
                        $element['items'][] = ')';

                        if ($n['type'] == DeclarationType::tfunction) {
                            self::elementSet($element, 'parameters', $n['parameters']);
                        } else {
                            // Arbitrary set the element to be a recursive declaration
                            self::elementAddItems($element, $after['items']);
                        }
                    }
                    break;

                /*
                 * The element is a recursive declarator
                 */
                case ReaderState::subdeclarator_end:
                    $c = $this->fgetc();

                    if ($c === ')') {
                        $declarator_level --;
                        $subDeclaration = $data['n'];

                        self::mergeElements($element, $subDeclaration);
                        self::makeElementIdentifier($element);
                        $element['items'][] = ')';
                        $this->pushState(ReaderState::opt_array_or_function, $data);
                    } else {
                        $this->clearStates();
                        $this->fungetc();
                    }
                    break;

                case ReaderState::opt_array_or_function:
                    $c = $this->silentChar();

                    if ($c === '[') {
                        // Arrays may repeat
                        $this->pushState(ReaderState::opt_array, $data);
                        $this->pushState(ReaderState::direct_declarator_array, $data);
                    } elseif ($c === '(') {
                        $this->fgetc();
                        $this->pushState(ReaderState::direct_declarator_function, $data);
                    }
                    break;

                case ReaderState::opt_array:
                    $c = $this->silentChar();

                    if ($c === '[')
                        $this->pushState(ReaderState::direct_declarator_array, $data);
                    break;

                case ReaderState::opt_cstatement:
                    $c = $this->silentChar();

                    if ($c === '{') {
                        $cstatement = $this->getDelimitedText();
                        $element['group'] = DeclarationGroup::definition;
                        $element['cstatement'] = $cstatement;
                    }
                    break;

                case ReaderState::opt_function_definition:

                    if ($declarator_level === 0)
                        $this->pushState(ReaderState::opt_cstatement, $data);
                    break;

                case ReaderState::direct_declarator_array:
                    $element['type'] = DeclarationType::tarray;
                    self::makeElementIdentifier($element);
                    $content = $this->getDelimitedText(self::C_DELIMITERS);
                    $element['items'][] = $content;
                    break;

                case ReaderState::direct_declarator_function:
                    $element['type'] = DeclarationType::tfunction;
                    $this->pushState(ReaderState::opt_function_definition, $data);
                    $this->pushState(ReaderState::parameter, $data);
                    break;

                // ======================================================

                case ReaderState::parameter:
                    $newElement = $this->newElement();
                    $this->pushState(ReaderState::parameter_list, [
                        'e' => &$element,
                        'n' => &$newElement
                    ]);
                    $data = [
                        'e' => &$newElement
                    ];
                    $this->pushState(ReaderState::declarator, $data);
                    $this->pushState(ReaderState::declaration_specifiers, $data);
                    unset($newElement);
                    break;

                case ReaderState::parameter_list:
                    $c = $this->nextChar();
                    $element['_parameters'][] = $data['n'];

                    if ($c === ',') {
                        $this->pushState(ReaderState::parameter, $data);
                    } elseif ($c === ')') {
                        $params = $element['_parameters'];
                        unset($element['_parameters']);

                        if (\count($params) === 1 && self::elementIsEmpty($params[0]))
                            $element['parameters'] = [];
                        else {
                            $empty = \array_filter($params, 'self::elementIsEmpty');

                            if (! empty($empty)) {
                                $this->pushState(ReaderState::wait_end_declaration);
                                break;
                            }
                            $element['parameters'] = $params;
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
