<?php

require __DIR__.'/../../vendor/autoload.php';

use FriendsOfPhp\Number\Number;

$timeStart = microtime(true);

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
                $this->generateMethodDocumentation(),
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

    protected function generateMethodDocumentation(): string
    {
        return (new MethodDocumentationGenerator())->generate();
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

    public function __construct(string|array $code, string $language = '')
    {
        $this->code = trim(is_array($code) ? implode("\n", $code) : $code);
        $this->language = $language;
    }

    public function __toString(): string
    {
        return '```'.$this->language."\n".$this->code."\n```";
    }
}

/** @internal Generates method documentation for the Number class */
class MethodDocumentationGenerator
{
    protected readonly ReflectionClass $reflectionClass;
    protected readonly ExampleParser $examples;
    /** @var array<string, ReflectionMethod> */
    protected array $methodsToDocument;
    /** @var array<string, MarkdownBlock> */
    protected array $methodDocumentation;

    public function __construct()
    {
        $this->reflectionClass = new ReflectionClass(Number::class);
    }

    public function generate(): string
    {
        $this->parseExamples();
        $this->discoverMethodsToDocument();
        $this->generateMethodsDocumentation();

        return $this->compile();
    }

    protected function parseExamples(): void
    {
        $this->examples = examples();
    }

    protected function discoverMethodsToDocument(): void
    {
        $this->methodsToDocument = [];
        foreach ($this->reflectionClass->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor() && !$method->isDestructor()) {
                $this->methodsToDocument[$method->getName()] = $method;
            }
        }
    }

    protected function generateMethodsDocumentation(): void
    {
        $this->methodDocumentation = [];
        foreach ($this->methodsToDocument as $methodName => $method) {
            $this->methodDocumentation[$methodName] = $this->generateMethodDocumentation($method);
        }
    }

    protected function generateMethodDocumentation(ReflectionMethod $method): MarkdownBlock
    {
        $phpDoc = PHPDoc::parse($method->getDocComment());

        return new MarkdownBlock(
            new MarkdownHeading("`Number::{$method->getName()}()`", 3),
            [
                $phpDoc->description,
                new MarkdownCodeBlock(
                    $this->generateMethodSignature($method, $phpDoc),
                    'php'
                ),
                new MarkdownBlock(
                    new MarkdownHeading('Usage', 4),
                    new MarkdownCodeBlock(
                        $this->examples->getExamplesForMethod($method->getName()) ?: '// No examples available',
                        'php'
                    )
                ),
            ]
        );
    }

    protected function generateMethodSignature(ReflectionMethod $method, PHPDoc $phpDoc): string
    {
        $signature = "Number::{$method->getName()}(";
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type) {
                if (method_exists($type, 'getTypes')) {
                    $type = implode('|', $type->getTypes());
                } else {
                    $type = $type->getName();
                }
            }
            $docParam = $phpDoc->params[$parameter->getName()] ?? null;
            if ($docParam) {
                $type = $docParam;
            }

            $typeString = ($parameter->isOptional() ? '?' : '').$type.' ';

            $parameters[] = $typeString. '$'.$parameter->getName();
        }
        $signature .= implode(', ', $parameters);
        $signature .= ')';
        $returnType = $phpDoc->returnType ?? $method->getReturnType() ?? 'mixed';
        return $signature.': '.$returnType;
    }

    protected function compile(): string
    {
        $markdown = [];
        foreach ($this->methodDocumentation as $method) {
            $markdown[] = $method;
        }
        return implode("\n\n", $markdown);
    }
}

/**
 * @internal Represents a PHPDoc comment
 *
 * @property-read string $comment
 * @property-read string $description
 * @property-read string|null $returnType
 * @property-read array<string, string> $params
 * @property-read array<string, string> $extraTags
 */
class PHPDoc
{
    protected string $comment;
    protected string $description;
    protected ?string $returnType = null;
    protected array $params = [];
    protected array $extraTags = [];
    
    public static function parse(string $comment): static
    {
        return new static($comment);
    }

    public function __construct(string $comment)
    {
        $this->comment = static::stripCommentDirectives($comment);
        $this->parseTags();
    }

