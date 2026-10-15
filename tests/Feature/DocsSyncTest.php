<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The examples in the README and docs are the tested ones, word for word,
 * and every Antlers example in the docs has a Blade version.
 */
class DocsSyncTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Test]
    public function the_documented_examples_are_the_tested_ones(): void
    {
        $fixtures = $this->root().'/tests/__fixtures__/views/docs';
        $templates = file_get_contents($this->root().'/docs/templates.md');
        $readme = file_get_contents($this->root().'/README.md');

        foreach (glob($fixtures.'/*.{antlers.html,blade.php}', GLOB_BRACE) as $file) {
            $example = rtrim(file_get_contents($file), "\n");

            $this->assertStringContainsString($example, $templates, basename($file).' differs from docs/templates.md.');

            if (! str_starts_with(basename($file), 'schema')) {
                $this->assertStringContainsString($example, $readme, basename($file).' differs from the README.');
            }
        }
    }

    #[Test]
    public function every_antlers_example_has_a_blade_version(): void
    {
        foreach ([...glob($this->root().'/docs/*.md'), $this->root().'/README.md'] as $file) {
            $markdown = file_get_contents($file);

            $this->assertSame(
                substr_count($markdown, '```antlers'),
                substr_count($markdown, "<summary>Blade</summary>"),
                basename($file).' has an Antlers example without a Blade version.'
            );
        }
    }

    #[Test]
    public function links_between_docs_go_somewhere(): void
    {
        foreach ([...glob($this->root().'/docs/*.md'), $this->root().'/README.md'] as $file) {
            preg_match_all('~\]\(((?!https?:|mailto:|#)[^)#]+)(#[^)]*)?\)~', file_get_contents($file), $links);

            foreach ($links[1] as $link) {
                $this->assertFileExists(dirname($file).'/'.$link, basename($file)." links to [{$link}].");
            }
        }
    }
}
