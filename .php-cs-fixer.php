<?php

declare(strict_types=1);

/*
 * PHP Code Style Fixer (config created for version 3.4.0 (Si!)).
 *
 * Use one of the following console commands to just see the
 * changes that will be made.
 * - `php-cs-fixer fix --config='.php-cs-fixer.php' --dry-run`
 * - `php '.php-cs-fixer.php'`
 * - `php7.1 '.php-cs-fixer.php'`
 * - `php7.2 '.php-cs-fixer.php'`
 * - `php7.3 '.php-cs-fixer.php'`
 * - `php7.4 '.php-cs-fixer.php'`
 * - `php8.0 '.php-cs-fixer.php'`
 *
 * Use one of the following console commands to fix PHP code:
 * - `php-cs-fixer fix --config='.php-cs-fixer.php'
 * - `php '.php-cs-fixer.php' --force`
 * - `php7.1 '.php-cs-fixer.php' --force`
 * - `php7.2 '.php-cs-fixer.php' --force`
 * - `php7.3 '.php-cs-fixer.php' --force`
 * - `php7.4 '.php-cs-fixer.php' --force`
 * - `php8.0 '.php-cs-fixer.php' --force`
 *
 * @see https://cs.symfony.com/
 */
$rules = [
    /*
     * Each line of multi-line DocComments must have an asterisk [PSR-5]
     * and must be aligned with the first one.
     */
    'align_multiline_comment' => true,

    // Each element of an array must be indented exactly once.
    'array_indentation' => true,

    /*
     * Converts simple usages of `array_push($x, $y);` to `$x[] = $y;`.
     *
     * Risky!
     * Risky when the function `array_push` is overridden.
     */
    'array_push' => true,

    // PHP arrays should be declared using the configured syntax.
    'array_syntax' => [
        'syntax' => 'short',
    ],

    // Use the null coalescing assignment operator `??=` where possible.
    'assign_null_coalescing_to_coalesce_equal' => true,

    /*
     * Converts backtick operators to `shell_exec` calls.
     *
     * Conversion is done only when it is non risky, so when special
     * chars like single-quotes, double-quotes and backticks are not
     * used inside the command.
     */
    'backtick_to_shell_exec' => true,

    // Binary operators should be surrounded by space as configured.
    'binary_operator_spaces' => true,

    // There MUST be one blank line after the namespace declaration.
    'blank_line_after_namespace' => true,

    /*
     * Ensure there is no code on the same line as the PHP open tag and
     * it is followed by a blank line.
     */
    'blank_line_after_opening_tag' => true,

    // An empty line feed must precede any configured statement.
    'blank_line_before_statement' => [
        'statements' => [
            'return',
        ],
    ],

    /*
     * The body of each structure MUST be enclosed by braces. Braces
     * should be properly placed. Body of braces should be properly
     * indented.
     */
    'braces' => [
        'allow_single_line_anonymous_class_with_empty_body' => true,
        'allow_single_line_closure' => true,
    ],

    // A single space or none should be between cast and variable.
    'cast_spaces' => true,

    /*
     * Class, trait and interface elements must be separated with one or
     * none blank line.
     */
    'class_attributes_separation' => true,

    /*
     * Whitespace around the keywords of a class, trait or interfaces
     * definition should be one space.
     */
    'class_definition' => true,

    // Namespace must not contain spacing, comments or PHPDoc.
    'clean_namespace' => true,

    // Using `isset($var) &&` multiple times should be done in one call.
    'combine_consecutive_issets' => true,

    // Calling `unset` on multiple items should be done in one call.
    'combine_consecutive_unsets' => true,

    /*
     * Replace multiple nested calls of `dirname` by only one call with
     * second `$level` parameter. Requires PHP >= 7.0.
     *
     * Risky!
     * Risky when the function `dirname` is overridden.
     */
    'combine_nested_dirname' => true,

    /*
     * Comments with annotation should be docblock when used on
     * structural elements.
     *
     * Risky!
     * Risky as new docblocks might mean more, e.g. a Doctrine entity
     * might have a new column in database.
     */
    'comment_to_phpdoc' => [
        'ignored_tags' => [
            'noinspection',
        ],
    ],

    /*
     * Remove extra spaces in a nullable typehint.
     *
     * Rule is applied only in a PHP 7.1+ environment.
     */
    'compact_nullable_typehint' => true,

    // Concatenation should be spaced according configuration.
    'concat_space' => [
        'spacing' => 'one',
    ],

    /*
     * The PHP constants `true`, `false`, and `null` MUST be written
     * using the correct casing.
     */
    'constant_case' => true,

    /*
     * Control structure continuation keyword must be on the configured
     * line.
     */
    'control_structure_continuation_position' => true,

    /*
     * Class `DateTimeImmutable` should be used instead of `DateTime`.
     *
     * Risky!
     * Risky when the code relies on modifying `DateTime` objects or if
     * any of the `date_create*` functions are overridden.
     */
    'date_time_immutable' => true,

    /*
     * Equal sign in declare statement should be surrounded by spaces or
     * not following configuration.
     */
    'declare_equal_normalize' => true,

    // There must not be spaces around `declare` statement parentheses.
    'declare_parentheses' => true,

    /*
     * Force strict types declaration in all files. Requires PHP >= 7.0.
     *
     * Risky!
     * Forcing strict types will stop non strict code from working.
     */
    'declare_strict_types' => true,

    /*
     * Replaces `dirname(__FILE__)` expression with equivalent `__DIR__`
     * constant.
     *
     * Risky!
     * Risky when the function `dirname` is overridden.
     */
    'dir_constant' => true,

    /*
     * Doctrine annotations must use configured operator for assignment
     * in arrays.
     */
    'doctrine_annotation_array_assignment' => true,

    /*
     * Doctrine annotations without arguments must use the configured
     * syntax.
     */
    'doctrine_annotation_braces' => true,

    // Doctrine annotations must be indented with four spaces.
    'doctrine_annotation_indentation' => true,

    /*
     * Fixes spaces in Doctrine annotations.
     *
     * There must not be any space around parentheses; commas must be
     * preceded by no space and followed by one space; there must be no
     * space around named arguments assignment operator; there must be
     * one space around array assignment operator.
     */
    'doctrine_annotation_spaces' => true,

    /*
     * Replaces short-echo `<?=` with long format `<?php echo`/`<?php
     * print` syntax, or vice-versa.
     */
    'echo_tag_syntax' => [
        'format' => 'short',
        'long_function' => 'echo',
        'shorten_simple_statements_only' => true,
    ],

    /*
     * The keyword `elseif` should be used instead of `else if` so that
     * all control keywords look like single words.
     */
    'elseif' => true,

    // Empty loop-body must be in configured style.
    'empty_loop_body' => [
        'style' => 'braces',
    ],

    // Empty loop-condition must be in configured style.
    'empty_loop_condition' => true,

    // PHP code MUST use only UTF-8 without BOM (remove BOM).
    'encoding' => true,

    /*
     * Replace deprecated `ereg` regular expression functions with
     * `preg`.
     *
     * Risky!
     * Risky if the `ereg` function is overridden.
     */
    'ereg_to_preg' => true,

    /*
     * Error control operator should be added to deprecation notices
     * and/or removed from other cases.
     *
     * Risky!
     * Risky because adding/removing `@` might cause changes to code
     * behaviour or if `trigger_error` function is overridden.
     */
    'error_suppression' => [
        'mute_deprecation_error' => true,
        'noise_remaining_usages' => true,
        'noise_remaining_usages_exclude' => [
            'fclose',
            'fopen',
            'gzinflate',
            'iconv',
            'mime_content_type',
            'rename',
            'rmdir',
            'unlink',
        ],
    ],

    /*
     * Escape implicit backslashes in strings and heredocs to ease the
     * understanding of which are special chars interpreted by PHP and
     * which not.
     *
     * In PHP double-quoted strings and heredocs some chars like `n`,
     * `$` or `u` have special meanings if preceded by a backslash (and
     * some are special only if followed by other special chars), while
     * a backslash preceding other chars are interpreted like a plain
     * backslash. The precise list of those special chars is hard to
     * remember and to identify quickly: this fixer escapes backslashes
     * that do not start a special interpretation with the char after
     * them.
     * It is possible to fix also single-quoted strings: in this case
     * there is no special chars apart from single-quote and backslash
     * itself, so the fixer simply ensure that all backslashes are
     * escaped. Both single and double backslashes are allowed in
     * single-quoted strings, so the purpose in this context is mainly
     * to have a uniformed way to have them written all over the
     * codebase.
     */
    'escape_implicit_backslashes' => true,

    /*
     * Add curly braces to indirect variables to make them clear to
     * understand. Requires PHP >= 7.0.
     */
    'explicit_indirect_variable' => true,

    /*
     * Converts implicit variables into explicit ones in double-quoted
     * strings or heredoc syntax.
     *
     * The reasoning behind this rule is the following:
     * - When there are two valid ways of doing the same thing, using
     * both is confusing, there should be a coding standard to follow
     * - PHP manual marks `"$var"` syntax as implicit and `"${var}"`
     * syntax as explicit: explicit code should always be preferred
     * - Explicit syntax allows word concatenation inside strings, e.g.
     * `"${var}IsAVar"`, implicit doesn't
     * - Explicit syntax is easier to detect for IDE/editors and
     * therefore has colors/highlight with higher contrast, which is
     * easier to read
     * Backtick operator is skipped because it is harder to handle; you
     * can use `backtick_to_shell_exec` fixer to normalize backticks to
     * strings
     */
    'explicit_string_variable' => true,

    /*
     * All classes must be final, except abstract ones and Doctrine
     * entities.
     *
     * No exception and no configuration are intentional. Beside
     * Doctrine entities and of course abstract classes, there is no
     * single reason not to declare all classes final. If you want to
     * subclass a class, mark the parent class as abstract and create
     * two child classes, one empty if necessary: you'll gain much more
     * fine grained type-hinting. If you need to mock a standalone
     * class, create an interface, or maybe it's a value-object that
     * shouldn't be mocked at all. If you need to extend a standalone
     * class, create an interface and use the Composite pattern. If you
     * aren't ready yet for serious OOP, go with
     * FinalInternalClassFixer, it's fine.
     *
     * Risky!
     * Risky when subclassing non-abstract classes.
     */
    'final_class' => false,

    /*
     * Internal classes should be `final`.
     *
     * Risky!
     * Changing classes to `final` might cause code execution to break.
     */
    'final_internal_class' => false,

    /*
     * All `public` methods of `abstract` classes should be `final`.
     *
     * Enforce API encapsulation in an inheritance architecture. If you
     * want to override a method, use the Template method pattern.
     *
     * Risky!
     * Risky when overriding `public` methods of `abstract` classes.
     */
    'final_public_method_for_abstract_class' => false,

    /*
     * Order the flags in `fopen` calls, `b` and `t` must be last.
     *
     * Risky!
     * Risky when the function `fopen` is overridden.
     */
    'fopen_flag_order' => true,

    /*
     * The flags in `fopen` calls must omit `t`, and `b` must be omitted
     * or included consistently.
     *
     * Risky!
     * Risky when the function `fopen` is overridden.
     */
    'fopen_flags' => [
        'b_mode' => true,
    ],

    /*
     * PHP code must use the long `<?php` tags or short-echo `<?=` tags
     * and not other tag variations.
     */
    'full_opening_tag' => true,

    /*
     * Transforms imported FQCN parameters and return types in function
     * arguments to short version.
     */
    'fully_qualified_strict_types' => true,

    // Spaces should be properly placed in a function declaration.
    'function_declaration' => true,

    /*
     * Replace core functions calls returning constants with the
     * constants.
     *
     * Risky!
     * Risky when any of the configured functions to replace are
     * overridden.
     */
    'function_to_constant' => [
        'functions' => [
            'get_called_class',
            'get_class',
            'get_class_this',
            'php_sapi_name',
            'phpversion',
            'pi',
        ],
    ],

    // Ensure single space between function's argument and its typehint.
    'function_typehint_space' => true,

    // Configured annotations should be omitted from PHPDoc.
    'general_phpdoc_annotation_remove' => [
        'annotations' => [
            'author',
            'license',
        ],
    ],

    // Renames PHPDoc tags.
    'general_phpdoc_tag_rename' => [
        'replacements' => [
            'inheritDocs' => 'inheritDoc',
        ],
    ],

    // Imports or fully qualifies global classes/functions/constants.
    'global_namespace_import' => [
        'import_constants' => false,
        'import_functions' => false,
        'import_classes' => false,
    ],

    // There MUST be group use for the same namespaces.
    'group_import' => false,

    // Add, replace or remove header comment.
    'header_comment' => [
        'header' => 'This file is part of the nelexa/zip package.'."\n"
            .'(c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>'."\n"
            .'For the full copyright and license information, please view the LICENSE'."\n"
            .'file that was distributed with this source code.',
        'comment_type' => 'comment',
        'location' => 'after_declare_strict',
        'separate' => 'both',
    ],

    /*
     * Heredoc/nowdoc content must be properly indented. Requires PHP >=
     * 7.3.
     */
    'heredoc_indentation' => true,

    // Convert `heredoc` to `nowdoc` where possible.
    'heredoc_to_nowdoc' => true,

    /*
     * Function `implode` must be called with 2 arguments in the
     * documented order.
     *
     * Risky!
     * Risky when the function `implode` is overridden.
     */
    'implode_call' => true,

    /*
     * Include/Require and file path should be divided with a single
     * space. File path should not be placed under brackets.
     */
    'include' => true,

    /*
     * Pre- or post-increment and decrement operators should be used if
     * possible.
     */
    'increment_style' => false,

    // Code MUST use configured indentation type.
    'indentation_type' => true,

    // Integer literals must be in correct case.
    'integer_literal_case' => true,

    /*
     * Replaces `is_null($var)` expression with `null === $var`.
     *
     * Risky!
     * Risky when the function `is_null` is overridden.
     */
    'is_null' => true,

    // Lambda must not import variables it doesn't use.
    'lambda_not_used_import' => true,

    // All PHP files must use same line ending.
    'line_ending' => true,

    // Ensure there is no code on the same line as the PHP open tag.
    'linebreak_after_opening_tag' => true,

    /*
     * List (`array` destructuring) assignment should be declared using
     * the configured syntax. Requires PHP >= 7.1.
     */
    'list_syntax' => [
        'syntax' => 'short',
    ],

    /*
     * Use `&&` and `||` logical operators instead of `and` and `or`.
     *
     * Risky!
     * Risky, because you must double-check if using and/or with lower
     * precedence was intentional.
     */
    'logical_operators' => true,

    // Cast should be written in lower case.
    'lowercase_cast' => true,

    // PHP keywords MUST be in lower case.
    'lowercase_keywords' => true,

    /*
     * Class static references `self`, `static` and `parent` MUST be in
     * lower case.
     */
    'lowercase_static_reference' => true,

    // Magic constants should be referred to using the correct casing.
    'magic_constant_casing' => true,

    /*
     * Magic method definitions and calls must be using the correct
     * casing.
     */
    'magic_method_casing' => true,

    /*
     * Replace non multibyte-safe functions with corresponding mb
     * function.
     *
     * Risky!
     * Risky when any of the functions are overridden, or when relying
     * on the string byte size rather than its length in characters.
     */
    'mb_str_functions' => false,

    /*
     * In method arguments and method call, there MUST NOT be a space
     * before each comma and there MUST be one space after each comma.
     * Argument lists MAY be split across multiple lines, where each
     * subsequent line is indented once. When doing so, the first item
     * in the list MUST be on the next line, and there MUST be only one
     * argument per line.
     */
    'method_argument_space' => [
        'on_multiline' => 'ensure_fully_multiline',
        'after_heredoc' => true,
    ],

    /*
     * Method chaining MUST be properly indented. Method chaining with
     * different levels of indentation is not supported.
     */
    'method_chaining_indentation' => true,

    /*
     * Replace `strpos()` calls with `str_starts_with()` or
     * `str_contains()` if possible.
     *
     * Risky!
     * Risky if `strpos`, `str_starts_with` or `str_contains` functions
     * are overridden.
     */
    'modernize_strpos' => false,

    /*
     * Replaces `intval`, `floatval`, `doubleval`, `strval` and
     * `boolval` function calls with according type casting operator.
     *
     * Risky!
     * Risky if any of the functions `intval`, `floatval`, `doubleval`,
     * `strval` or `boolval` are overridden.
     */
    'modernize_types_casting' => true,

    /*
     * DocBlocks must start with two asterisks, multiline comments must
     * start with a single asterisk, after the opening slash. Both must
     * end with a single asterisk before the closing slash.
     */
    'multiline_comment_opening_closing' => true,

    /*
     * Forbid multi-line whitespace before the closing semicolon or move
     * the semicolon to the new line for chained calls.
     */
    'multiline_whitespace_before_semicolons' => [
        'strategy' => 'new_line_for_chained_calls',
    ],

    /*
     * Add leading `\` before constant invocation of internal constant
     * to speed up resolving. Constant name match is case-sensitive,
     * except for `null`, `false` and `true`.
     *
     * Risky!
     * Risky when any of the constants are namespaced or overridden.
     */
    'native_constant_invocation' => true,

    /*
     * Function defined by PHP should be called using the correct
     * casing.
     */
    'native_function_casing' => true,

    /*
     * Add leading `\` before function invocation to speed up resolving.
     *
     * Risky!
     * Risky when any of the functions are overridden.
     */
    'native_function_invocation' => [
        'include' => [
            '@compiler_optimized',
        ],
        'scope' => 'namespaced',
        'strict' => true,
    ],

    // Native type hints for functions should use the correct case.
    'native_function_type_declaration_casing' => true,

    /*
     * All instances created with new keyword must be followed by
     * braces.
     */
    'new_with_braces' => true,

    /*
     * Master functions shall be used instead of aliases.
     *
     * Risky!
     * Risky when any of the alias functions are overridden.
     */
    'no_alias_functions' => [
        'sets' => [
            '@all',
        ],
    ],

    // Master language constructs shall be used instead of aliases.
    'no_alias_language_construct_call' => true,

    // Replace control structure alternative syntax to use braces.
    'no_alternative_syntax' => true,

    // There should not be a binary flag before strings.
    'no_binary_string' => true,

    // There should be no empty lines after class opening brace.
    'no_blank_lines_after_class_opening' => true,

    /*
     * There should not be blank lines between docblock and the
     * documented element.
     */
    'no_blank_lines_after_phpdoc' => true,

    // There should be no blank lines before a namespace declaration.
    'no_blank_lines_before_namespace' => false,

    /*
     * There must be a comment when fall-through is intentional in a
     * non-empty case body.
     *
     * Adds a "no break" comment before fall-through cases, and removes
     * it if there is no fall-through.
     */
    'no_break_comment' => [
        'comment_text' => 'no break',
    ],

    /*
     * The closing `?>` tag MUST be omitted from files containing only
     * PHP.
     */
    'no_closing_tag' => true,

    // There should not be any empty comments.
    'no_empty_comment' => true,

    // There should not be empty PHPDoc blocks.
    'no_empty_phpdoc' => true,

    // Remove useless (semicolon) statements.
    'no_empty_statement' => true,

    /*
     * Removes extra blank lines and/or blank lines following
     * configuration.
     */
    'no_extra_blank_lines' => [
        'tokens' => [
            'case',
            'continue',
            'curly_brace_block',
            'default',
            'extra',
            'parenthesis_brace_block',
            'square_brace_block',
            'switch',
            'throw',
            'use',
        ],
    ],

    /*
     * Replace accidental usage of homoglyphs (non ascii characters) in
     * names.
     *
     * Risky!
     * Renames classes and cannot rename the files. You might have
     * string references to renamed code (`$$name`).
     */
    'no_homoglyph_names' => true,

    // Remove leading slashes in `use` clauses.
    'no_leading_import_slash' => true,

    /*
     * The namespace declaration line shouldn't contain leading
     * whitespace.
     */
    'no_leading_namespace_whitespace' => true,

    // Either language construct `print` or `echo` should be used.
    'no_mixed_echo_print' => true,

    // Operator `=>` should not be surrounded by multi-line whitespaces.
    'no_multiline_whitespace_around_double_arrow' => true,

    /*
     * Properties MUST not be explicitly initialized with `null` except
     * when they have a type declaration (PHP 7.4).
     */
    'no_null_property_initialization' => true,

    /*
     * Convert PHP4-style constructors to `__construct`.
     *
     * Risky!
     * Risky when old style constructor being fixed is overridden or
     * overrides parent one.
     */
    'no_php4_constructor' => true,

    /*
     * Short cast `bool` using double exclamation mark should not be
     * used.
     */
    'no_short_bool_cast' => true,

    // Single-line whitespace before closing semicolon are prohibited.
    'no_singleline_whitespace_before_semicolons' => true,

    /*
     * There must be no space around double colons (also called Scope
     * Resolution Operator or Paamayim Nekudotayim).
     */
    'no_space_around_double_colon' => true,

    /*
     * When making a method or function call, there MUST NOT be a space
     * between the method or function name and the opening parenthesis.
     */
    'no_spaces_after_function_name' => true,

    // There MUST NOT be spaces around offset braces.
    'no_spaces_around_offset' => true,

    /*
     * There MUST NOT be a space after the opening parenthesis. There
     * MUST NOT be a space before the closing parenthesis.
     */
    'no_spaces_inside_parenthesis' => true,

    // Replaces superfluous `elseif` with `if`.
    'no_superfluous_elseif' => true,

    /*
     * Removes `@param`, `@return` and `@var` tags that don't provide
     * any useful information.
     */
    'no_superfluous_phpdoc_tags' => [
        'allow_mixed' => true,
        'allow_unused_params' => true,
    ],

    // Remove trailing commas in list function calls.
    'no_trailing_comma_in_list_call' => true,

    // PHP single-line arrays should not have trailing comma.
    'no_trailing_comma_in_singleline_array' => true,

    // Remove trailing whitespace at the end of non-blank lines.
    'no_trailing_whitespace' => true,

    // There MUST be no trailing spaces inside comment or PHPDoc.
    'no_trailing_whitespace_in_comment' => true,

    /*
     * There must be no trailing whitespace in strings.
     *
     * Risky!
     * Changing the whitespaces in strings might affect string
     * comparisons and outputs.
     */
    'no_trailing_whitespace_in_string' => true,

    // Removes unneeded parentheses around control statements.
    'no_unneeded_control_parentheses' => [
        'statements' => [
            'break',
            'clone',
            'continue',
            'echo_print',
            'return',
            'switch_case',
            'yield',
            'yield_from',
        ],
    ],

    /*
     * Removes unneeded curly braces that are superfluous and aren't
     * part of a control structure's body.
     */
    'no_unneeded_curly_braces' => true,

    /*
     * A `final` class must not have `final` methods and `private`
     * methods must not be `final`.
     *
     * Risky!
     * Risky when child class overrides a `private` method.
     */
    'no_unneeded_final_method' => true,

    /*
     * In function arguments there must not be arguments with default
     * values before non-default ones.
     *
     * Risky!
     * Modifies the signature of functions; therefore risky when using
     * systems (such as some Symfony components) that rely on those (for
     * example through reflection).
     */
    'no_unreachable_default_argument_value' => true,

    // Variables must be set `null` instead of using `(unset)` casting.
    'no_unset_cast' => true,

    /*
     * Properties should be set to `null` instead of using `unset`.
     *
     * Risky!
     * Risky when relying on attributes to be removed using `unset`
     * rather than be set to `null`. Changing variables to `null`
     * instead of unsetting means these still show up when looping over
     * class variables and reference properties remain unbroken. With
     * PHP 7.4, this rule might introduce `null` assignments to
     * properties whose type declaration does not allow it.
     */
    'no_unset_on_property' => false,

    // Unused `use` statements must be removed.
    'no_unused_imports' => true,

    // There should not be useless `else` cases.
    'no_useless_else' => true,

    /*
     * There should not be an empty `return` statement at the end of a
     * function.
     */
    'no_useless_return' => true,

    /*
     * There must be no `sprintf` calls with only the first argument.
     *
     * Risky!
     * Risky when if the `sprintf` function is overridden.
     */
    'no_useless_sprintf' => true,

    /*
     * In array declaration, there MUST NOT be a whitespace before each
     * comma.
     */
    'no_whitespace_before_comma_in_array' => [
        'after_heredoc' => true,
    ],

    // Remove trailing whitespace at the end of blank lines.
    'no_whitespace_in_blank_line' => true,

    /*
     * Remove Zero-width space (ZWSP), Non-breaking space (NBSP) and
     * other invisible unicode symbols.
     *
     * Risky!
     * Risky when strings contain intended invisible characters.
     */
    'non_printable_character' => true,

    // Array index should always be written by using square braces.
    'normalize_index_brace' => true,

    /*
     * Logical NOT operators (`!`) should have leading and trailing
     * whitespaces.
     */
    'not_operator_with_space' => false,

    // Logical NOT operators (`!`) should have one trailing whitespace.
    'not_operator_with_successor_space' => false,

    /*
     * Adds or removes `?` before type declarations for parameters with
     * a default `null` value.
     *
     * Rule is applied only in a PHP 7.1+ environment.
     */
    'nullable_type_declaration_for_default_null_value' => true,

    /*
     * There should not be space before or after object operators `->`
     * and `?->`.
     */
    'object_operator_without_whitespace' => true,

    // Literal octal must be in `0o` notation.
    'octal_notation' => true,

    /*
     * Operators - when multiline - must always be at the beginning or
     * at the end of the line.
     */
    'operator_linebreak' => true,

    // Orders the elements of classes/interfaces/traits.
    'ordered_class_elements' => [
        'order' => [
            'use_trait',
        ],
    ],

    // Ordering `use` statements.
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',
        'imports_order' => [
            'class',
            'const',
            'function',
        ],
    ],

    /*
     * Orders the interfaces in an `implements` or `interface extends`
     * clause.
     *
     * Risky!
     * Risky for `implements` when specifying both an interface and its
     * parent interface, because PHP doesn't break on `parent, child`
     * but does on `child, parent`.
     */
    'ordered_interfaces' => false,

    /*
     * Trait `use` statements must be sorted alphabetically.
     *
     * Risky!
     * Risky when depending on order of the imports.
     */
    'ordered_traits' => true,

    /*
     * PHPUnit assertion method calls like `->assertSame(true, $foo)`
     * should be written with dedicated method like
     * `->assertTrue($foo)`.
     *
     * Risky!
     * Fixer could be risky if one is overriding PHPUnit's native
     * methods.
     */
    'php_unit_construct' => true,

    /*
     * PHPUnit assertions like `assertInternalType`, `assertFileExists`,
     * should be used over `assertTrue`.
     *
     * Risky!
     * Fixer could be risky if one is overriding PHPUnit's native
     * methods.
     */
    'php_unit_dedicate_assert' => [
        'target' => 'newest',
    ],

    /*
     * PHPUnit assertions like `assertIsArray` should be used over
     * `assertInternalType`.
     *
     * Risky!
     * Risky when PHPUnit methods are overridden or when project has
     * PHPUnit incompatibilities.
     */
    'php_unit_dedicate_assert_internal_type' => true,

    /*
     * Usages of `->setExpectedException*` methods MUST be replaced by
     * `->expectException*` methods.
     *
     * Risky!
     * Risky when PHPUnit classes are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_expectation' => true,

    // PHPUnit annotations should be a FQCNs including a root namespace.
    'php_unit_fqcn_annotation' => true,

    // All PHPUnit test classes should be marked as internal.
    'php_unit_internal_class' => true,

    /*
     * Enforce camel (or snake) case for PHPUnit test methods, following
     * configuration.
     */
    'php_unit_method_casing' => true,

    /*
     * Usages of `->getMock` and
     * `->getMockWithoutInvokingTheOriginalConstructor` methods MUST be
     * replaced by `->createMock` or `->createPartialMock` methods.
     *
     * Risky!
     * Risky when PHPUnit classes are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_mock' => true,

    /*
     * Usage of PHPUnit's mock e.g. `->will($this->returnValue(..))`
     * must be replaced by its shorter equivalent such as
     * `->willReturn(...)`.
     *
     * Risky!
     * Risky when PHPUnit classes are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_mock_short_will_return' => true,

    /*
     * PHPUnit classes MUST be used in namespaced version, e.g.
     * `\PHPUnit\Framework\TestCase` instead of
     * `\PHPUnit_Framework_TestCase`.
     *
     * PHPUnit v6 has finally fully switched to namespaces.
     * You could start preparing the upgrade by switching from
     * non-namespaced TestCase to namespaced one.
     * Forward compatibility layer (`\PHPUnit\Framework\TestCase` class)
     * was backported to PHPUnit v4.8.35 and PHPUnit v5.4.0.
     * Extended forward compatibility layer (`PHPUnit\Framework\Assert`,
     * `PHPUnit\Framework\BaseTestListener`,
     * `PHPUnit\Framework\TestListener` classes) was introduced in
     * v5.7.0.
     *
     * Risky!
     * Risky when PHPUnit classes are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_namespaced' => true,

    /*
     * Usages of `@expectedException*` annotations MUST be replaced by
     * `->setExpectedException*` methods.
     *
     * Risky!
     * Risky when PHPUnit classes are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_no_expectation_annotation' => true,

    /*
     * Changes the visibility of the `setUp()` and `tearDown()`
     * functions of PHPUnit to `protected`, to match the PHPUnit
     * TestCase.
     *
     * Risky!
     * This fixer may change functions named `setUp()` or `tearDown()`
     * outside of PHPUnit tests, when a class is wrongly seen as a
     * PHPUnit test.
     */
    'php_unit_set_up_tear_down_visibility' => true,

    /*
     * All PHPUnit test cases should have `@small`, `@medium` or
     * `@large` annotation to enable run time limits.
     *
     * The special groups [small, medium, large] provides a way to
     * identify tests that are taking long to be executed.
     */
    'php_unit_size_class' => true,

    /*
     * PHPUnit methods like `assertSame` should be used instead of
     * `assertEquals`.
     *
     * Risky!
     * Risky when any of the functions are overridden or when testing
     * object equality.
     */
    'php_unit_strict' => false,

    /*
     * Adds or removes @test annotations from tests, following
     * configuration.
     *
     * Risky!
     * This fixer may change the name of your tests, and could cause
     * incompatibility with abstract classes or interfaces.
     */
    'php_unit_test_annotation' => true,

    /*
     * Calls to `PHPUnit\Framework\TestCase` static methods must all be
     * of the same type, either `$this->`, `self::` or `static::`.
     *
     * Risky!
     * Risky when PHPUnit methods are overridden or not accessible, or
     * when project has PHPUnit incompatibilities.
     */
    'php_unit_test_case_static_method_calls' => true,

    /*
     * Adds a default `@coversNothing` annotation to PHPUnit test
     * classes that have no `@covers*` annotation.
     */
    'php_unit_test_class_requires_covers' => false,

    // PHPDoc should contain `@param` for all params.
    'phpdoc_add_missing_param_annotation' => [
        'only_untyped' => false,
    ],

    /*
     * All items of the given phpdoc tags must be either left-aligned or
     * (by default) aligned vertically.
     */
    'phpdoc_align' => [
        'tags' => [
            'return',
            'throws',
            'type',
            'var',
            'property',
            'method',
            'param',
        ],
        'align' => 'vertical',
    ],

    // PHPDoc annotation descriptions should not be a sentence.
    'phpdoc_annotation_without_dot' => true,

    /*
     * Docblocks should have the same indentation as the documented
     * subject.
     */
    'phpdoc_indent' => true,

    // Fixes PHPDoc inline tags.
    'phpdoc_inline_tag_normalizer' => true,

    /*
     * Changes doc blocks from single to multi line, or reversed. Works
     * for class constants, properties and methods only.
     */
    'phpdoc_line_span' => [
        'const' => 'single',
        'property' => 'single',
        'method' => 'multi',
    ],

    // `@access` annotations should be omitted from PHPDoc.
    'phpdoc_no_access' => true,

    // No alias PHPDoc tags should be used.
    'phpdoc_no_alias_tag' => true,

    /*
     * `@return void` and `@return null` annotations should be omitted
     * from PHPDoc.
     */
    'phpdoc_no_empty_return' => true,

    /*
     * `@package` and `@subpackage` annotations should be omitted from
     * PHPDoc.
     */
    'phpdoc_no_package' => true,

    // Classy that does not inherit must not have `@inheritdoc` tags.
    'phpdoc_no_useless_inheritdoc' => true,

    /*
     * Annotations in PHPDoc should be ordered so that `@param`
     * annotations come first, then `@throws` annotations, then
     * `@return` annotations.
     */
    'phpdoc_order' => true,

    // Order phpdoc tags by value.
    'phpdoc_order_by_value' => true,

    /*
     * The type of `@return` annotations of methods returning a
     * reference to itself must the configured one.
     */
    'phpdoc_return_self_reference' => true,

    /*
     * Scalar types should always be written in the same form. `int` not
     * `integer`, `bool` not `boolean`, `float` not `real` or `double`.
     */
    'phpdoc_scalar' => true,

    /*
     * Annotations in PHPDoc should be grouped together so that
     * annotations of the same type immediately follow each other, and
     * annotations of a different type are separated by a single blank
     * line.
     */
    'phpdoc_separation' => true,

    // Single line `@var` PHPDoc should have proper spacing.
    'phpdoc_single_line_var_spacing' => true,

    /*
     * PHPDoc summary should end in either a full stop, exclamation
     * mark, or question mark.
     */
    'phpdoc_summary' => true,

    // Fixes casing of PHPDoc tags.
    'phpdoc_tag_casing' => true,

    // Forces PHPDoc tags to be either regular annotations or inline.
    'phpdoc_tag_type' => [
        'tags' => [
            'inheritDoc' => 'inline',
        ],
    ],

    // Docblocks should only be used on structural elements.
    'phpdoc_to_comment' => false,

    /*
     * EXPERIMENTAL: Takes `@param` annotations of non-mixed types and
     * adjusts accordingly the function signature. Requires PHP >= 7.0.
     *
     * Risky!
     * This rule is EXPERIMENTAL and [1] is not covered with backward
     * compatibility promise. [2] `@param` annotation is mandatory for
     * the fixer to make changes, signatures of methods without it (no
     * docblock, inheritdocs) will not be fixed. [3] Manual actions are
     * required if inherited signatures are not properly documented.
     */
    'phpdoc_to_param_type' => true,

    /*
     * EXPERIMENTAL: Takes `@var` annotation of non-mixed types and
     * adjusts accordingly the property signature. Requires PHP >= 7.4.
     *
     * Risky!
     * This rule is EXPERIMENTAL and [1] is not covered with backward
     * compatibility promise. [2] `@var` annotation is mandatory for the
     * fixer to make changes, signatures of properties without it (no
     * docblock) will not be fixed. [3] Manual actions might be required
     * for newly typed properties that are read before initialization.
     */
    'phpdoc_to_property_type' => false,

    /*
     * EXPERIMENTAL: Takes `@return` annotation of non-mixed types and
     * adjusts accordingly the function signature. Requires PHP >= 7.0.
     *
     * Risky!
     * This rule is EXPERIMENTAL and [1] is not covered with backward
     * compatibility promise. [2] `@return` annotation is mandatory for
     * the fixer to make changes, signatures of methods without it (no
     * docblock, inheritdocs) will not be fixed. [3] Manual actions are
     * required if inherited signatures are not properly documented.
     */
    'phpdoc_to_return_type' => true,

    /*
     * PHPDoc should start and end with content, excluding the very
     * first and last line of the docblocks.
     */
    'phpdoc_trim' => true,

    /*
     * Removes extra blank lines after summary and after description in
     * PHPDoc.
     */
    'phpdoc_trim_consecutive_blank_line_separation' => true,

    // The correct case must be used for standard PHP types in PHPDoc.
    'phpdoc_types' => true,

    // Sorts PHPDoc types.
    'phpdoc_types_order' => [
        'sort_algorithm' => 'none',
        'null_adjustment' => 'always_last',
    ],

    /*
     * `@var` and `@type` annotations must have type and name in the
     * correct order.
     */
    'phpdoc_var_annotation_correct_order' => true,

    /*
     * `@var` and `@type` annotations of classy properties should not
     * contain the name.
     */
    'phpdoc_var_without_name' => true,

    /*
     * Converts `pow` to the `**` operator.
     *
     * Risky!
     * Risky when the function `pow` is overridden.
     */
    'pow_to_exponentiation' => true,

    /*
     * Converts `protected` variables and methods to `private` where
     * possible.
     */
    'protected_to_private' => true,

    /*
     * Classes must be in a path that matches their namespace, be at
     * least one namespace deep and the class name should match the file
     * name.
     *
     * Risky!
     * This fixer may change your class name, which will break the code
     * that depends on the old name.
     */
    'psr_autoloading' => false,

    /*
     * Replaces `rand`, `srand`, `getrandmax` functions calls with their
     * `mt_*` analogs or `random_int`.
     *
     * Risky!
     * Risky when the configured functions are overridden. Or when
     * relying on the seed based generating of the numbers.
     */
    'random_api_migration' => [
        'replacements' => [
            'mt_rand' => 'random_int',
            'rand' => 'random_int',
        ],
    ],

    /*
     * Callables must be called without using `call_user_func*` when
     * possible.
     *
     * Risky!
     * Risky when the `call_user_func` or `call_user_func_array`
     * function is overridden or when are used in constructions that
     * should be avoided, like `call_user_func_array('foo', ['bar' =>
     * 'baz'])` or `call_user_func($foo, $foo = 'bar')`.
     */
    'regular_callable_call' => true,

    /*
     * Local, dynamic and directly referenced variables should not be
     * assigned and directly returned by a function or method.
     */
    'return_assignment' => true,

    /*
     * There should be one or no space before colon, and one space after
     * it in return type declarations, according to configuration.
     *
     * Rule is applied only in a PHP 7+ environment.
     */
    'return_type_declaration' => true,

    /*
     * Inside class or interface element `self` should be preferred to
     * the class name itself.
     *
     * Risky!
     * Risky when using dynamic calls like get_called_class() or late
     * static binding.
     */
    'self_accessor' => true,

    /*
     * Inside a `final` class or anonymous class `self` should be
     * preferred to `static`.
     */
    'self_static_accessor' => true,

    // Instructions must be terminated with a semicolon.
    'semicolon_after_instruction' => true,

    /*
     * Cast shall be used, not `settype`.
     *
     * Risky!
     * Risky when the `settype` function is overridden or when used as
     * the 2nd or 3rd expression in a `for` loop .
     */
    'set_type_to_cast' => true,

    /*
     * Cast `(boolean)` and `(integer)` should be written as `(bool)`
     * and `(int)`, `(double)` and `(real)` as `(float)`, `(binary)` as
     * `(string)`.
     */
    'short_scalar_cast' => true,

    /*
     * Converts explicit variables in double-quoted strings and heredoc
     * syntax from simple to complex format (`${` to `{$`).
     *
     * Doesn't touch implicit variables. Works together nicely with
     * `explicit_string_variable`.
     */
    'simple_to_complex_string_variable' => true,

    /*
     * Simplify `if` control structures that return the boolean result
     * of their condition.
     */
    'simplified_if_return' => true,

    /*
     * A return statement wishing to return `void` should not return
     * `null`.
     */
    'simplified_null_return' => true,

    /*
     * A PHP file without end tag must always end with a single empty
     * line feed.
     */
    'single_blank_line_at_eof' => true,

    /*
     * There should be exactly one blank line before a namespace
     * declaration.
     */
    'single_blank_line_before_namespace' => true,

    /*
     * There MUST NOT be more than one property or constant declared per
     * statement.
     */
    'single_class_element_per_statement' => true,

    // There MUST be one use keyword per declaration.
    'single_import_per_statement' => true,

    /*
     * Each namespace use MUST go on its own line and there MUST be one
     * blank line after the use statements block.
     */
    'single_line_after_imports' => true,

    /*
     * Single-line comments and multi-line comments with only one line
     * of actual content should use the `//` syntax.
     */
    'single_line_comment_style' => true,

    // Throwing exception must be done in single line.
    'single_line_throw' => false,

    // Convert double quotes to single quotes for simple strings.
    'single_quote' => [
        'strings_containing_single_quote_chars' => false,
    ],

    // Ensures a single space after language constructs.
    'single_space_after_construct' => true,

    // Each trait `use` must be done as single statement.
    'single_trait_insert_per_statement' => true,

    // Fix whitespace after a semicolon.
    'space_after_semicolon' => true,

    // Increment and decrement operators should be used if possible.
    'standardize_increment' => true,

    // Replace all `<>` with `!=`.
    'standardize_not_equals' => true,

    /*
     * Lambdas not (indirect) referencing `$this` must be declared
     * `static`.
     *
     * Risky!
     * Risky when using `->bindTo` on lambdas without referencing to
     * `$this`.
     */
    'static_lambda' => true,

    /*
     * Comparisons should be strict.
     *
     * Risky!
     * Changing comparisons to strict might change code behavior.
     */
    'strict_comparison' => true,

    /*
     * Functions should be used with `$strict` param set to `true`.
     *
     * The functions "array_keys", "array_search", "base64_decode",
     * "in_array" and "mb_detect_encoding" should be used with $strict
     * param.
     *
     * Risky!
     * Risky when the fixed function is overridden or if the code relies
     * on non-strict usage.
     */
    'strict_param' => true,

    /*
     * String tests for empty must be done against `''`, not with
     * `strlen`.
     *
     * Risky!
     * Risky when `strlen` is overridden, when called using a
     * `stringable` object, also no longer triggers warning when called
     * using non-string(able).
     */
    'string_length_to_empty' => true,

    /*
     * All multi-line strings must use correct line ending.
     *
     * Risky!
     * Changing the line endings of multi-line strings might affect
     * string comparisons and outputs.
     */
    'string_line_ending' => true,

    // A case should be followed by a colon and not a semicolon.
    'switch_case_semicolon_to_colon' => true,

    // Removes extra spaces between colon and case value.
    'switch_case_space' => true,

    // Switch case must not be ended with `continue` but with `break`.
    'switch_continue_to_break' => true,

    // Standardize spaces around ternary operator.
    'ternary_operator_spaces' => true,

    /*
     * Use the Elvis operator `?:` where possible.
     *
     * Risky!
     * Risky when relying on functions called on both sides of the `?`
     * operator.
     */
    'ternary_to_elvis_operator' => true,

    /*
     * Use `null` coalescing operator `??` where possible. Requires PHP
     * >= 7.0.
     */
    'ternary_to_null_coalescing' => true,

    /*
     * Multi-line arrays, arguments list and parameters list must have a
     * trailing comma.
     */
    'trailing_comma_in_multiline' => [
        'after_heredoc' => true,
    ],

    /*
     * Arrays should be formatted like function/method arguments,
     * without leading or trailing single line space.
     */
    'trim_array_spaces' => true,

    // A single space or none should be around union type operator.
    'types_spaces' => true,

    // Unary operators should be placed adjacent to their operands.
    'unary_operator_spaces' => true,

    /*
     * Anonymous functions with one-liner return statement must use
     * arrow functions.
     *
     * Risky!
     * Risky when using `isset()` on outside variables that are not
     * imported with `use ()`.
     */
    'use_arrow_functions' => true,

    /*
     * Visibility MUST be declared on all properties and methods;
     * `abstract` and `final` MUST be declared before the visibility;
     * `static` MUST be declared after the visibility.
     */
    'visibility_required' => [
        'elements' => [
            'const',
            'method',
            'property',
        ],
    ],

    /*
     * Add `void` return type to functions with missing or empty return
     * statements, but priority is given to `@return` annotations.
     * Requires PHP >= 7.1.
     *
     * Risky!
     * Modifies the signature of functions.
     */
    'void_return' => true,

    /*
     * In array declaration, there MUST be a whitespace after each
     * comma.
     */
    'whitespace_after_comma_in_array' => true,

    /*
     * Write conditions in Yoda style (`true`), non-Yoda style
     * (`['equal' => false, 'identical' => false, 'less_and_greater' =>
     * false]`) or ignore those conditions (`null`) based on
     * configuration.
     */
    'yoda_style' => [
        'equal' => false,
        'identical' => false,
        'less_and_greater' => false,
    ],
];