    protected function parseTags(): void
    {
        $lines = explode("\n", $this->comment);
        $description = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '@')) {
                $parts = explode(' ', $line);
                $tag = substr(array_shift($parts), 1);

                if ($tag === 'return') {
                    $this->returnType = array_shift($parts);
                    continue;
                }

                if ($tag === 'param') {
                    $paramName = trim($parts[1], '$');
                    $paramType = $parts[0];

                    $this->params[$paramName] = $paramType;

                    continue;
                }

                $tagId = $tag;
                if (isset($this->extraTags[$tagId])) {
                    $count = 1;
                    while (isset($this->extraTags[$tagId.'-'.$count])) {
                        $count++;
                    }
                    $tagId .= '-'.$count;
                }
                $this->extraTags[$tagId] = implode(' ', $parts);
            } else {
                $description .= $line."\n";
            }
        }
        if ($description) {
            $this->description = trim($description);
        }
    }

    public function getTags(): array
    {
        return $this->extraTags;
    }

    public function __get(string $name): null|string|array
    {
        return $this->{$name} ?? $this->extraTags[$name] ?? null;
    }

    protected static function stripCommentDirectives(string $comment): string
    {
        return trim(implode("\n", array_map(function (string $line): string {
            return trim(str_replace(['*', '/'], '', $line));
        }, explode("\n", $comment))));
    }
}

/** @internal Parses the examples returned by the examples function to a more usable format */
class ExampleParser
{
    protected array $examples;

    public static function parse(array $examples): static
    {
        return new static($examples);
    }

    /** @param array $examples Array of the return values */
    public function __construct(array $examples)
    {
        $this->examples = $this->parseExamples($examples);
    }

    public function getExamplesForMethod(string $method): array
    {
        return array_filter($this->examples, function (ExampleObject $example) use ($method) {
            return str_starts_with($example->source, "Number::$method");
        });
    }

    protected function parseExamples(array $input): array
    {
        $contents = explode("\n", file_get_contents(__FILE__));
        $functionCallLine = debug_backtrace()[2]['line'] - count($input);
        $examples = [];

        foreach ($input as $index => $result) {
            $source = trim($contents[$functionCallLine + $index], "\t\n\r\0\x0B, ");
            $examples[] = new ExampleObject($source, $result);
        }

        return $examples;
    }
}

/** @internal Represents an example object */
class ExampleObject implements Stringable
{
    public readonly string $source;
    public readonly string $result;

    public function __construct($source, $result)
    {
        $this->source = $source;
        $this->result = $result;
    }

    public function __toString(): string
    {
        return "echo $this->source; // $this->result";
    }
}

// Config functions

/** Binds examples to be documented */
function examples(): ExampleParser
{
    return ExampleParser::parse([
        Number::format(1234567.89),
        Number::spell(1234),
        Number::ordinal(42),
        Number::percentage(0.75),
        Number::currency(1234.56, 'EUR'),
        Number::fileSize(1024),
        Number::forHumans(1234567.89),
    ]);
}

// Run the generator
$generator = new DocumentationGenerator();
$generator->generate();

echo $generator->getMarkdown() . "\n\n";

// Temp for testing
file_put_contents(__DIR__.'/../../vendor/hyde/_docs/index.md', $generator->getMarkdown());

function dd($data)
{
    var_dump($data);
    die;
}

function checkForIssues(string $document): string|false
{
    $errors = [];
    if (str_contains($document, '// No examples available')) {
        $errors[] = 'No examples available for one or more methods';
    }

    if ($errors) {
        return sprintf("\033[31mFound %s %s!\033[0m\n", Number::format(count($errors)), count($errors) === 1 ? 'issue' : 'issues').'- '. implode("\n- ", $errors)."\n\n";
    }
    return false;
}

$errors = checkForIssues($generator->getMarkdown());

if ($errors) {
    echo $errors;
}

echo sprintf("\033[32mAll done!\033[0m Generated in: %sms\n", Number::format((microtime(true) - $timeStart) * 1000));

if ($errors) {
    exit(1);
}
