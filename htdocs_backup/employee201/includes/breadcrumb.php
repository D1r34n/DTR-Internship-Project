<?php
function renderBreadcrumb($items = []) {
    $currentPage = basename($_SERVER['PHP_SELF'], ".php");
    $currentPageName = ucwords(str_replace('_', ' ', $currentPage));

    echo '<nav class="breadcrumb">';
    foreach ($items as $item) {
        echo '<a href="' . htmlspecialchars($item['link']) . '">' 
            . htmlspecialchars($item['label']) . '</a> › ';
    }
    echo '<span>' . htmlspecialchars($currentPageName) . '</span>';
    echo '</nav>';
}
?>

<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #1e1e2d;
        margin: 0;
        color: #fff;
    }

    .container {
        padding: 20px;
    }

    /* Breadcrumb styling */
    .breadcrumb {
        font-size: 14px;
        margin-bottom: 20px;
        color: #9ca3af; /* subtle gray */
    }

    .breadcrumb a {
        color: #60a5fa; /* soft blue */
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .breadcrumb a:hover {
        text-decoration: underline;
        color: #3b82f6; /* brighter blue */
    }

    .breadcrumb span {
        color: #e5e7eb; /* current page lighter gray */
        font-weight: 600;
    }
</style>
