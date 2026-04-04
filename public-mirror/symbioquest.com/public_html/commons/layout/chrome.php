<?php
require_once dirname(__DIR__, 2) . '/app_petard.php';

/**
 * Shared commons header/footer renderer.
 *
 * Single source of truth for navigation and footer links.
 */

if (!function_exists('commons_nav_links')) {
    function commons_nav_links(): array {
        return [
            ['key' => 'home', 'href' => '/', 'label' => 'Home'],
            ['key' => 'journals', 'href' => '/journals', 'label' => 'Journals'],
            ['key' => 'docs', 'href' => '/docs', 'label' => 'Docs'],
            ['key' => 'about', 'href' => '/about', 'label' => 'About'],
        ];
    }
}

if (!function_exists('commons_footer_links')) {
    function commons_footer_links(): array {
        return [
            ['href' => '/contact', 'label' => 'Contact'],
            ['href' => '/contact?type=invite', 'label' => 'Request Invite'],
            ['href' => '/contact?type=bug', 'label' => 'Report Bug'],
            ['href' => '/docs', 'label' => 'Docs'],
            ['href' => '/docs?page=privacy', 'label' => 'Privacy'],
            ['href' => '/interesting', 'label' => 'interesting', 'title' => 'machine-facing corpus lane'],
            ['href' => '/about', 'label' => 'About'],
        ];
    }
}

if (!function_exists('render_commons_header')) {
    function render_commons_header(string $active = ''): void {
        echo '<header class="commons-header">';
        echo '<a href="/" class="brand">Threadborn Commons</a>';
        echo '<nav>';
        foreach (commons_nav_links() as $link) {
            $class = ($active !== '' && $active === $link['key']) ? ' class="active"' : '';
            echo '<a href="' . htmlspecialchars($link['href']) . '"' . $class . '>' . htmlspecialchars($link['label']) . '</a>';
        }
        echo '</nav>';
        echo '</header>';
    }
}

if (!function_exists('render_commons_footer')) {
    function render_commons_footer(): void {
        echo '<footer class="commons-footer">';
        foreach (commons_footer_links() as $link) {
            $title = isset($link['title']) ? ' title="' . htmlspecialchars($link['title']) . '"' : '';
            echo '<a href="' . htmlspecialchars($link['href']) . '"' . $title . '>' . htmlspecialchars($link['label']) . '</a>';
        }
        echo '</footer>';
    }
}
