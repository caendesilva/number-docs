<?php

require __DIR__.'/../../vendor/autoload.php';

use FriendsOfPhp\Number\Number;

/** @internal This class is used by the Number package to generate documentation for the package, using reflection and source code evaluation. It is not intended to be used outside of the package. */
class DocumentationGenerator
{
    protected readonly ReadmeData $readme;
    protected readonly array $composerData;

    protected array $markdownSections = [];
    protected string $markdown;

    public function generate(): void
    {
        $this->loadAndParseReadmeData();
        $this->loadAndParseComposerData();
        $this->assembleDocument();
        $this->compileDocument();
    }

    public function getMarkdown(): string
    {
        return $this->markdown;
    }

    protected function loadAndParseReadmeData(): void
    {
        $this->readme = new ReadmeData();
    }

    protected function loadAndParseComposerData(): void
    {
        $this->composerData = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
    }

    protected function assembleDocument(): void
    {
        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading($this->readme->title, 1),
                $this->readme->description
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Installation', 2),
                $this->generateInstallationMarkdown(),
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Basic Usage', 2),
                [
                    'The Number package provides a single class, `Number`, which can be used to format numbers in a variety of ways. Here are some example, with the full reference below.',
                    $this->readme->getBlock('basic-usage')->getContent(),
                ]
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Full Reference', 2),
                '// TODO',
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('License', 2),
                $this->readme->license,
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Attributions', 2),
                $this->readme->attributions,
            )
        );

        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Contributing', 2),
                $this->readme->contributing ?? 'Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.',
            )
        );
    }

    protected function compileDocument(): void
    {
        $this->markdown = implode("\n\n", $this->markdownSections);
    }

    protected function addBlock(MarkdownBlock $block): void
    {
        $this->markdownSections[] = $block;
    }

    protected function generateInstallationMarkdown(): array
    {
        return [
            'Install the package using Composer:',
            new MarkdownCodeBlock("composer require {$this->composerData['name']}", 'bash'),
        ];
    }
}

/**
 * @internal Data object for the Readme
 * @property-read string $title
 * @property-read string $description
 * @property-read string $license
 * @property-read string $attributions
 */
class ReadmeData
{
    protected readonly string $contents;
    protected readonly array $lines;
    protected readonly array $blocks;
    protected array $data = [];

    public function __construct()
    {
        $this->contents = file_get_contents(__DIR__.'/../../README.md');
        $this->lines = explode("\n", $this->contents);
        $this->parseReadme();
        $this->parseData();
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function getBlock(string $id): MarkdownBlock
    {
        return $this->blocks[$id];
    }

    protected function parseReadme(): void
    {
        // Start by generating blocks. Each block starts with a heading, and ends with the next heading or the end of the file.
        $blocks = [];
        $currentBlock = null;
        foreach ($this->lines as $line) {
            if (str_starts_with($line, '#')) {
                if ($currentBlock) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = new MarkdownBlock(
                    new MarkdownHeading(
                        trim($line, '# '),
                        substr_count($line, '#')
                    ),
                    ''
                );
            } else {
                $currentBlock->addLine($line);
            }
        }

        // Add the last block
        $blocks[] = $currentBlock;

        // Iterate over blocks to set string identifiers
        $parsedBlocks = [];
        foreach ($blocks as $block) {
            // kebab-case of title
            $id = strtolower(str_replace(' ', '-', $block->getHeading()->getText()));
            // If id is already in use, append a number
            if (isset($parsedBlocks[$id])) {
                $number = 1;
                while (isset($parsedBlocks[$id.'-'.$number])) {
                    $number++;
                }
                $id .= '-'.$number;
            }
            $parsedBlocks[$id] = $block;
        }

        $this->blocks = $parsedBlocks;
    }

    protected function parseData(): void
    {
        $this->data['title'] = $this->blocks[array_key_first($this->blocks)]->getHeading()->getText();
        $this->data['description'] = $this->blocks[array_key_first($this->blocks)]->getContent();
        $this->data['license'] = $this->blocks['license']->getContent();
        $this->data['attributions'] = $this->blocks['attributions']->getContent();
    }
}

/** @internal Represents a Markdown section */
class MarkdownBlock implements Stringable
{
    protected MarkdownHeading $heading;
    protected string $content;

    public function __construct(MarkdownHeading $heading, string|array $content)
    {
        $this->heading = $heading;
        $this->content = trim(is_array($content) ? implode("\n\n", $content) : $content);
    }

    public function __toString(): string
    {
        return $this->heading."\n\n".$this->getContent();
    }

    public function addLine(string $line): void
    {
        $this->content .= $line."\n";
    }

    public function getHeading(): MarkdownHeading
    {
        return $this->heading;
    }

    public function getContent(): string
    {
        return trim($this->content);
    }
}

/** @internal Represents a Markdown heading */
class MarkdownHeading implements Stringable
{
    protected string $text;
    protected int $level;

    public function __construct(string $text, int $level)
    {
        $this->text = $text;
        $this->level = $level;
    }

    public function __toString(): string
    {
        return str_repeat('#', $this->level).' '.$this->text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getLevel(): int
    {
        return $this->level;
    }
}

/** @internal Represents a Markdown code block */
class MarkdownCodeBlock implements Stringable
{
    protected string $code;
    protected string $language;

    public function __construct(string $code, string $language = '')
    {
        $this->code = $code;
        $this->language = $language;
    }

    public function __toString(): string
    {
        return '```'.$this->language."\n".$this->code."\n```";
    }
}


// Run the generator
$generator = new DocumentationGenerator();
$generator->generate();

echo $generator->getMarkdown();


function dd($data)
{
    var_dump($data);
    die;
}
