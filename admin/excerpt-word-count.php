<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

add_action( 'admin_footer', function() {
    ?>
    <script type="text/javascript">document.addEventListener('DOMContentLoaded', function() {
    let isUpdating = false; // Flag to prevent recursion

    function updateExcerptCount() {
        const excerptField = document.querySelector('.editor-post-excerpt textarea');
        if (!excerptField || isUpdating) return;

        isUpdating = true; // Block the observer while we make changes

        let counter = document.getElementById('custom-excerpt-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'custom-excerpt-counter';
            counter.style = 'text-align: right; font-size: 12px; font-weight: bold; margin-top: 5px;';
            excerptField.parentNode.insertBefore(counter, excerptField.nextSibling);
        }

        const text = excerptField.value.trim();
        const wordCount = text ? text.split(/\s+/).length : 0;
        
        let statusColor = (wordCount >= 20 && wordCount <= 40) ? '#46b450' : (wordCount > 40 ? '#dc3232' : '#757575');

        counter.style.color = statusColor;
        counter.innerHTML = `Words: ${wordCount} ${wordCount >= 20 && wordCount <= 40 ? '✓' : ''}`;

        isUpdating = false; // Unblock
    }

    // Only observe the sidebar specifically if possible, or use a throttle
    const observer = new MutationObserver((mutations) => {
        // Only run if the mutation wasn't our own counter
        const wasCounter = mutations.some(m => m.target.id === 'custom-excerpt-counter' || m.target.parentNode?.id === 'custom-excerpt-counter');
        if (!wasCounter) {
            updateExcerptCount();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('input', function(e) {
        if (e.target.matches('.editor-post-excerpt textarea')) {
            updateExcerptCount();
        }
    });
});
    </script>
    <?php
});