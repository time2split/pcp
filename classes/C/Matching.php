<?php
namespace C;

abstract class Matching
{

    public const storage_class_specifiers = [
        'auto',
        'typedef',
        'static',
        'extern',
        'register'
    ];

    public const function_specifiers = [
        'inline'
    ];

    public const type_qualifiers = [
        'const',
        'volatile',
        'restrict'
    ];

    public const type_specifiers = [
        'void',
        'char',
        'short',
        'int',
        'long',
        'float',
        'double',
        'signed',
        'unsigned',
        '_Bool',
        '_Complex',
        '_Imaginary'
    ];

    public const all_specifiers = [
        ...self::storage_class_specifiers,
        ...self::function_specifiers,
        ...self::type_qualifiers,
        ...self::type_specifiers
    ];

    public static function isSpecifier(string $name): bool
    {
        return \in_array($name, self::all_specifiers);
    }

    public static function isTypeQualifier(string $name): bool
    {
        return \in_array($name, self::type_qualifiers);
    }
    
    public static function isTypeSpecifier(string $name): bool
    {
        return \in_array($name, self::type_specifiers);
    }
}