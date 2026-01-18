<?php
/**
 * Integration tests for cache purge logic.
 *
 * @package Better_Nginx_Cache
 */

namespace BetterNginxCache\Tests\Integration;

use BetterNginxCache\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;

/**
 * Tests for the cache purge triggering logic.
 *
 * Tests the handle_post_status_change() method to ensure cache is purged
 * only when appropriate.
 */
class CachePurgeTest extends TestCase
{
    /**
     * Mock purge tracker.
     *
     * @var array
     */
    private $purge_calls = [];

    /**
     * Instance of the purge handler for testing.
     *
     * @var object
     */
    private $handler;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->purge_calls = [];
        $test = $this;

        // Create a minimal class that exposes the purge logic for testing.
        $this->handler = new class ($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            /**
             * Handle post status transitions to determine if cache should be purged.
             *
             * This is a copy of Better_Nginx_Cache::handle_post_status_change() for testing.
             *
             * @param string  $new_status New post status.
             * @param string  $old_status Old post status.
             * @param \WP_Post $post       Post object.
             */
            public function handle_post_status_change($new_status, $old_status, $post)
            {
                // Skip if no post object.
                if (!$post || !is_object($post)) {
                    return;
                }

                // Skip revisions and autosaves.
                if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
                    return;
                }

                // Skip internal post types that don't affect the frontend.
                $skip_types = array('revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'wp_global_styles');
                if (in_array($post->post_type, $skip_types, true)) {
                    return;
                }

                // Check excluded types filter.
                $excluded_types = (array) apply_filters('bnc_excluded_post_types', array());
                if (in_array($post->post_type, $excluded_types, true)) {
                    return;
                }

                $should_purge = false;

                // Publishing a post.
                if ('publish' === $new_status && 'publish' !== $old_status) {
                    $should_purge = true;
                }

                // Updating an already published post.
                if ('publish' === $new_status && 'publish' === $old_status) {
                    $should_purge = true;
                }

                // Unpublishing a post.
                if ('publish' === $old_status && 'publish' !== $new_status) {
                    $should_purge = true;
                }

                // Trashing a post.
                if ('trash' === $new_status) {
                    $should_purge = true;
                }

                // Apply filter for custom control.
                $should_purge = apply_filters('bnc_should_purge_on_status_change', $should_purge, $new_status, $old_status, $post);

                if ($should_purge) {
                    $this->purge_cache_once();
                }
            }

            /**
             * Mock purge method that tracks calls.
             */
            public function purge_cache_once()
            {
                $this->test->recordPurge();
            }
        };
    }

    /**
     * Record a purge call for assertions.
     */
    public function recordPurge(): void
    {
        $this->purge_calls[] = true;
    }

    /**
     * Get the number of purge calls.
     *
     * @return int
     */
    private function getPurgeCount(): int
    {
        return count($this->purge_calls);
    }

    /**
     * Create a mock post object.
     *
     * @param array $args Post properties.
     * @return object Mock post object.
     */
    private function createMockPost(array $args = []): object
    {
        $defaults = [
            'ID' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
        ];

        $post = (object) array_merge($defaults, $args);
        return $post;
    }

    /**
     * Set up WordPress function mocks for a standard test.
     *
     * Uses when() for filters so they don't fail if not called.
     *
     * @param bool $is_revision Whether to simulate a revision.
     * @param bool $is_autosave Whether to simulate an autosave.
     */
    private function setupWordPressMocks(bool $is_revision = false, bool $is_autosave = false): void
    {
        Functions\when('wp_is_post_revision')->justReturn($is_revision);
        Functions\when('wp_is_post_autosave')->justReturn($is_autosave);
        // Use when() instead of expect() so filters don't fail if path exits early.
        Filters\expectApplied('bnc_excluded_post_types')->zeroOrMoreTimes()->andReturn([]);
        Filters\expectApplied('bnc_should_purge_on_status_change')->zeroOrMoreTimes()->andReturnFirstArg();
    }

    // =========================================================================
    // Tests: Cache SHOULD be purged
    // =========================================================================

    /**
     * Test that publishing a new post triggers cache purge.
     */
    public function testPublishingNewPostTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    /**
     * Test that updating an already published post triggers cache purge.
     */
    public function testUpdatingPublishedPostTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('publish', 'publish', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    /**
     * Test that unpublishing a post triggers cache purge.
     */
    public function testUnpublishingPostTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('draft', 'publish', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    /**
     * Test that trashing a post triggers cache purge.
     */
    public function testTrashingPostTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('trash', 'publish', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    /**
     * Test that trashing a draft also triggers purge.
     */
    public function testTrashingDraftTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('trash', 'draft', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    /**
     * Test that publishing a page triggers cache purge.
     */
    public function testPublishingPageTriggersPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost(['post_type' => 'page']);

        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(1, $this->getPurgeCount());
    }

    // =========================================================================
    // Tests: Cache should NOT be purged
    // =========================================================================

    /**
     * Test that autosaves do not trigger cache purge.
     */
    public function testAutosavesDoNotTriggerPurge(): void
    {
        $this->setupWordPressMocks(false, true);
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('publish', 'publish', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that revisions do not trigger cache purge.
     */
    public function testRevisionsDoNotTriggerPurge(): void
    {
        $this->setupWordPressMocks(true, false);
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('publish', 'publish', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that draft to draft changes do not trigger purge.
     */
    public function testDraftToDraftDoesNotTriggerPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('draft', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that draft to pending changes do not trigger purge.
     */
    public function testDraftToPendingDoesNotTriggerPurge(): void
    {
        $this->setupWordPressMocks();
        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('pending', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that revision post type does not trigger purge.
     */
    public function testRevisionPostTypeDoesNotTriggerPurge(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);

        $post = $this->createMockPost(['post_type' => 'revision']);
        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that nav_menu_item post type does not trigger purge.
     */
    public function testNavMenuItemPostTypeDoesNotTriggerPurge(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);

        $post = $this->createMockPost(['post_type' => 'nav_menu_item']);
        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that customize_changeset post type does not trigger purge.
     */
    public function testCustomizeChangesetPostTypeDoesNotTriggerPurge(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);

        $post = $this->createMockPost(['post_type' => 'customize_changeset']);
        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that excluded post types do not trigger purge.
     */
    public function testExcludedPostTypesDoNotTriggerPurge(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Filters\expectApplied('bnc_excluded_post_types')->zeroOrMoreTimes()->andReturn(['product']);

        $post = $this->createMockPost(['post_type' => 'product']);

        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that null post object does not trigger purge.
     */
    public function testNullPostDoesNotTriggerPurge(): void
    {
        $this->handler->handle_post_status_change('publish', 'draft', null);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that non-object post does not trigger purge.
     */
    public function testNonObjectPostDoesNotTriggerPurge(): void
    {
        $this->handler->handle_post_status_change('publish', 'draft', 'not an object');

        $this->assertSame(0, $this->getPurgeCount());
    }

    // =========================================================================
    // Tests: Filter functionality
    // =========================================================================

    /**
     * Test that the bnc_should_purge_on_status_change filter can prevent purge.
     */
    public function testFilterCanPreventPurge(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Filters\expectApplied('bnc_excluded_post_types')->zeroOrMoreTimes()->andReturn([]);
        Filters\expectApplied('bnc_should_purge_on_status_change')->zeroOrMoreTimes()->andReturn(false);

        $post = $this->createMockPost();

        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertSame(0, $this->getPurgeCount());
    }

    /**
     * Test that the filter receives correct arguments.
     */
    public function testFilterReceivesCorrectArguments(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Filters\expectApplied('bnc_excluded_post_types')->zeroOrMoreTimes()->andReturn([]);

        $post = $this->createMockPost(['ID' => 42, 'post_type' => 'page']);
        $captured_args = [];

        Filters\expectApplied('bnc_should_purge_on_status_change')
            ->once()
            ->andReturnUsing(function ($should_purge, $new, $old, $p) use (&$captured_args) {
                $captured_args = [$should_purge, $new, $old, $p];
                return $should_purge;
            });

        $this->handler->handle_post_status_change('publish', 'draft', $post);

        $this->assertTrue($captured_args[0]);
        $this->assertSame('publish', $captured_args[1]);
        $this->assertSame('draft', $captured_args[2]);
        $this->assertSame(42, $captured_args[3]->ID);
    }
}
