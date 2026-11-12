<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    public function test_repeated_messages_render_once_with_inline_icon_and_escaped_text(): void
    {
        $message = 'Already enrolled in <Batch>.';
        session()->put(['error' => $message, 'warning' => $message, 'success' => 'Saved.']);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'batch_ids' => [$message], 'student_id' => [$message],
        ])));

        $html = Blade::render('<x-feedback />');

        $this->assertSame(1, substr_count($html, 'Already enrolled in &lt;Batch&gt;.'));
        $this->assertSame(1, substr_count($html, 'Saved.'));
        $this->assertSame(2, substr_count($html, 'role="alert"'));
        $this->assertSame(2, substr_count($html, 'd-flex align-items-baseline'));
        $this->assertSame(2, substr_count($html, 'flex-shrink-0'));
        $this->assertStringNotContainsString('<Batch>', $html);
    }

    public function test_matching_flash_messages_render_once_without_validation_errors(): void
    {
        session()->put(['success' => 'Saved.', 'warning' => 'Saved.']);
        view()->share('errors', new ViewErrorBag);

        $html = Blade::render('<x-feedback spacing="mx-3 mt-3" />');

        $this->assertSame(1, substr_count($html, 'role="alert"'));
        $this->assertSame(1, substr_count($html, 'Saved.'));
        $this->assertStringContainsString('mx-3 mt-3', $html);
    }
}
