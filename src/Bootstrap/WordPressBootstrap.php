<?php

namespace Nahid\TaskPHP\Bootstrap;

use Nahid\TaskPHP\Contracts\TaskInterface;

/**
 * WordPress bootstrap for background tasks.
 * 
 * This bootstrap loads WordPress core functions and optionally
 * handles multisite blog switching.
 * 
 * Example usage:
 * 
 *     Task::registerBootstrap(new WordPressBootstrap(
 *         wpLoadPath: ABSPATH . 'wp-load.php',
 *         blogId: get_current_blog_id() // For multisite
 *     ))->async([...]);
 */
class WordPressBootstrap extends AbstractBootstrap
{
    protected $wpLoadPath;
    protected $blogId;
    protected $shortInit;

    public function __construct(
        string $wpLoadPath,
        ?int $blogId = null,
        bool $shortInit = false
    ) {
        $this->wpLoadPath = $wpLoadPath;
        $this->blogId = $blogId;
        $this->shortInit = $shortInit;
    }

    /**
     * Bootstrap WordPress.
     */
    public function bootstrap(): void
    {
        // Define SHORTINIT to skip loading plugins (faster bootstrap)
        if ($this->shortInit) {
            define('SHORTINIT', true);
        }

        // Load WordPress core
        require_once $this->wpLoadPath;

        // Switch to specific blog if multisite
        if (is_multisite() && $this->blogId) {
            switch_to_blog($this->blogId);
        }

        // Now all WordPress functions are available:
        // - wp_mail()
        // - get_post(), get_posts()
        // - get_option(), update_option()
        // - WP_Query
        // - WooCommerce functions (if installed)
        // - Custom post types, taxonomies, etc.
    }

    /**
     * Start a database transaction before each task (if using wpdb).
     */
    public function beforeTask(TaskInterface $task): void
    {
        global $wpdb;
        if ($wpdb) {
            $wpdb->query('START TRANSACTION');
        }
    }

    /**
     * Commit the database transaction after successful task execution.
     */
    public function afterTask(TaskInterface $task, $result): void
    {
        global $wpdb;
        if ($wpdb) {
            $wpdb->query('COMMIT');
        }
    }

    /**
     * Rollback the database transaction on task failure.
     */
    public function onError(TaskInterface $task, \Throwable $error): void
    {
        global $wpdb;
        if ($wpdb) {
            $wpdb->query('ROLLBACK');
        }
    }

    /**
     * Restore the previous blog if multisite.
     */
    public function shutdown(): void
    {
        if (is_multisite() && $this->blogId) {
            restore_current_blog();
        }
    }
}
