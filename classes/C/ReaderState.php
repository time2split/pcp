<?php
namespace C;

enum ReaderState{
    case invalid;
    case wait_end_declaration;
    case start;
    
    case cpp_directive;
    
    case declaration;
    case declaration_specifiers;
    case declaration_end;
    
    case subdeclarator;
    case subdeclarator_after;
    case subdeclarator_end;
    
    case declarator;
    case declarator_end;
    
    case direct_declarator;
    case direct_declarator_check_array;
    case direct_declarator_array;
    case direct_declarator_function;
    
    case opt_cstatement;
    case opt_array_or_function;
    case opt_array;
    case opt_function_definition;
    
    case parameter;
    case parameter_list;
    
    case returnElement;
}