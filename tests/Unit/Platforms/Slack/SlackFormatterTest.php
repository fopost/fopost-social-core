<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Slack;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Platforms\Slack\SlackFormatter;

class SlackFormatterTest extends TestCase
{
    private SlackFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SlackFormatter();
    }

    // -----------------------------------------------------------------------
    //  format() – mrkdwn plain text
    // -----------------------------------------------------------------------

    #[Test]
    public function it_formats_title_as_bold_mrkdwn(): void
    {
        $post = new Post(title: 'Hello World', body: 'Some content');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('*Hello World*', $result);
        $this->assertStringContainsString('Some content', $result);
    }

    #[Test]
    public function it_escapes_special_mrkdwn_chars_in_title(): void
    {
        $post = new Post(title: 'A <b>bold</b> & "great" title', body: '');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('*A &lt;b&gt;bold&lt;/b&gt; &amp; "great" title*', $result);
    }

    #[Test]
    public function it_formats_tags_as_inline_code(): void
    {
        $post = new Post(title: '', body: 'content', tags: ['php', 'laravel']);

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('`php`', $result);
        $this->assertStringContainsString('`laravel`', $result);
    }

    #[Test]
    public function it_formats_url_as_slack_link(): void
    {
        $post = new Post(title: '', body: 'content', url: 'https://example.com/article');

        $result = $this->formatter->format($post);

        $this->assertStringContainsString('<https://example.com/article|Read more>', $result);
    }

    #[Test]
    public function it_formats_complete_post(): void
    {
        $post = new Post(
            title: 'My Post',
            body: 'This is the body.',
            url: 'https://example.com',
            tags: ['news', 'tech'],
        );

        $result = $this->formatter->format($post);

        // All parts present, separated by double newlines
        $this->assertStringContainsString('*My Post*', $result);
        $this->assertStringContainsString('This is the body.', $result);
        $this->assertStringContainsString('`news`', $result);
        $this->assertStringContainsString('`tech`', $result);
        $this->assertStringContainsString('<https://example.com|Read more>', $result);
    }

    #[Test]
    public function it_truncates_at_max_length(): void
    {
        $longBody = str_repeat('A', 45_000);
        $post = new Post(title: '', body: $longBody);

        $result = $this->formatter->format($post);

        $this->assertLessThanOrEqual(40_000, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    #[Test]
    public function it_handles_empty_post(): void
    {
        $post = new Post(title: '', body: '');

        $result = $this->formatter->format($post);

        $this->assertSame('', $result);
    }

    #[Test]
    public function it_handles_body_only_post(): void
    {
        $post = new Post(title: '', body: 'Just the body.');

        $result = $this->formatter->format($post);

        $this->assertSame('Just the body.', $result);
    }

    // -----------------------------------------------------------------------
    //  formatBlocks() – Block Kit
    // -----------------------------------------------------------------------

    #[Test]
    public function it_formats_blocks_with_header_and_section(): void
    {
        $post = new Post(title: 'Block Title', body: 'Block body text.');

        $blocks = $this->formatter->formatBlocks($post);

        $this->assertCount(3, $blocks); // header, section, divider

        $this->assertSame('header', $blocks[0]['type']);
        $this->assertSame('Block Title', $blocks[0]['text']['text']);

        $this->assertSame('section', $blocks[1]['type']);
        $this->assertSame('Block body text.', $blocks[1]['text']['text']);

        $this->assertSame('divider', $blocks[2]['type']);
    }

    #[Test]
    public function it_formats_blocks_with_tags_as_context(): void
    {
        $post = new Post(title: '', body: 'Content', tags: ['php', 'slack']);

        $blocks = $this->formatter->formatBlocks($post);

        // section + context + divider
        $contextBlock = $blocks[1]; // After section
        $this->assertSame('context', $contextBlock['type']);
        $this->assertStringContainsString('`php`', $contextBlock['elements'][0]['text']);
        $this->assertStringContainsString('`slack`', $contextBlock['elements'][0]['text']);
    }

    #[Test]
    public function it_formats_blocks_with_url_button(): void
    {
        $post = new Post(title: 'Title', body: '', url: 'https://example.com');

        $blocks = $this->formatter->formatBlocks($post);

        // Find the actions block
        $actionsBlock = null;
        foreach ($blocks as $block) {
            if ($block['type'] === 'actions') {
                $actionsBlock = $block;
                break;
            }
        }

        $this->assertNotNull($actionsBlock);
        $this->assertSame('button', $actionsBlock['elements'][0]['type']);
        $this->assertSame('https://example.com', $actionsBlock['elements'][0]['url']);
        $this->assertSame('Read more', $actionsBlock['elements'][0]['text']['text']);
    }

    #[Test]
    public function it_supports_custom_button_text_in_blocks(): void
    {
        $post = new Post(title: '', body: '', url: 'https://example.com');

        $blocks = $this->formatter->formatBlocks($post, ['button_text' => 'Visit']);

        $actionsBlock = null;
        foreach ($blocks as $block) {
            if ($block['type'] === 'actions') {
                $actionsBlock = $block;
                break;
            }
        }

        $this->assertNotNull($actionsBlock);
        $this->assertSame('Visit', $actionsBlock['elements'][0]['text']['text']);
    }

    #[Test]
    public function it_truncates_block_body_at_3000_chars(): void
    {
        $longBody = str_repeat('B', 4_000);
        $post = new Post(title: '', body: $longBody);

        $blocks = $this->formatter->formatBlocks($post);

        $sectionText = $blocks[0]['text']['text'];
        $this->assertLessThanOrEqual(3_000, mb_strlen($sectionText));
        $this->assertStringEndsWith('...', $sectionText);
    }

    #[Test]
    public function it_returns_empty_blocks_for_empty_post(): void
    {
        $post = new Post(title: '', body: '');

        $blocks = $this->formatter->formatBlocks($post);

        $this->assertSame([], $blocks);
    }

    // -----------------------------------------------------------------------
    //  Accessors
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_correct_platform_name(): void
    {
        $this->assertSame('slack', $this->formatter->platform());
    }

    #[Test]
    public function it_returns_max_length(): void
    {
        $this->assertSame(40_000, $this->formatter->maxLength());
    }
}
