<?php

declare(strict_types=1);

/**
 * FileName: .php-cs-fixer.php
 * ==============================================
 * Copy right 2016-2020
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 *
 * @author: 永 | <chuanshuo_yongyuan@163.com>
 * @date  : 2021/11/25 14:21
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$rules = [
    '@PSR12'                                => true,
    'binary_operator_spaces'                => [
        'default' => 'align_single_space',
    ], //等号对齐、数字箭头符号对齐
    'blank_line_after_opening_tag'          => true,
    'compact_nullable_typehint'             => true,
    'declare_equal_normalize'               => true,
    'lowercase_cast'                        => true,
    'lowercase_static_reference'            => true,
    'new_with_braces'                       => true,
    'no_blank_lines_after_class_opening'    => true,
    'no_leading_import_slash'               => true,
    'ordered_class_elements'                => [
        'order'          => [
            'use_trait',
            'constant_public',
            'constant_protected',
            'constant_private',
            'public',
            'protected',
            'private',
            'method_public',
            'method_protected',
            'method_private',
        ],
        'sort_algorithm' => 'alpha',
    ],
    'ordered_imports'                       => [
        'imports_order'  => [
            'class',
            'function',
            'const',
        ],
        'sort_algorithm' => 'length',
    ],
    'return_type_declaration'               => true,
    'short_scalar_cast'                     => true,
    'single_blank_line_before_namespace'    => true,
    'single_trait_insert_per_statement'     => true,
    'ternary_operator_spaces'               => true,
    'unary_operator_spaces'                 => true,
    'visibility_required'                   => [
        'elements' => [
            'const',
            'method',
            'property',
        ],
    ],
    'align_multiline_comment'               => true,
    'no_trailing_whitespace'                => true,
    'echo_tag_syntax'                       => true,
    'no_unused_imports'                     => true, // 删除没用到的use
    'no_empty_statement'                    => true, //多余的分号
    'no_whitespace_in_blank_line'           => true, //删除空行中的空格
    'concat_space'                          => ['spacing' => 'one'], // .拼接必须有空格分割
    'array_syntax'                          => ['syntax' => 'short'],
    'single_quote'                          => true, //简单字符串应该使用单引号代替双引号
    'blank_line_before_statement'           => [
        'statements' => [
            'break',
            'continue',
            'declare',
            'return',
            'throw',
            'try',
        ],
    ], // 空行换行必须在任何已配置的语句之前
    'no_trailing_comma_in_singleline_array' => true, // 删除单行数组中的逗号
];

$finder = Finder::create()
    ->exclude('tests/')
    ->exclude('vendor')
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
