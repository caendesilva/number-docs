<?php

require __DIR__.'/../../vendor/autoload.php';

use FriendsOfPhp\Number\Number;

/** @internal This class is used by the Number package to generate documentation for the package, using reflection and source code evaluation. It is not intended to be used outside of the package. */
class DocumentationGenerator
{
    protected array $markdownSections = [];
    protected string $markdown;

    public function generate(): void
    {
        $this->assembleDocument();
        $this->compileDocument();
    }

    public function getMarkdown(): string
    {
        return $this->markdown;
    }

    protected function assembleDocument(): void {
        $this->addBlock(
            new MarkdownBlock(
                new MarkdownHeading('Number', 1),
                'PHP Number Utility'
            )
        );
    }

    protected function compileDocument(): void {
        $this->markdown = implode("\n\n", $this->markdownSections);
    }

    protected function addBlock(MarkdownBlock $block): void {
        $this->markdownSections[] = $block;
    }
}

/** @internal Represents a Markdown section */
class MarkdownBlock implements Stringable {
    protected MarkdownHeading $heading;
    protected string $content;

    public function __construct(MarkdownHeading $heading, string $content)
    {
        $this->heading = $heading;
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->heading."\n\n".$this->content;
    }
}

/** @internal Represents a Markdown heading */
class MarkdownHeading implements Stringable {
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
}


// Run the generator
$generator = new DocumentationGenerator();
$generator->generate();

echo $generator->getMarkdown();