if (\PHP_SAPI === 'cli' && !class_exists(\PhpCsFixer\Config::class)) {
    $which = static function ($program, $default = null) {
        exec(sprintf('command -v %s', escapeshellarg($program)), $output, $resultCode);
        if ($resultCode === 0) {
            return trim($output[0]);
        }

        return $default;
    };
    $findExistsFile = static function (array $files): ?string {
        foreach ($files as $file) {
            if ($file !== null && is_file($file)) {
                return $file;
            }
        }

        return null;
    };

    $fixerBinaries = [
        __DIR__ . '/vendor/bin/php-cs-fixer',
        __DIR__ . '/tools/php-cs-fixer/vendor/bin/php-cs-fixer',
        $which('php-cs-fixer'),
        isset($_SERVER['COMPOSER_HOME']) ? $_SERVER['COMPOSER_HOME'] . '/vendor/bin/php-cs-fixer' : null,
    ];
    $fixerBin = $findExistsFile($fixerBinaries) ?? 'php-cs-fixer';
    $phpBin = $_SERVER['_'] ?? 'php';

    $dryRun = !in_array('--force', $_SERVER['argv'], true);
    $commandFormat = '%s %s fix --config %s --diff --ansi -vv%s';
    $command = sprintf(
        $commandFormat,
        escapeshellarg($phpBin),
        escapeshellarg($fixerBin),
        escapeshellarg(__FILE__),
        $dryRun ? ' --dry-run' : ''
    );
    $outputCommand = sprintf(
        $commandFormat,
        $phpBin,
        strpos($fixerBin, ' ') === false ? $fixerBin : escapeshellarg($fixerBin),
        escapeshellarg(__FILE__),
        $dryRun ? ' --dry-run' : ''
    );

    fwrite(\STDOUT, "\e[22;94m" . $outputCommand . "\e[m\n\n");
    system($command, $returnCode);

    if ($dryRun || $returnCode === 8) {
        fwrite(\STDOUT, "\n\e[1;40;93m\e[K\n");
        fwrite(\STDOUT, "    [DEBUG] Dry run php-cs-fixer config.\e[K\n");
        fwrite(\STDOUT, "            Only shows which files would have been modified.\e[K\n");
        fwrite(\STDOUT, "            To apply the rules, use the --force option:\e[K\n\e[K\n");
        fwrite(
            \STDOUT,
            sprintf(
                "            \e[1;40;92m%s %s --force\e[K\n\e[0m\n",
                basename($phpBin),
                $_SERVER['argv'][0]
            )
        );
    } elseif ($returnCode !== 0) {
        fwrite(\STDERR, sprintf("\n\e[1;41;97m\e[K\n    ERROR CODE: %s\e[K\n\e[0m\n", $returnCode));
    }

    exit($returnCode);
}

return (new \PhpCsFixer\Config())
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setFinder(
        \PhpCsFixer\Finder::create()
            ->ignoreUnreadableDirs()
            ->in(__DIR__)
    )
;
