<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */

namespace ScssPhp\ScssPhp\Tests;

use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Node\Number;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

/**
 * API test
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class ApiTest extends TestCase
{
    use ExpectDeprecationTrait;

    private $scss;

    public function testUserFunction()
    {
        $this->scss = new Compiler();

        $this->scss->registerFunction('add-two', function ($args) {
            list($a, $b) = $args;
            return $a[1] + $b[1];
        });

        $this->assertEquals(
            'result: 30;',
            $this->compile('result: add-two(10, 20);')
        );
    }

    public function testUserFunctionNull()
    {
        $this->scss = new Compiler();

        $this->scss->registerFunction('get-null', function ($args) {
            return Compiler::$null;
        });

        $this->assertEquals(
            '',
            $this->compile('result: get-null();')
        );
    }

    public function testUserFunctionKwargs()
    {
        $this->scss = new Compiler();

        $this->scss->registerFunction(
            'divide',
            function ($args, $kwargs) {
                return $kwargs['dividend'][1] / $kwargs['divisor'][1];
            },
            ['dividend', 'divisor']
        );

        $this->assertEquals(
            'result: 15;',
            $this->compile('result: divide($divisor: 2, $dividend: 30);')
        );
    }

    public function testImportCustomCallback()
    {
        $this->scss = new Compiler();

        $this->scss->addImportPath(function ($path) {
            return __DIR__ . '/inputs/' . str_replace('.css', '.scss', $path);
        });

        $this->assertEquals(
            trim(file_get_contents(__DIR__ . '/outputs/variables.css')),
            $this->compile('@import "variables.css";')
        );
    }

    public function testImportAbsolutePath()
    {
        $this->scss = new Compiler();

        $basePath = __DIR__ . \DIRECTORY_SEPARATOR . 'inputs';
        $basePathAsScss = '"' . str_replace('\\', '\\\\', $basePath) . '"';

        $this->scss->setVariables(['base-path' => $basePathAsScss]);
        $this->scss->addImportPath(__DIR__ . \DIRECTORY_SEPARATOR . 'inputs');

        $this->assertEquals(
            trim(file_get_contents(__DIR__ . '/outputs/variables.css')),
            $this->compile('@import $base-path + "/variables.scss";')
        );
    }

    /**
     * @dataProvider provideSetVariables
     */
    public function testSetVariables($expected, $scss, $variables)
    {
        $this->scss = new Compiler();

        $this->scss->setVariables($variables);

        $this->assertEquals($expected, $this->compile($scss));
    }

    public function provideSetVariables()
    {
        return [
            [
                ".magic {\n  color: red;\n  width: 760px;\n}",
                '.magic { color: $color; width: $base - 200; }',
                [
                    'color' => 'red',
                    'base'  => '960px',
                ],
            ],
            [
                ".logo {\n  color: gray;\n}",
                '.logo { color: desaturate($primary, 100%); }',
                [
                    'primary' => '#ff0000',
                ],
            ],
            // !default
            [
                ".default {\n  color: red;\n}",
                '$color: red !default;' . "\n" . '.default { color: $color; }',
                [
                ],
            ],
            // no !default
            [
                ".default {\n  color: red;\n}",
                '$color: red;' . "\n" . '.default { color: $color; }',
                [
                ],
                    'color' => 'blue',
            ],
            // override !default
            [
                ".default {\n  color: blue;\n}",
                '$color: red !default;' . "\n" . '.default { color: $color; }',
                [
                    'color' => 'blue',
                ],
            ],
        ];
    }

    public function testCompileByteOrderMarker()
    {
        $this->scss = new Compiler();

        // test that BOM is stripped/ignored
        $this->assertEquals(
            '@import "main.css";',
            $this->compile("\xEF\xBB\xBF@import \"main.css\";")
        );
    }

    /**
     * @group legacy
     */
    public function testDeprecatedChildCompiler()
    {
        $this->expectDeprecation('Registering custom functions by extending the Compiler and using the lib* discovery mechanism is deprecated and will be removed in 2.0. Replace the "ScssPhp\ScssPhp\Tests\DeprecatedChildCompiler::libDeprecatedChild" method with registering the "deprecated_child" function through "Compiler::registerFunction".');
        $this->expectDeprecation('Overriding the "blue" core function by extending the Compiler is deprecated and will be unsupported in 2.0. Remove the "ScssPhp\ScssPhp\Tests\DeprecatedChildCompiler::libBlue" method.');
        $this->scss = new DeprecatedChildCompiler();

        $this->assertEquals(
            "a {\n  b: true;\n  c: 255;\n  d: 1;\n}",
            $this->compile("a {\n  b: deprecated_child();\n  c: red(#f00);\n  d: blue(#f00);\n}")
        );
    }

    public function compile($str)
    {
        return trim($this->scss->compile($str));
    }
}

class DeprecatedChildCompiler extends Compiler
{
    protected function libDeprecatedChild($args)
    {
        return self::$true;
    }

    protected function libBlue($args)
    {
        return new Number(1, '');
    }
}
