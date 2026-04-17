let isUpdating = false;

// Destructure settings with safe fallbacks
function updateExcerptCount() {
    const excerptField = document.querySelector('.editor-post-excerpt textarea');
    if (!excerptField || isUpdating) return;

    isUpdating = true;

    let counter = document.getElementById('custom-excerpt-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'custom-excerpt-counter';
        counter.style = 'text-align: right; font-size: 12px; font-weight: bold; margin-top: 5px;';
        excerptField.parentNode.insertBefore(counter, excerptField.nextSibling);
    }

    const text = excerptField.value.trim();
    const wordCount = text ? text.split(/\s+/).length : 0;
    
    const { excerptMin, excerptMax } = window.ANM_SETTINGS;
    const isWithinRange = wordCount >= excerptMin && wordCount <= excerptMax;
    const isOver = wordCount > excerptMax;

    const statusColor = isWithinRange ? '#46b450' : (isOver ? '#dc3232' : '#757575');

    counter.style.color = statusColor;
    counter.innerHTML = `Words: ${wordCount} ${isWithinRange ? '✓' : ''}`;

    isUpdating = false;
}

// MutationObserver to catch when the sidebar/excerpt field is rendered
const observer = new MutationObserver((mutations) => {
    const wasCounter = mutations.some(m => 
        m.target.id === 'custom-excerpt-counter' || 
        m.target.parentNode?.id === 'custom-excerpt-counter'
    );
    
    if (!wasCounter) {
        updateExcerptCount();
    }
});

observer.observe(document.body, { childList: true, subtree: true });

// Input listener for live typing
document.addEventListener('input', (e) => {
    if (e.target.matches('.editor-post-excerpt textarea')) {
        updateExcerptCount();
    }
});

/*
let prevValue = '';


subscribe(() => {
    console.log("subscribe");
    console.log(window.ANM_SETTINGS);

    if (!window.ANM_SETTINGS) {
        return;
    }

    const { excerptMin, excerptMax } = window.ANM_SETTINGS;
    const excerpt = select('core/editor').getEditedPostAttribute('excerpt');
    
    if (excerpt !== prevValue) {
        prevValue = excerpt;

        console.log(`Current Count: ${excerpt?.split(/\s+/).length || 0}`);

        const excerptField = document.querySelector('.editor-post-excerpt textarea');
        if (!excerptField) return;

        let counter = document.getElementById('custom-excerpt-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'custom-excerpt-counter';
            counter.style = 'text-align: right; font-size: 12px; font-weight: bold; margin-top: 5px;';
            excerptField.parentNode.insertBefore(counter, excerptField.nextSibling);
        }

        const text = excerptField.value.trim();
        const wordCount = text ? text.split(/\s+/).length : 0;
        
        let statusColor = (wordCount >= excerptMin && wordCount <= excerptMax) 
                            ? '#46b450'
                            : (wordCount > excerptMax ? '#dc3232' : '#757575');

        counter.style.color = statusColor;
        counter.innerHTML = `Words: ${wordCount} ${wordCount >= excerptMin && wordCount <= excerptMax ? '✓' : ''}`;
    }
});
*/